<?php
// Trial follow-up, run by the hourly cron (cron/send_reports.php and /cron):
//   - emails to trial customers when a step is due (no people yet, kiosk not used, 2 days left, last day)
//   - alerts to the provider (trial not used after 2 days, trial ends tomorrow)
//   - a Monday scorecard to the provider
// Each email goes out once per portal (nurture_log), at most one customer email a day, and never to a paying
// customer. Turn it all off in /super → Settings ("Trial follow-up emails").

/** Real activity in a portal: people added after sign-up (sample people excluded) and kiosk check-ins. */
function trial_activity(array $t): array
{
    return with_tenant_db($t, function () use ($t) {
        $after = date('Y-m-d H:i:s', strtotime((string) $t['created_at']) + 300); // sample people are added at sign-up
        $people = (int) val('SELECT COUNT(*) FROM students WHERE tenant_id = ? AND created_at > ?', [$t['id'], $after])
            + (int) val('SELECT COUNT(*) FROM teachers WHERE tenant_id = ? AND created_at > ?', [$t['id'], $after]);
        $checkins = (int) val('SELECT COUNT(*) FROM attendance WHERE tenant_id = ?', [$t['id']]);
        $owner = row("SELECT name, email FROM users WHERE tenant_id = ? AND role = 'owner' ORDER BY id LIMIT 1", [$t['id']]);
        return ['people' => $people, 'checkins' => $checkins, 'owner' => $owner];
    });
}

function nurture_sent(int $tenantId, string $kind): bool
{
    return (bool) val('SELECT 1 FROM nurture_log WHERE tenant_id = ? AND kind = ?', [$tenantId, $kind]);
}

function nurture_mark(int $tenantId, string $kind): void
{
    q('INSERT IGNORE INTO nurture_log (tenant_id, kind, sent_at) VALUES (?, ?, ?)', [$tenantId, $kind, gmdate('Y-m-d H:i:s')]);
}

/** Simple branded email body. */
function nurture_html(string $title, string $body, ?string $button = null, ?string $url = null): string
{
    return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#161B36;line-height:1.5">'
        . '<h2 style="margin:0 0 10px">' . e($title) . '</h2>' . $body
        . ($button && $url ? '<p style="margin:18px 0"><a href="' . e($url) . '" style="display:inline-block;background:#5B4CE0;color:#fff;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:bold">' . e($button) . '</a></p>' : '')
        . '<p style="color:#6b7090;font-size:13px">Questions? Just reply to this email: a real person reads it.</p></div>';
}

/** The customer email that is due for a trial portal, or null. Order matters: the most useful step first. */
function nurture_customer_email(array $t, array $a, int $daysIn, int $left): ?array
{
    $first = trim(explode(' ', (string) ($a['owner']['name'] ?? ''))[0]) ?: 'there';
    $name = $t['name'];
    $promo = trim((string) setting('trial_promo', ''));
    $stats = '<p>So far: <b>' . $a['people'] . '</b> ' . ($a['people'] === 1 ? 'person' : 'people') . ' added and <b>' . $a['checkins'] . '</b> check-ins.</p>';
    if ($left === 1) {
        return ['last-day', 'Your free trial ends tomorrow', nurture_html('Your free trial ends tomorrow',
            "<p>Hi $first,</p><p>The free trial for <b>" . e($name) . '</b> ends tomorrow.</p>' . $stats
            . '<p>Choose a plan to keep your kiosk, people and reports. Your data stays safe, and the kiosk keeps working for 7 more days while you decide.</p>'
            . ($promo !== '' ? '<p><b>' . e($promo) . '</b></p>' : ''), 'Choose a plan', tenant_url($t, '/admin/billing'))];
    }
    if ($left === 2) {
        return ['two-days', '2 days left on your free trial', nurture_html('2 days left on your free trial',
            "<p>Hi $first,</p><p>Your free trial for <b>" . e($name) . '</b> ends in 2 days.</p>' . $stats
            . '<p>Most centers choose <b>Business yearly: $' . number_format(plans()['school']['annual'] ?? 790, 0) . ' a year (2 months free)</b>. Starter and Enterprise, monthly or yearly, are on the same page.</p>'
            . ($promo !== '' ? '<p><b>' . e($promo) . '</b></p>' : ''), 'See plans', tenant_url($t, '/admin/billing'))];
    }
    if ($daysIn >= 2 && $a['checkins'] === 0) {
        return ['kiosk', 'Put ' . $name . ' on a tablet at your door', nurture_html('Put your kiosk at the door',
            "<p>Hi $first,</p><p>Your space is ready, but nobody has signed in yet. Three steps:</p>"
            . '<ol><li>On the tablet or phone at your front door, open <a href="' . e(tenant_url($t, '/')) . '">' . e(tenant_url($t, '/')) . '</a>.</li>'
            . '<li>Sign in once with a staff login (Admin → Users can create one).</li>'
            . '<li>Families tap their name, choose who is with them, and sign in. That\'s it.</li></ol>'
            . '<p>Tip: add the page to the tablet\'s home screen so it opens like an app. Want help? Reply and we\'ll set it up with you in 10 minutes.</p>',
            'Open my kiosk', tenant_url($t, '/'))];
    }
    if ($daysIn >= 1 && $a['people'] === 0) {
        return ['import', 'Add your people to ' . $name . ' (we can do it for you)', nurture_html('Add your people',
            "<p>Hi $first,</p><p>Your space <b>" . e($name) . '</b> doesn\'t have your own people yet. Two easy ways:</p>'
            . '<ol><li><b>Upload your list:</b> Admin → Import takes your Excel or CSV as it is; you match the columns once.</li>'
            . '<li><b>Let us do it:</b> reply to this email with your spreadsheet and we\'ll import it for you today.</li></ol>',
            'Import my list', tenant_url($t, '/admin/import'))];
    }
    return null;
}

