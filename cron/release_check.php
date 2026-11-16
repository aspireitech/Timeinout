<?php
// Security and quality gate, run on the code before EVERY release zip that gets uploaded to the server.
//   php cron/release_check.php                 checks only
//   php cron/release_check.php --zip OUT.zip   checks, then builds the upload zip from git HEAD only when nothing failed
// Exit code 0 = passed, 1 = failed. Runs on a developer machine (needs git); it is blocked from the web like all of cron/.
if (PHP_SAPI !== 'cli') {
    exit('Run from the command line.');
}
$root = dirname(__DIR__);
chdir($root);
$zipOut = null;
foreach ($argv as $i => $a) {
    if ($a === '--zip') {
        $zipOut = $argv[$i + 1] ?? null;
    }
}
$fails = $warns = 0;
$say = function (string $status, string $check, string $detail = '') use (&$fails, &$warns) {
    $status === 'FAIL' ? $fails++ : ($status === 'WARN' ? $warns++ : null);
    echo str_pad("[$status]", 7) . ' ' . $check . ($detail !== '' ? "\n         " . str_replace("\n", "\n         ", $detail) : '') . PHP_EOL;
};
$git = fn(string $args) => trim((string) shell_exec('git ' . $args . ' 2>/dev/null'));
$files = array_values(array_filter(explode("\n", $git('ls-files'))));
$php = array_values(array_filter($files, fn($f) => str_ends_with($f, '.php')));
$read = fn(string $f) => (string) @file_get_contents($f);

// 1. Every PHP file compiles
$bad = [];
foreach ($php as $f) {
    exec('php -l ' . escapeshellarg($f) . ' 2>&1', $out, $rc);
    if ($rc !== 0) {
        $bad[] = $f;
    }
}
$say($bad ? 'FAIL' : 'OK', 'PHP syntax (' . count($php) . ' files)', implode("\n", $bad));

// 2. Nothing secret or local-only is in the repository
$forbidden = array_values(array_filter($files, fn($f) => preg_match('#^(app/config\.php|app/keys/|public/__devlogin\.php|backups/|\.env)|\.(sql|sql\.gz|key|pem|p12)$#', $f) && $f !== 'database/schema.sql'));
$say($forbidden ? 'FAIL' : 'OK', 'No config.php, keys, backups, dumps or local helpers tracked', implode("\n", $forbidden));
$patterns = [
    'Stripe live key' => '/\b(sk|rk)_live_[A-Za-z0-9]{10,}/', 'Stripe webhook secret' => '/\bwhsec_[A-Za-z0-9]{16,}/',
    'Private key' => '/-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----/', 'AWS key' => '/\bAKIA[0-9A-Z]{16}\b/',
    'GitHub token' => '/\bgh[pousr]_[A-Za-z0-9]{30,}/', 'Slack token' => '/\bxox[bpas]-[A-Za-z0-9-]{10,}/',
    'Hosting account ID' => '/\bu\d{9}\b/', 'Filled-in password' => "/'(pass|password|client_secret|secret)'\s*=>\s*'[^'\s]{6,}'/",
    'Encryption key value' => "/'app_key'\s*=>\s*'(base64:)?[A-Za-z0-9+\/=]{30,}'/",
];
$hits = [];
foreach ($files as $f) {
    if (preg_match('#\.(png|jpe?g|gif|ico|zip|woff2?|ttf|pdf)$#', $f)) {
        continue;
    }
    $s = $read($f);
    foreach ($patterns as $name => $re) {
        // Placeholders in examples (db_password, change-me-now, MAILBOX-PASSWORD) and the public demo logins are not secrets
        if (preg_match($re, $s, $m) && !($name === 'Filled-in password' && preg_match("/=>\\s*'(db_password|change-me-now|[A-Z0-9-]+|Demo@1234|Staff@1234)'/", $m[0]))) {
            $hits[] = "$f: $name";
        }
    }
}
$say($hits ? 'FAIL' : 'OK', 'No secrets in tracked files (keys, tokens, passwords, hosting IDs)', implode("\n", array_unique($hits)));

// 3. The web server blocks private folders, and security headers are sent
$ht = $read('.htaccess');
$say(preg_match('#\^\(app\|database\|cron\|docs\|backups\)#', $ht) ? 'OK' : 'FAIL', '.htaccess blocks app/, database/, cron/, docs/, backups/');
$idx = $read('public/index.php');
$missing = array_filter(['Content-Security-Policy', 'Strict-Transport-Security', 'X-Content-Type-Options', 'Referrer-Policy', 'frame-ancestors'], fn($h) => !str_contains($idx, $h));
$say($missing ? 'FAIL' : 'OK', 'Security headers (CSP, HSTS, nosniff, referrer, frame-ancestors)', implode(', ', $missing));
$say(str_contains($read('cron/backup.php'), 'Require all denied') ? 'OK' : 'FAIL', 'Backups folder denied to the web');

