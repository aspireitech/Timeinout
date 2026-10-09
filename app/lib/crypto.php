<?php
// Field-level encryption (AES-256-GCM) for personal contact details and secrets.
// The key lives outside the database: app_key in config.php, or a key file that
// is generated once in app/keys/ (blocked from the web). Back it up: without the
// key, encrypted phone numbers and emails cannot be read.

const ENC_PREFIX = 'enc1:';

function app_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $cfg = (string) cfg('app_key', '');
    if ($cfg !== '') {
        $raw = base64_decode(preg_replace('/^base64:/', '', $cfg), true);
        if ($raw !== false && strlen($raw) === 32) {
            return $key = $raw;
        }
        return $key = hash('sha256', $cfg, true); // any passphrase works too
    }
    $dir = APP_DIR . '/keys';
    $file = $dir . '/app.key';
    if (!is_file($file)) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        $new = base64_encode(random_bytes(32));
        // Write atomically so two first requests can't create different keys
        $tmp = $file . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $new) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Cannot create the encryption key. Make app/ writable, or set app_key in app/config.php.');
        }
        @chmod($file, 0600);
    }
    $raw = base64_decode(trim((string) file_get_contents($file)), true);
    if ($raw === false || strlen($raw) !== 32) {
        throw new RuntimeException('The encryption key in app/keys/app.key is damaged.');
    }
    return $key = $raw;
}

function is_encrypted(?string $v): bool
{
    return $v !== null && str_starts_with($v, ENC_PREFIX);
}

function encrypt_pii(?string $plain): ?string
{
    if ($plain === null || $plain === '') {
        return $plain === '' ? null : $plain;
    }
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return ENC_PREFIX . base64_encode($iv . $tag . $cipher);
}

/** Decrypts; plain (older) values are returned unchanged. */
function decrypt_pii(?string $value): ?string
{
    if (!is_encrypted($value)) {
        return $value;
    }
    $raw = base64_decode(substr($value, strlen(ENC_PREFIX)), true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '[unreadable]' : $plain;
}