/** Hourly: send what is due. Returns log lines for the cron output. */
function run_nurture(): array
{
    if (setting('nurture_on', '1') !== '1') {
        return [];
    }
    $log = [];
    $provider = (string) (cfg('contact_email') ?: (cfg('super_admin')['email'] ?? ''));
    $GLOBALS['audit_system'] = true;
    foreach (rows("SELECT * FROM tenants WHERE status = 'trial' AND deleted_at IS NULL AND slug <> ? ORDER BY id", [DEMO_SLUG]) as $t) {
        try {
            if (billing_state($t) !== 'trial') {
                continue;
            }
            $a = trial_activity($t);
            $daysIn = (int) floor((time() - strtotime((string) $t['created_at'])) / 86400);
            $left = trial_days_left($t);
            // To the customer: at most one a day, each kind once
            $last = val("SELECT MAX(sent_at) FROM nurture_log WHERE tenant_id = ? AND kind NOT LIKE 'alert-%'", [$t['id']]);
            $mail = nurture_customer_email($t, $a, $daysIn, $left);
            $to = $a['owner']['email'] ?? null;
            if ($mail && $to && !nurture_sent((int) $t['id'], $mail[0]) && (!$last || strtotime($last . ' UTC') < time() - 20 * 3600)) {
                if (send_mail([$to], $mail[1], $mail[2])) {
                    nurture_mark((int) $t['id'], $mail[0]);
                    audit('Trial email sent', $t['name'], true, $mail[0] . ' to ' . $to);
                    $log[] = $t['slug'] . ': trial email "' . $mail[0] . '"';
                }
            }
            // To you
            $alert = null;
            if ($left === 1 && !nurture_sent((int) $t['id'], 'alert-ending')) {
                $alert = ['alert-ending', $t['name'] . ': trial ends tomorrow', 'The trial ends tomorrow. A quick call often turns it into a sale.'];
            } elseif ($daysIn >= 2 && $a['checkins'] === 0 && !nurture_sent((int) $t['id'], 'alert-nocheckin')) {
                $alert = ['alert-nocheckin', $t['name'] . ': no check-ins after 2 days', 'Nobody has used the kiosk yet. Offer to set it up with them.'];
            }
            if ($alert && $provider !== '') {
                $html = nurture_html($alert[1], '<p>' . e($alert[2]) . '</p><table style="border-collapse:collapse">'
                    . '<tr><td style="padding:4px 12px 4px 0;color:#6b7090">Owner</td><td>' . e(($a['owner']['name'] ?? '') . ' <' . ($a['owner']['email'] ?? '') . '>') . '</td></tr>'
                    . '<tr><td style="padding:4px 12px 4px 0;color:#6b7090">Portal</td><td><a href="' . e(tenant_url($t, '/')) . '">' . e(tenant_url($t, '/')) . '</a></td></tr>'
                    . '<tr><td style="padding:4px 12px 4px 0;color:#6b7090">People added</td><td>' . $a['people'] . '</td></tr>'
                    . '<tr><td style="padding:4px 12px 4px 0;color:#6b7090">Check-ins</td><td>' . $a['checkins'] . '</td></tr>'
                    . '<tr><td style="padding:4px 12px 4px 0;color:#6b7090">Plan</td><td>' . e(plans()[$t['plan']]['name'] ?? $t['plan']) . (!empty($t['source']) ? ' · came from ' . e($t['source']) : '') . '</td></tr></table>');
                if (send_mail([$provider], $alert[1], $html, [], 'contact', alert_copies())) {
                    nurture_mark((int) $t['id'], $alert[0]);
                    $log[] = $t['slug'] . ': alert "' . $alert[0] . '"';
                }
            }
        } catch (Throwable $e) {
            $log[] = $t['slug'] . ': trial follow-up error ' . $e->getMessage();
        }
    }
    return $log;
}

