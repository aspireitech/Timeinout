<?php
// Emails daily / weekly / monthly reports to each subscriber.
// cPanel → Cron Jobs, run every hour:
//   php /home/USER/timeinout/cron/send_reports.php
// Add --force to ignore the report_hour check (useful for testing).
if (PHP_SAPI !== 'cli') {
    exit('Run from the command line, or use the /cron?key=... URL instead.');
}
require __DIR__ . '/../app/bootstrap.php';
$lines = array_merge(run_scheduled_reports(in_array('--force', $argv, true)), wave_sync());
echo ($lines ? implode(PHP_EOL, $lines) : 'Nothing due.') . PHP_EOL;
