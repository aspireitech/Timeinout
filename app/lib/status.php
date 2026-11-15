<?php
// Status page (/status, /status.json): health of each part of the service.
// The public view shows only plain states; the provider (signed in to /super) also sees the details.

const STATUS_LABELS = ['ok' => 'Operational', 'warn' => 'Degraded', 'down' => 'Outage', 'info' => 'Not set up'];

function status_age(?string $utc): ?int
{
    return $utc ? max(0, time() - (strtotime($utc . ' UTC') ?: 0)) : null;
}

function status_ago(?int $sec): string
{
    if ($sec === null) {
        return 'never';
    }
    if ($sec < 120) {
        return 'just now';
    }
    if ($sec < 7200) {
        return round($sec / 60) . ' minutes ago';
    }
    if ($sec < 172800) {
        return round($sec / 3600) . ' hours ago';
    }
    return round($sec / 86400) . ' days ago';
}

/** Newest folder in backups/ (named YYYY-MM-DD_HHMM, UTC), as "Y-m-d H:i:s", or null. */
function status_last_backup(): ?string
{
    $dirs = glob(ROOT_DIR . '/backups/20[0-9][0-9]-[0-9][0-9]-[0-9][0-9]_[0-9][0-9][0-9][0-9]', GLOB_ONLYDIR) ?: [];
    if (!$dirs) {
        return null;
    }
    rsort($dirs);
    $n = basename($dirs[0]);
    return substr($n, 0, 10) . ' ' . substr($n, 11, 2) . ':' . substr($n, 13, 2) . ':00';
}

/**
 * Every component: ['name', 'state' (ok|warn|down|info), 'public' (one line for everyone), 'detail' (provider only)].
 */
