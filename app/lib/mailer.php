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
    if ((cfg('mail')['driver'] ?? '') === 'graph') {
        $m['driver'] = 'graph'; // Microsoft Graph chosen in config.php always wins
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
    if (($m['driver'] ?? '') === 'graph') {
        return graph_send($from, $fromName, $to, $bcc, $subject, $html, $attachments, $sender !== 'contact' ? contact_email() : null);
    }
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

// ---------- Microsoft 365 through Microsoft Graph (no SMTP, no mailbox passwords) ----------
// config.php: 'mail' => ['driver' => 'graph', …] and 'graph' => ['tenant_id', 'client_id', 'client_secret'].
// The Entra app needs the Mail.Send application permission, limited to the sending mailboxes.

function graph_http(string $method, string $url, array $headers, string $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        throw new RuntimeException('Could not reach Microsoft: ' . $err);
    }
    return [$code, json_decode((string) $resp, true) ?: []];
}

/** App-only access token, cached until shortly before it expires. */
function graph_token(): string
{
    $g = cfg('graph', []);
    if (empty($g['tenant_id']) || empty($g['client_id']) || empty($g['client_secret'])) {
        throw new RuntimeException('Microsoft Graph is not set up: add tenant_id, client_id and client_secret under graph in app/config.php.');
    }
    $cache = APP_DIR . '/sessions/graph_token.json';
    $c = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;
    if ($c && ($c['client'] ?? '') === $g['client_id'] && ($c['exp'] ?? 0) > time() + 120) {
        return (string) decrypt_pii($c['token']);
    }
    [$code, $d] = graph_http('POST', rtrim($g['login_url'] ?? 'https://login.microsoftonline.com', '/') . '/' . rawurlencode($g['tenant_id']) . '/oauth2/v2.0/token',
        ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'client_id' => $g['client_id'], 'client_secret' => $g['client_secret'],
            'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials']));
    if ($code !== 200 || empty($d['access_token'])) {
        throw new RuntimeException('Microsoft sign-in failed: ' . ($d['error_description'] ?? $d['error'] ?? "HTTP $code"));
    }
    @file_put_contents($cache, json_encode(['client' => $g['client_id'], 'exp' => time() + (int) ($d['expires_in'] ?? 3600), 'token' => encrypt_pii($d['access_token'])]));
    @chmod($cache, 0600);
    return (string) $d['access_token'];
}

function graph_send(string $from, string $fromName, array $to, array $bcc, string $subject, string $html, array $attachments, ?string $replyTo): bool
{
    $addr = fn(array $list) => array_map(fn($a) => ['emailAddress' => ['address' => $a]], $list);
    $msg = [
        'subject' => $subject,
        'body' => ['contentType' => 'HTML', 'content' => $html],
        'from' => ['emailAddress' => ['address' => $from, 'name' => $fromName]],
        'toRecipients' => $addr($to),
    ];
    if ($bcc) {
        $msg['bccRecipients'] = $addr($bcc);
    }
    if ($replyTo) {
        $msg['replyTo'] = $addr([$replyTo]);
    }
    foreach ($attachments as [$name, $mime, $data]) {
        $msg['attachments'][] = ['@odata.type' => '#microsoft.graph.fileAttachment', 'name' => $name, 'contentType' => $mime, 'contentBytes' => base64_encode($data)];
    }
    try {
        [$code, $d] = graph_http('POST', rtrim(cfg('graph')['api_url'] ?? 'https://graph.microsoft.com', '/') . '/v1.0/users/' . rawurlencode($from) . '/sendMail',
            ['Authorization: Bearer ' . graph_token(), 'Content-Type: application/json'], json_encode(['message' => $msg, 'saveToSentItems' => false]));
        if ($code === 202) {
            return true;
        }
        $GLOBALS['mail_error'] = 'Microsoft Graph: ' . ($d['error']['message'] ?? "HTTP $code") . ($code === 403 ? ' (check the Mail.Send permission, admin consent and that ' . $from . ' is in the allowed mailboxes)' : '');
    } catch (Throwable $e) {
        $GLOBALS['mail_error'] = $e->getMessage();
    }
    error_log('Graph mail error: ' . $GLOBALS['mail_error']);
    return false;
}
