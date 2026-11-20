<?php
// Restore check from the command line (after a restore or a move to new hosting):
//   php /home/USER/domains/clockinoutapp.com/public_html/cron/verify_restore.php
//   php …/cron/verify_restore.php --send-test=you@example.com     (also sends a real test email)
// Exit code 0 = nothing failed, 1 = something failed. Secrets are never printed.
if (PHP_SAPI !== 'cli') {
    exit('Run from the command line.');
}
if (PHP_VERSION_ID < 80100) {
    echo 'This runs PHP ' . PHP_VERSION . '; ClockInOut needs 8.1 or newer.' . PHP_EOL;
    exit(1);
}
require __DIR__ . '/../app/bootstrap.php';
$to = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--send-test=')) {
        $to = filter_var(substr($a, 12), FILTER_VALIDATE_EMAIL) ?: null;
    }
}
try {
    migrate(); // brings a restored database up to this code's schema
} catch (Throwable $e) {
    echo 'Database upgrade could not run: ' . $e->getMessage() . PHP_EOL;
}
$rows = restore_checks($to);
$mark = ['ok' => '[ OK ]', 'warn' => '[WARN]', 'fail' => '[FAIL]', 'info' => '[INFO]'];
$area = '';
foreach ($rows as $r) {
    if ($r['area'] !== $area) {
        $area = $r['area'];
        echo PHP_EOL . strtoupper($area) . PHP_EOL;
    }
    echo '  ' . $mark[$r['status']] . ' ' . $r['check'] . ': ' . $r['detail'] . PHP_EOL;
    if ($r['fix'] !== '' && $r['status'] !== 'ok') {
        echo '         fix: ' . $r['fix'] . PHP_EOL;
    }
}
$c = restore_check_counts($rows);
echo PHP_EOL . "Result: {$c['ok']} ok, {$c['warn']} warnings, {$c['fail']} failed" . PHP_EOL;
$GLOBALS['audit_system'] = true;
audit('Restore check (command line)', null, $c['fail'] === 0, "{$c['ok']} ok, {$c['warn']} warn, {$c['fail']} fail");
exit($c['fail'] ? 1 : 0);
