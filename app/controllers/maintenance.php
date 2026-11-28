<?php
// Provider console → Restore check (/super/check): the same checks as cron/verify_restore.php, in the browser.

function super_restore_check(): void
{
    require_super();
    $rows = null;
    $to = null;
    if (is_post()) {
        $to = filter_var(trim((string) input('send_test')), FILTER_VALIDATE_EMAIL) ?: null;
        $rows = restore_checks($to);
        $c = restore_check_counts($rows);
        audit('Restore check', null, $c['fail'] === 0, "{$c['ok']} ok, {$c['warn']} warn, {$c['fail']} fail");
    }
    view('platform/super_check', ['rows' => $rows, 'to' => $to], 'platform');
}