function system_status(bool $detail = false): array
{
    $c = [];
    $add = function (string $name, string $state, string $public, string $more = '') use (&$c, $detail) {
        $c[] = ['name' => $name, 'state' => $state, 'public' => $public, 'detail' => $detail ? $more : ''];
    };

    // Website + database
    try {
        val('SELECT 1');
        $tenants = (int) val('SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL');
        $add('Website and sign-up', 'ok', 'Pages load and new portals can be created.', "PHP " . PHP_VERSION . " · schema " . SCHEMA_VERSION . " · $tenants portals");
        $dbOk = true;
    } catch (Throwable $e) {
        $add('Website and sign-up', 'down', 'The database is not reachable.', $e->getMessage());
        $dbOk = false;
    }

    // Customer portals and kiosk (dedicated databases reachable)
    if ($dbOk) {
        $bad = [];
        $own = rows("SELECT * FROM tenants WHERE deleted_at IS NULL AND db_name IS NOT NULL AND db_name <> '' LIMIT 50");
        foreach ($own as $t) {
            try {
                tenant_pdo($t)->query('SELECT 1');
            } catch (Throwable $e) {
                $bad[] = $t['slug'];
            }
        }
        $add('Customer portals and kiosk', $bad ? 'warn' : 'ok', $bad ? 'Some portals have trouble reaching their data.' : 'Kiosks, admin portals and reports are working.',
            count($own) . ' with their own database' . ($bad ? '; not reachable: ' . implode(', ', $bad) : ''));
    }

    // Email
    $ok = status_age(setting('mail_last_ok', null));
    $fail = status_age(setting('mail_last_fail', null));
    if ($ok === null && $fail === null) {
        $add('Email (sign-in codes, reports, alerts)', 'info', 'Status shows after the next email goes out.', 'Use /super → Settings → Send a test email.');
    } elseif ($fail !== null && ($ok === null || $fail < $ok)) {
        $add('Email (sign-in codes, reports, alerts)', 'down', 'Emails are not going out right now.', 'Last failure ' . status_ago($fail) . ': ' . setting('mail_last_error', ''));
    } else {
        $add('Email (sign-in codes, reports, alerts)', 'ok', 'Emails are being delivered.', 'Last successful send ' . status_ago($ok) . ($fail !== null ? '; last failure ' . status_ago($fail) : ''));
    }

    // Sign-in
    $add('Sign-in', 'ok', 'Admin and staff sign-in are working.', (google_ready() ? 'Provider Google sign-in set up' : 'Provider Google sign-in not set up') . ' · admin codes ' . (admin_otp_enabled() ? 'on' : 'off'));

    // Scheduled jobs
    $run = status_age(setting('cron_last_run', null));
    $err = (int) setting('cron_last_errors', '0');
    if ($run === null) {
        $add('Scheduled reports and automation', 'down', 'Scheduled reports are not running.', 'The hourly job has never run. Check hPanel → Cron Jobs.');
    } elseif ($run > 3 * 3600) {
        $add('Scheduled reports and automation', 'down', 'Scheduled reports are delayed.', 'Hourly job last ran ' . status_ago($run) . '.');
    } else {
        $add('Scheduled reports and automation', $err ? 'warn' : 'ok', $err ? 'Running, with some errors.' : 'Reports and automatic emails run every hour.',
            'Hourly job last ran ' . status_ago($run) . ($err ? " with $err error(s)" : '') . ".\n" . setting('cron_last_log', ''));
    }

    // Payments
    if (!stripe_ready()) {
        $add('Card payments', wave_ready() ? 'ok' : 'info', wave_ready() ? 'Invoices are available.' : 'Payments are handled by invoice.', 'Stripe not set up');
    } else {
        $ev = status_age(setting('stripe_last_event', null));
        $add('Card payments', 'ok', 'Card checkout and renewals are working.', 'Stripe set up; last webhook event ' . status_ago($ev) . (setting('stripe_tax', '0') === '1' ? ' · Stripe Tax on' : ''));
    }

    // Backups
    $b = status_age(status_last_backup());
    if ($b === null) {
        $add('Data backups', 'warn', 'Daily backups are being set up.', 'No backup folder yet. The 2:00 cron job creates one each night.');
    } else {
        $add('Data backups', $b > 30 * 3600 ? 'warn' : 'ok', $b > 30 * 3600 ? 'The last backup is older than a day.' : 'Data is backed up every night.', 'Last backup ' . status_ago($b) . '.');
    }

    if ($detail) { // provider-only components
        try {
            $counts = array_column(rows('SELECT status, COUNT(*) AS n FROM prospects GROUP BY status'), 'n', 'status');
            $sent = (int) val('SELECT COUNT(*) FROM prospects WHERE last_sent_at >= ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
            $on = setting('prospects_on', '0') === '1' && !outreach_problem();
            $c[] = ['name' => 'Outreach to prospects', 'state' => $on ? 'ok' : 'info', 'public' => '', 'detail' =>
                ($on ? 'Sending on' : 'Sending off' . (outreach_problem() ? ': ' . outreach_problem() : '')) . " · $sent sent in the last 24 h · "
                . implode(' · ', array_map(fn($k, $v) => (PROSPECT_STATUSES[$k] ?? $k) . ": $v", array_keys($counts), $counts))];
            $f = status_age(val("SELECT MAX(created_at) FROM prospects WHERE added_by = 'finder'"));
            $c[] = ['name' => 'Daily lead finder (Claude)', 'state' => $f === null ? 'info' : ($f > 36 * 3600 ? 'warn' : 'ok'), 'public' => '',
                'detail' => 'Last delivery ' . status_ago($f) . '.'];
        } catch (Throwable $e) {
        }
    }
    return $c;
}

/** Overall state: the worst of the public components. */
function status_overall(array $components): string
{
    $rank = ['ok' => 0, 'info' => 0, 'warn' => 1, 'down' => 2];
    $worst = 0;
    foreach ($components as $x) {
        if ($x['public'] !== '') {
            $worst = max($worst, $rank[$x['state']] ?? 0);
        }
    }
    return ['ok', 'warn', 'down'][$worst];
}
