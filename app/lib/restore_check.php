<?php
// Restore check: after a restore, a move to new hosting, or any time, test every credential and dependency
// for real and say exactly where to fix what fails. Never prints a secret.
//   Command line:  php cron/verify_restore.php [--send-test=you@example.com]
//   Browser:       /super → Settings → "Run the restore check" (/super/check)
// Each row: [area, check, status ok|warn|fail|info, detail, where to fix].

function restore_checks(?string $sendTestTo = null): array
{
    $rows = [];
    $add = function (string $area, string $check, string $status, string $detail, string $fix = '') use (&$rows) {
        $rows[] = compact('area', 'check', 'status', 'detail', 'fix');
    };
    $try = function (string $area, string $check, callable $fn, string $fix) use ($add) {
        try {
            [$status, $detail] = $fn();
            $add($area, $check, $status, $detail, $status === 'ok' ? '' : $fix);
        } catch (Throwable $e) {
            $add($area, $check, 'fail', mb_substr($e->getMessage(), 0, 300), $fix);
        }
    };

    // ---------- Server ----------
    $add('Server', 'PHP version', PHP_VERSION_ID >= 80100 ? 'ok' : 'fail', PHP_VERSION,
        'hPanel → Advanced → PHP Configuration → PHP 8.1 or newer (also the PHP path in the cron jobs).');
    $missing = array_values(array_filter(['pdo_mysql', 'openssl', 'curl', 'mbstring', 'json', 'zlib', 'zip'], fn($x) => !extension_loaded($x)));
    $add('Server', 'PHP extensions', $missing ? ($missing === ['zip'] ? 'warn' : 'fail') : 'ok', $missing ? 'missing: ' . implode(', ', $missing) : 'pdo_mysql, openssl, curl, mbstring, json, zlib, zip',
        'hPanel → Advanced → PHP Configuration → PHP extensions → tick the missing ones.');

    // ---------- Secrets on the server ----------
    $cfgFile = APP_DIR . '/config.php';
    $perm = @fileperms($cfgFile) & 0777;
    $add('Secrets', 'app/config.php present', is_file($cfgFile) ? 'ok' : 'fail', is_file($cfgFile) ? 'found' : 'missing',
        'Upload config.php from the password manager to public_html/app/config.php.');
    $add('Secrets', 'config.php permissions', $perm && ($perm & 0007) === 0 ? 'ok' : 'warn', $perm ? sprintf('%o', $perm) : '?',
        'File Manager → app/config.php → Permissions → 600 (or 640). Command: chmod 600 app/config.php');
    $add('Secrets', 'debug off', cfg('debug') ? 'fail' : 'ok', cfg('debug') ? "'debug' => true" : "'debug' => false",
        "app/config.php: set 'debug' => false (true shows errors to visitors).");
    $add('Secrets', 'HTTPS forced', cfg('force_https') ? 'ok' : 'warn', cfg('force_https') ? 'on' : 'off', "app/config.php: 'force_https' => true once SSL works.");
    $try('Secrets', 'Encryption key (app.key) opens the stored data', function () {
        $probe = 'restore-check-' . bin2hex(random_bytes(4));
        if (decrypt_pii(encrypt_pii($probe)) !== $probe) {
            return ['fail', 'the key cannot encrypt and decrypt'];
        }
        $samples = rows("SELECT k, v FROM settings WHERE v LIKE 'enc1:%' LIMIT 20");
        $samples = array_merge($samples, array_map(fn($t) => ['k' => 'db_pass of ' . $t['slug'], 'v' => $t['db_pass']], rows("SELECT slug, db_pass FROM tenants WHERE db_pass LIKE 'enc1:%' LIMIT 20")));
        $bad = [];
        foreach ($samples as $s) {
            $plain = decrypt_pii((string) $s['v']);
            if ($plain === null || $plain === '[unreadable]') {
                $bad[] = $s['k'];
            }
        }
        if ($bad) {
            return ['fail', 'stored values cannot be opened with this key: ' . implode(', ', array_slice($bad, 0, 6))];
        }
        return ['ok', count($samples) . ' stored secret(s) opened correctly'];
    }, 'Put back the ORIGINAL app/keys/app.key (password manager). A new key cannot read old data.');

    // ---------- Databases ----------
    $try('Database', 'Platform database login', function () {
        $v = (int) val("SELECT v FROM settings WHERE k = 'schema_version'");
        return [$v >= SCHEMA_VERSION ? 'ok' : 'warn', cfg('db')['name'] . ' · schema ' . $v . ' (code expects ' . SCHEMA_VERSION . ')'];
    }, "app/config.php → 'db' (host, name, user, pass) must match hPanel → Databases. Schema behind: open the website once; it upgrades itself.");
    foreach (rows('SELECT * FROM tenants WHERE db_name IS NOT NULL AND deleted_at IS NULL ORDER BY slug') as $t) {
        $try('Database', 'Portal "' . $t['slug'] . '" own database', function () use ($t) {
            $pdo = tenant_pdo($t);
            $n = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
            return ['ok', $t['db_name'] . ' · ' . $n . ' students'];
        }, '/super → Subscribers → this portal → database details (host, name, user, password) must match hPanel → Databases; import its .sql.gz first.');
    }

    // ---------- Files and folders ----------
    foreach (['backups' => ROOT_DIR . '/backups', 'sessions' => APP_DIR . '/sessions', 'logos' => PUBLIC_DIR . '/uploads/logos'] as $name => $dir) {
        $ok = is_dir($dir) && is_writable($dir);
        $add('Files', "Folder $name writable", $ok ? 'ok' : ($name === 'backups' && !is_dir($dir) ? 'warn' : 'fail'), $ok ? 'yes' : (is_dir($dir) ? 'not writable' : 'missing'),
            "File Manager → create the folder or set permissions 755. Command: mkdir -p $name && chmod 755 $name");
    }
    $missingLogos = [];
    foreach (rows("SELECT slug, logo_path FROM tenants WHERE logo_path IS NOT NULL AND logo_path <> '' AND deleted_at IS NULL") as $t) {
        if (!is_file(PUBLIC_DIR . '/' . ltrim((string) $t['logo_path'], '/'))) { // stored like uploads/logos/x.png
            $missingLogos[] = $t['slug'];
        }
    }
    $add('Files', 'Customer logos present', $missingLogos ? 'warn' : 'ok', $missingLogos ? 'missing for: ' . implode(', ', array_slice($missingLogos, 0, 8)) : 'all found',
        'Unzip uploads_logos.zip from the backup folder into public/uploads/logos/.');

    // ---------- Scheduled jobs and backups ----------
    $run = status_age(setting('cron_last_run', null));
    $add('Jobs', 'Hourly job ran', $run !== null && $run < 2 * 3600 ? 'ok' : 'fail', $run === null ? 'never' : status_ago($run),
        'hPanel → Advanced → Cron Jobs → hourly: php …/public_html/cron/send_reports.php (page 3). Check "View output".');
    $b = status_age(status_last_backup());
    $add('Jobs', 'Nightly backup ran', $b !== null && $b < 26 * 3600 ? 'ok' : 'fail', $b === null ? 'no backup folder found' : status_ago($b),
        'hPanel → Cron Jobs → daily 2:00: php …/public_html/cron/backup.php. Run it once by hand to test.');

    // ---------- Email ----------
    $m = mail_config('contact');
    $driver = (string) ($m['driver'] ?? 'mail');
    if ($driver === 'graph') {
        $try('Email', 'Microsoft 365 sign-in (Graph client secret)', function () {
            @unlink(APP_DIR . '/sessions/graph_token.json');
            graph_token();
            return ['ok', 'token received'];
        }, "Entra → App registrations → ClockInOut → Certificates & secrets → new secret → app/config.php 'graph' → 'client_secret'. Expired secrets stop all email.");
        $try('Email', 'Microsoft 365 inbox read (Mail.Read, for replies and bounces)', function () {
            $box = (string) (($m = mail_config('contact'))['from_email'] ?? '') ?: contact_email();
            [$code, $d] = graph_http('GET', rtrim(cfg('graph')['api_url'] ?? 'https://graph.microsoft.com', '/') . '/v1.0/users/' . rawurlencode($box) . '/mailFolders/inbox?$select=totalItemCount',
                ['Authorization: Bearer ' . graph_token()], '');
            return $code === 200 ? ['ok', 'inbox of ' . $box . ' readable'] : ['fail', 'HTTP ' . $code . ': ' . ($d['error']['message'] ?? '')];
        }, 'Entra → API permissions → Microsoft Graph → Application "Mail.Read" → Grant admin consent; the Exchange access policy must include contact@ (page 4).');
    } elseif ($driver === 'smtp') {
        $try('Email', 'SMTP login (' . ($m['host'] ?? '?') . ')', function () use ($m) {
            $secure = $m['secure'] ?? '';
            $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $m['host'] . ':' . (int) $m['port'], $no, $err, 15);
            if (!$fp) {
                return ['fail', "cannot connect: $err"];
            }
            $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') { break; } } return $r; };
            $read();
            fwrite($fp, "EHLO check\r\n"); $read();
            if ($secure === 'tls') { fwrite($fp, "STARTTLS\r\n"); $read(); stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); fwrite($fp, "EHLO check\r\n"); $read(); }
            fwrite($fp, "AUTH LOGIN\r\n"); $read(); fwrite($fp, base64_encode((string) $m['user']) . "\r\n"); $read(); fwrite($fp, base64_encode((string) $m['pass']) . "\r\n");
            $r = $read(); fwrite($fp, "QUIT\r\n"); fclose($fp);
            return str_starts_with($r, '235') ? ['ok', 'login accepted'] : ['fail', 'login refused: ' . trim($r)];
        }, "app/config.php → 'mail' and 'mail_senders' → 'contact' → user / pass (the mailbox password).");
    } else {
        $add('Email', 'Sending method', 'warn', 'PHP mail() (no login, no copies, weaker delivery)', "app/config.php → 'mail' → 'driver' => 'graph' (Microsoft 365) or 'smtp'.");
    }
    if ($sendTestTo) {
        $try('Email', 'Test email to ' . $sendTestTo, function () use ($sendTestTo) {
            $ok = send_mail([$sendTestTo], cfg('app_name') . ' restore check: email works', '<p>This test email was sent by the restore check at ' . gmdate('Y-m-d H:i') . ' UTC.</p>');
            return $ok ? ['ok', 'sent; check the inbox (and spam)'] : ['fail', (string) ($GLOBALS['mail_error'] ?? '')];
        }, 'See the error; most often an expired Microsoft secret or a wrong mailbox password.');
    }

    // ---------- Payments ----------
    if (stripe_ready()) {
        $try('Payments', 'Stripe secret key', function () {
            stripe_request('GET', '/v1/balance');
            return [str_starts_with(secret('stripe_secret'), 'sk_live_') ? 'ok' : 'warn', str_starts_with(secret('stripe_secret'), 'sk_live_') ? 'live key works' : 'works, but it is a TEST key'];
        }, '/super → Settings → Stripe secret key (Stripe → Developers → API keys).');
        $add('Payments', 'Stripe webhook signing secret', secret('stripe_webhook_secret') !== '' ? 'ok' : 'fail', secret('stripe_webhook_secret') !== '' ? 'set' : 'missing',
            'Stripe → Developers → Webhooks → endpoint https://' . cfg('base_domain') . '/webhooks/stripe → Signing secret → /super → Settings.');
        foreach (array_keys(plans()) as $pk) {
            foreach (['', '_annual'] as $sfx) {
                $id = (string) setting('stripe_price_' . $pk . $sfx, '');
                if ($id === '') {
                    continue;
                }
                $try('Payments', "Stripe price $pk" . ($sfx ? ' (yearly)' : ''), function () use ($id) {
                    $p = stripe_request('GET', '/v1/prices/' . rawurlencode($id));
                    return [!empty($p['active']) ? 'ok' : 'warn', $id . (!empty($p['active']) ? ' active' : ' NOT active')];
                }, '/super → Settings → price IDs must exist in the same Stripe account and mode.');
            }
        }
    } else {
        $add('Payments', 'Stripe', 'info', 'not set up (invoices only)', '');
    }
    if (wave_ready()) {
        $try('Payments', 'Wave token', function () {
            wave_gql('query { user { id } }');
            return ['ok', 'works'];
        }, '/super → Settings → Wave token (Wave → Settings → Developer → Full access token).');
    }

    // ---------- Sign-in ----------
    $add('Sign-in', 'Google sign-in for /super', google_ready() ? 'ok' : 'warn', google_ready() ? 'client set; redirect must be https://' . cfg('base_domain') . '/super/google/callback' : 'not set (email code still works)',
        'Google Cloud → APIs & Services → Credentials → OAuth client → redirect URIs; keys in /super → Sign-in.');
    $add('Sign-in', 'Prospect finder key', setting('prospect_api_key_hash', '') !== '' ? 'ok' : 'warn', setting('prospect_api_key_hash', '') !== '' ? 'set (the key itself lives in the Claude environment)' : 'missing',
        '/super → Prospects → Replace finder key, then put it in claude.ai → Cloud environment "Timeinout" → network secret.');

    // ---------- Website, DNS and SSL ----------
    $probe = function (string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$code, (string) $body, $err];
    };
    $nonce = bin2hex(random_bytes(16));
    setting_set('ping_nonce', $nonce); // only this server's database knows it
    $where = function (string $body, string $who): array {
        if ($body === "clockinout:$who:this-server") {
            return ['ok', 'HTTPS works and the address reaches this server'];
        }
        if ($body === "clockinout:$who:other-server" || $body === "clockinout:$who") {
            return ['fail', 'the address still reaches ANOTHER server (old hosting?): DNS not switched yet'];
        }
        return ['fail', 'unexpected answer'];
    };
    $try('Website', 'https://' . cfg('base_domain') . ' reaches this server', function () use ($probe, $nonce, $where) {
        [$code, $body, $err] = $probe('https://' . cfg('base_domain') . base_path() . '/__ping?n=' . $nonce);
        return $code ? $where($body, 'platform') : ['fail', $err ?: 'no answer'];
    }, 'Namecheap → Advanced DNS → A record @ (and www) → this server\'s IP; hPanel → SSL → install. Before switching DNS this is expected to fail.');
    $subs = rows('SELECT slug FROM tenants WHERE subdomain_ok = 1 AND deleted_at IS NULL ORDER BY id LIMIT 5');
    foreach ($subs as $t) {
        $try('Website', 'https://' . $t['slug'] . '.' . cfg('base_domain'), function () use ($probe, $t, $nonce, $where) {
            [$code, $body, $err] = $probe('https://' . $t['slug'] . '.' . cfg('base_domain') . '/__ping?n=' . $nonce);
            return $code ? $where($body, $t['slug']) : ['fail', $err ?: 'no answer'];
        }, 'Namecheap → A record * (wildcard) → server IP; hPanel → Domains → Subdomains → ' . $t['slug'] . ' → folder public_html; SSL issued.');
    }
    $add('Website', 'Search Console tag', setting('google_site_verification', '') !== '' ? 'ok' : 'warn', setting('google_site_verification', '') !== '' ? 'set' : 'missing', '/super → Settings → Search Console HTML tag.');

    return $rows;
}

/** [ok, warn, fail] counts. */
function restore_check_counts(array $rows): array
{
    $c = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
    foreach ($rows as $r) {
        $c[$r['status']]++;
    }
    return $c;
}
