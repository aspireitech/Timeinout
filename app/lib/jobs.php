<?php
// The hourly job (cron/send_reports.php or /run-cron): every task runs on its own, so one failing task
// never stops the others, and the outcome is saved for /super ("Hourly job" status).

/** Runs every hourly task. Returns the log lines. */
function run_hourly_jobs(bool $forceReports = false, bool $forceScorecard = false): array
{
    $tasks = [
        'reports'   => fn() => run_scheduled_reports($forceReports),
        'wave'      => fn() => wave_sync(),
        'purge'     => fn() => purge_expired_tenants(),
        'subdomain' => fn() => subdomain_recheck(),
        'trials'    => fn() => run_nurture(),
        'outreach'  => fn() => run_prospects(),
        'scorecard' => fn() => send_scorecard($forceScorecard),
    ];
    $lines = [];
    $errors = 0;
    foreach ($tasks as $name => $task) {
        try {
            $lines = array_merge($lines, (array) $task());
        } catch (Throwable $e) {
            $errors++;
            $lines[] = "ERROR in $name: " . $e->getMessage();
        }
    }
    try {
        setting_set('cron_last_run', gmdate('Y-m-d H:i:s'));
        setting_set('cron_last_log', mb_substr(implode("\n", $lines) ?: 'Nothing due.', 0, 3000));
        setting_set('cron_last_errors', (string) $errors);
    } catch (Throwable $e) {
        $lines[] = 'Could not save the job status: ' . $e->getMessage();
    }
    return $lines;
}

/** [label, css class] for the hourly job status shown in /super. */
function hourly_job_status(): array
{
    $last = (string) setting('cron_last_run', '');
    if ($last === '') {
        return ['The hourly job has never run. Check hPanel → Advanced → Cron Jobs (see the Hosting page of the documentation).', 'danger'];
    }
    $ago = time() - (strtotime($last . ' UTC') ?: 0);
    $when = (new DateTime($last, new DateTimeZone('UTC')))->setTimezone(outreach_tz())->format('M j, g:i A');
    if ($ago > 2 * 3600) {
        return ["The hourly job last ran $when, over 2 hours ago. Check the cron job.", 'danger'];
    }
    $err = (int) setting('cron_last_errors', '0');
    return ['Hourly job last ran ' . $when . ($err ? " with $err error(s): see the log below" : ', all good'), $err ? 'warn' : 'in'];
}
