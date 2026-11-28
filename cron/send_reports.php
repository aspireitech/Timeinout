<?php
// The hourly job: customer reports, Wave sync, clean-up, subdomain checks, trial emails, outreach, scorecard.
// Hostinger → Advanced → Cron Jobs, PHP, once per hour:  public_html/cron/send_reports.php
// Add --force to send reports now, --scorecard to send the Monday scorecard now (testing).
if (PHP_SAPI !== 'cli') {
    exit('Run from the command line, or use the /run-cron?key=... URL instead.');
}
if (PHP_VERSION_ID < 80100) { // the app needs PHP 8.1+; this file itself still runs on older PHP to say so
    echo 'This cron job runs PHP ' . PHP_VERSION . ', but ClockInOut needs PHP 8.1 or newer. In hPanel, edit the cron job'
        . ' and choose "Custom" with the full path of a newer PHP, for example /opt/alt/php83/usr/bin/php or /usr/bin/php8.3.' . PHP_EOL;
    exit(1);
}
require __DIR__ . '/../app/bootstrap.php';
$lines = run_hourly_jobs(in_array('--force', $argv, true), in_array('--scorecard', $argv, true));
echo ($lines ? implode(PHP_EOL, $lines) : 'Nothing due.') . PHP_EOL;