// 4. Every form is CSRF-checked; every exempt webhook verifies a signature or a secret token
$routes = $read('app/routes.php');
$say(str_contains($routes, "csrf_check()") && str_contains($routes, "str_starts_with(\$handler, 'webhook_')") ? 'OK' : 'FAIL', 'Router checks the CSRF token on every POST');
$weak = [];
foreach ($php as $f) {
    if (preg_match_all('/function (webhook_\w+)\(\).*?\n}/s', $read($f), $m, PREG_SET_ORDER)) {
        foreach ($m as [$body, $name]) {
            if (!preg_match('/hash_equals|hash_hmac|\[a-f0-9\]\{32\}|stripe_verify|signature/i', $body)) {
                $weak[] = "$f: $name";
            }
        }
    }
}
$say($weak ? 'FAIL' : 'OK', 'Webhooks without CSRF verify a signature or secret token', implode("\n", $weak));

// 5. Injection patterns: request data inside SQL, shell or redirects
$inj = [];
foreach ($php as $f) {
    foreach (explode("\n", $read($f)) as $n => $line) {
        if (preg_match('/\b(q|row|rows|val)\(\s*["\'][^"\']*\$(_GET|_POST|_REQUEST|_COOKIE)/', $line)
            || preg_match('/\b(q|row|rows|val)\(\s*"[^"]*\{?\$[a-z_]*input\(/', $line)
            || preg_match('/\b(shell_exec|passthru|popen|proc_open|eval)\s*\(/', $line) && !str_starts_with($f, 'cron/release_check')
            || preg_match('/redirect\(\s*(input|\$_GET|\$_POST|\$_SERVER\[.HTTP_REFERER)/', $line)
            || preg_match('/\bunserialize\s*\(\s*(input|\$_)/', $line)) {
            $inj[] = "$f:" . ($n + 1) . ': ' . trim(mb_substr($line, 0, 120));
        }
    }
}
$say($inj ? 'FAIL' : 'OK', 'No request data in SQL, shell commands, eval, unserialize or redirects', implode("\n", $inj));

// 6. Output escaping in templates (review list: raw output of variables that are not numbers or known-safe helpers)
// Raw (unescaped) output already reviewed as safe (numbers, fixed class names, HTML built in code) is listed in
// cron/release_check_known.txt as "file: expression". Anything new fails until it is escaped with e() or reviewed and added.
$known = array_flip(array_filter(array_map('trim', explode("\n", $read('cron/release_check_known.txt')))));
$raw = [];
foreach (array_filter($files, fn($f) => str_starts_with($f, 'app/views/') && str_ends_with($f, '.php')) as $f) {
    foreach (explode("\n", $read($f)) as $n => $line) {
        if (preg_match_all('/<\?=\s*(\$(?!content\b)[a-zA-Z_][^;?]*?)\s*\?>/', $line, $m)) {
            foreach ($m[1] as $expr) {
                if (!preg_match('/^\$(k|i|v|l|g|cols\[|tile\(|L\(|a\(|_q|label|n\[|d\[|w\b)/', $expr) && !isset($known["$f: $expr"])) {
                    $raw[] = "$f:" . ($n + 1) . ': ' . $expr;
                }
            }
        }
    }
}
$say($raw ? 'FAIL' : 'OK', 'Template output escaped with e() (new unescaped output must be escaped or reviewed)', implode("\n", $raw));

// 7. Database changes are additive only
$mig = $read('app/lib/migrate.php');
$say(preg_match('/DROP\s+(TABLE|COLUMN|DATABASE)|TRUNCATE\s|DELETE\s+FROM\s+(tenants|users|students)/i', $mig) ? 'FAIL' : 'OK', 'Migrations only add (no DROP / TRUNCATE / mass DELETE)');

// 8. Safe defaults in the sample configuration
$sample = $read('app/config.sample.php');
$say(preg_match("/'debug'\s*=>\s*false/", $sample) && preg_match("/'force_https'\s*=>\s*true/", $sample) ? 'OK' : 'FAIL', "config.sample.php: 'debug' => false, 'force_https' => true");

// 9. Documentation updated with the code (CLAUDE.md rule)
$changedCode = $git('diff --name-only HEAD~1 HEAD -- app public cron');
$changedDocs = $git('diff --name-only HEAD~1 HEAD -- docs/MASTER_GUIDE.md');
$say($changedCode !== '' && $changedDocs === '' ? 'WARN' : 'OK', 'Master guide updated in the same commit as code changes');
$dirty = $git('status --porcelain --untracked-files=no');
$say($dirty !== '' ? 'WARN' : 'OK', 'All changes committed (the zip is built from the last commit)', $dirty);

echo PHP_EOL . ($fails ? "FAILED: $fails problem(s), $warns warning(s). No zip built." : "PASSED with $warns warning(s).") . PHP_EOL;
if ($fails) {
    exit(1);
}
if ($zipOut) {
    exec('git archive -o ' . escapeshellarg($zipOut) . ' HEAD', $o, $rc);
    if ($rc !== 0) {
        echo "Could not build $zipOut" . PHP_EOL;
        exit(1);
    }
    echo 'Built ' . $zipOut . ' (' . number_format(filesize($zipOut) / 1024) . ' KB) from commit ' . $git('rev-parse --short HEAD') . PHP_EOL
        . 'SHA-256 ' . hash_file('sha256', $zipOut) . PHP_EOL;
}
exit(0);
