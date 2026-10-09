<?php
// Sends HTML email via PHP mail() or a minimal built-in SMTP client
// (STARTTLS / SSL + AUTH LOGIN), so no Composer packages are needed.

/** Email settings: the provider console (saved in the database) wins over config.php. */
function mail_config(): array
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
    return $m;
}

$GLOBALS['mail_error'] = null;

/**
 * Sends an HTML email, optionally with attachments: [[filename, mime type, bytes], ...].
 * On failure the reason is left in $GLOBALS['mail_error'].
 */
function send_mail(array $to, string $subject, string $html, array $attachments = []): bool
{
    $GLOBALS['mail_error'] = null;
    $to = array_values(array_filter(array_map('trim', $to), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    if (!$to) {
        $GLOBALS['mail_error'] = 'No valid recipient email address.';
        return false;
    }
    $m = mail_config();
    $from = ($m['from_email'] ?? '') ?: 'no-reply@' . cfg('base_domain');
    $fromName = ($m['from_name'] ?? '') ?: cfg('app_name');
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = ['MIME-Version: 1.0', 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <$from>"];

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
            smtp_send($m, $from, $to, array_merge($headers, [
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
