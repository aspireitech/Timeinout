<?php
// Sends HTML email via PHP mail() or a minimal built-in SMTP client
// (STARTTLS / SSL + AUTH LOGIN), so no Composer packages are needed.

/**
 * Email settings for one sender mailbox.
 *   'contact'  sign-in codes, account notices, leads   (default: contact@<base_domain>)
 *   'reports'  daily / weekly / monthly / on-demand reports  (default: reports@<base_domain>)
 * Server settings come from config.php 'mail', overridden by /super → Settings; each sender's
 * address, login and password come from config.php 'mail_senders' when set there.
 */
function mail_config(string $sender = 'contact'): array
{
    $m = cfg('mail', []);
    try {
        if (setting('mail_driver')) {
            foreach (['driver', 'host', 'port', 'secure', 'user', 'from_email', 'from_name'] as $k) {
                $m[$k] = setting('mail_' . $k, $m[$k] ?? '');
            }
            $m['pass'] = decrypt_pii(setting('mail_pass', '')) ?? '';
        }
    } catch (Throwable $e) {
        // settings table not there yet: use config.php
    }
    $s = cfg('mail_senders', [])[$sender] ?? [];
    foreach (['from_email', 'from_name', 'user', 'pass'] as $k) {
        if (($s[$k] ?? '') !== '') {
            $m[$k] = $s[$k];
        }
    }
    return $m;
}

/** The mailbox that receives copies, leads and suspicious-activity reports. */
function contact_email(): string
{
    return (string) (cfg('contact_email') ?: cfg('lead_email') ?: 'contact@' . cfg('base_domain'));
}

$GLOBALS['mail_error'] = null;

/**
 * Sends an HTML email, optionally with attachments: [[filename, mime type, bytes], ...].
 * $sender picks the mailbox ('contact' or 'reports'); $bcc gets a hidden copy.
 * On failure the reason is left in $GLOBALS['mail_error'].
 */
function send_mail(array $to, string $subject, string $html, array $attachments = [], string $sender = 'contact', array $bcc = []): bool
{
    $GLOBALS['mail_error'] = null;
    $valid = fn(array $list) => array_values(array_unique(array_filter(array_map('trim', $list), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
    $to = $valid($to);
    $bcc = array_values(array_diff($valid($bcc), $to));
    if (!$to) {
        $GLOBALS['mail_error'] = 'No valid recipient email address.';
        return false;
    }
    $m = mail_config($sender);
    $from = ($m['from_email'] ?? '') ?: $sender . '@' . cfg('base_domain');
    $fromName = ($m['from_name'] ?? '') ?: cfg('app_name');
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = ['MIME-Version: 1.0', 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <$from>"];
    if ($sender !== 'contact') {
        $headers[] = 'Reply-To: ' . contact_email(); // replies to a report reach a person
    }

    if ($attachments) {
        $b = 'b' . bin2hex(random_bytes(12));
        $headers[] = "Content-Type: multipart/mixed; boundary=\"$b\"";
        $body = "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html));
        foreach ($attachments as [$name, $mime, $data]) {
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
            $body .= "--$b\r\nContent-Type: $mime; name=\"$safe\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$safe\"\r\n\r\n" . chunk_split(base64_encode($data));
        }
        $body .= "--$b--\r\n";
    } else {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body = chunk_split(base64_encode($html));
    }

    if (($m['driver'] ?? 'mail') === 'smtp') {
        try {
            smtp_send($m, $from, array_merge($to, $bcc), array_merge($headers, [
                'To: ' . implode(', ', $to),
                'Subject: ' . $encSubject,
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . cfg('base_domain') . '>',
            ]), $body);
            return true;
        } catch (Throwable $ex) {
            $GLOBALS['mail_error'] = 'SMTP: ' . $ex->getMessage();
            error_log('SMTP error: ' . $ex->getMessage());
            return false;
        }
    }
    if ($bcc) {
        $headers[] = 'Bcc: ' . implode(', ', $bcc);
    }
    $ok = @mail(implode(', ', $to), $encSubject, $body, implode("\r\n", $headers));
    if (!$ok) {
        $GLOBALS['mail_error'] = 'PHP mail() is not working on this server. Use SMTP in Provider settings.';
    }
    return $ok;
}

function smtp_send(array $c, string $from, array $to, array $headers, string $body): void
{
    $secure = $c['secure'] ?? '';
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . (int) $c['port'], $errno, $errstr, 15);
    if (!$fp) {
        throw new RuntimeException("connect failed: $errstr");
    }
    stream_set_timeout($fp, 15);
    $cmd = function (?string $line, array $expect) use ($fp): string {
        if ($line !== null) {
            fwrite($fp, $line . "\r\n");
        }
        $resp = '';
        while (($l = fgets($fp, 515)) !== false) {
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        if (!in_array((int) substr($resp, 0, 3), $expect, true)) {
            throw new RuntimeException(trim($resp) ?: 'no response');
        }
        return $resp;
    };
    $helo = 'EHLO ' . (gethostname() ?: 'localhost');
    $cmd(null, [220]);
    $cmd($helo, [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS failed');
        }
        $cmd($helo, [250]);
    }
    if (!empty($c['user'])) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['user']), [334]);
        $cmd(base64_encode($c['pass']), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    foreach ($to as $rcpt) {
        $cmd("RCPT TO:<$rcpt>", [250, 251]);
    }
    $cmd('DATA', [354]);
    $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $data = preg_replace('/^\./m', '..', $data);
    $cmd($data . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
}
