<?php
// Emails daily / weekly / monthly reports to each subscriber.
// cPanel → Cron Jobs, run every hour:
//   php /home/USER/clockinout/cron/send_reports.php
// Add --force to ignore the report_hour check (useful for testing).
// Add --scorecard to send the Monday growth scorecard now.
if (PHP_SAPI !== 'cli') {
    exit('Run from the command line, or use the /cron?key=... URL instead.');
}
require __DIR__ . '/../app/bootstrap.php';
$lines = array_merge(run_scheduled_reports(in_array('--force', $argv, true)), wave_sync(), purge_expired_tenants(), subdomain_recheck(), run_nurture(), send_scorecard(in_array('--scorecard', $argv, true)));
echo ($lines ? implode(PHP_EOL, $lines) : 'Nothing due.') . PHP_EOL;