/** Monday from 7:00 (server time): last week's numbers to the provider, once per week. */
function send_scorecard(bool $force = false): array
{
    if (setting('nurture_on', '1') !== '1' || (!$force && (date('N') !== '1' || (int) date('G') < 7))) {
        return [];
    }
    $kind = 'scorecard-' . date('o-W');
    if (!$force && nurture_sent(0, $kind)) {
        return [];
    }
    $to = (string) (cfg('contact_email') ?: (cfg('super_admin')['email'] ?? ''));
    if ($to === '') {
        return [];
    }
    $since = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
    $plans = plans();
    $new = rows('SELECT * FROM tenants WHERE created_at >= ? AND deleted_at IS NULL AND slug <> ? ORDER BY created_at', [$since, DEMO_SLUG]);
    $trials = rows("SELECT * FROM tenants WHERE status = 'trial' AND deleted_at IS NULL AND slug <> ? ORDER BY trial_ends_at", [DEMO_SLUG]);
    $paid = row("SELECT COUNT(*) AS n, COALESCE(SUM(amount_cents),0) AS c FROM payments WHERE status = 'paid' AND paid_at >= ?", [$since]);
    $leads = rows('SELECT COALESCE(source, ?) AS s, COUNT(*) AS n FROM leads WHERE created_at >= ? GROUP BY s ORDER BY n DESC', ['direct', $since]);
    $mrr = 0.0;
    $paying = 0;
    foreach (rows("SELECT plan, billing_cycle FROM tenants WHERE status = 'active' AND billing_provider IN ('stripe','wave','manual') AND slug <> ?", [DEMO_SLUG]) as $x) {
        $paying++;
        $mrr += ($x['billing_cycle'] ?? '') === 'annual' ? ($plans[$x['plan']]['annual'] ?? 0) / 12 : ($plans[$x['plan']]['amount'] ?? 0);
    }
    $sources = [];
    foreach ($new as $t) {
        $s = $t['source'] ?: 'direct';
        $sources[$s] = ($sources[$s] ?? 0) + 1;
    }
    arsort($sources);
    $cell = 'style="padding:6px 10px;border-bottom:1px solid #E2E4EF"';
    $big = fn($label, $value) => '<td style="padding:10px 14px;background:#F4F5FA;border-radius:10px"><div style="color:#6b7090;font-size:12px">' . $label . '</div><div style="font-size:22px;font-weight:bold">' . $value . '</div></td>';
    $html = '<div style="font-family:Arial,sans-serif;max-width:640px;color:#161B36"><h2 style="margin:0 0 4px">' . e(cfg('app_name')) . ' weekly scorecard</h2>'
        . '<p style="color:#6b7090;margin:0 0 14px">' . date('M j', strtotime('-7 days')) . ' – ' . date('M j, Y') . '</p>'
        . '<table style="border-spacing:8px"><tr>' . $big('New sign-ups', count($new)) . $big('New payments', (int) $paid['n'] . ' · ' . money((int) $paid['c'])) . $big('Paying customers', $paying) . $big('MRR', '$' . number_format($mrr, 0)) . '</tr></table>'
        . '<h3>Where new sign-ups came from</h3><p>' . ($sources ? implode(' · ', array_map(fn($k, $v) => e($k) . ': ' . $v, array_keys($sources), $sources)) : 'No new sign-ups.') . '</p>'
        . '<h3>Demo requests</h3><p>' . ($leads ? implode(' · ', array_map(fn($l) => e($l['s']) . ': ' . $l['n'], $leads)) : 'None this week.') . '</p>'
        . '<h3>Outreach to prospects</h3><p>' . prospects_week_summary() . '</p>'
        . '<h3>Trials in progress</h3>';
    if ($trials) {
        $html .= '<table style="border-collapse:collapse;font-size:14px"><tr><th align="left" ' . $cell . '>Portal</th><th align="left" ' . $cell . '>Days left</th><th align="left" ' . $cell . '>People added</th><th align="left" ' . $cell . '>Check-ins</th></tr>';
        foreach ($trials as $t) {
            try {
                $a = trial_activity($t);
            } catch (Throwable $e) {
                $a = ['people' => '?', 'checkins' => '?'];
            }
            $left = trial_days_left($t);
            $html .= '<tr><td ' . $cell . '><a href="' . e(tenant_url($t, '/')) . '">' . e($t['name']) . '</a></td><td ' . $cell . '>' . ($left > 0 ? $left : 'ended') . '</td><td ' . $cell . '>' . $a['people'] . '</td><td ' . $cell
                . ($a['checkins'] === 0 ? ' style="color:#C62828;font-weight:bold"' : '') . '>' . $a['checkins'] . '</td></tr>';
        }
        $html .= '</table><p style="color:#6b7090;font-size:13px">Red = no check-ins yet: call them first.</p>';
    } else {
        $html .= '<p>No trials in progress.</p>';
    }
    $html .= '</div>';
    if (!send_mail([$to], cfg('app_name') . ' weekly scorecard: ' . count($new) . ' new, $' . number_format($mrr, 0) . ' MRR', $html, [], 'contact', alert_copies())) {
        return ['scorecard: email could not be sent'];
    }
    nurture_mark(0, $kind);
    return ['scorecard sent to ' . $to];
}
