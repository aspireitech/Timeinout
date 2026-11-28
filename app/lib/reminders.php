<?php
// Missed sign-out reminders (free, optional: Admin → Features & add-ons → "Missed sign-out reminders").
// The morning after someone signed in but never signed out, at the portal's report hour (7:00 by default):
//   * staff get a short personal email ("please sign out before you leave"),
//   * the parent/guardian who did the drop-off gets one ("please sign out at pickup"), with a link to stop them.
// The admin gets no extra email: the daily report's "Signed in but never signed out" list shows, per person,
// whether a reminder went out, whether no email is on file, and who keeps forgetting.
// One reminder per person per day; never an automatic sign-out time (that would falsify hours and pickups).

const REMINDER_MODES = ['both' => 'Staff and parents', 'staff' => 'Staff only', 'parents' => 'Parents and guardians only'];
const REMINDER_REPEAT = 3; // reminders in 30 days that mark someone as "keeps forgetting" in the daily report

/** The days to look at this morning: yesterday, or with "working days only" Friday–Sunday on Monday and nothing at weekends. */
function reminder_dates(DateTime $now, bool $weekdaysOnly): array
{
    $dow = (int) $now->format('N');
    if ($weekdaysOnly && $dow >= 6) {
        return [];
    }
    $back = $weekdaysOnly && $dow === 1 ? [3, 2, 1] : [1];
    return array_map(fn($n) => (clone $now)->modify("-$n day")->format('Y-m-d'), $back);
}

/** Secret token for a parent's "stop these reminders" link (no login needed, cannot be guessed). */
function reminder_token(int $tenantId, string $type, int $id): string
{
    return substr(hash_hmac('sha256', "reminder-stop|$tenantId|$type|$id", app_key()), 0, 32);
}

/** Where replies go: the portal's first report recipient, else the owner. */
function reminder_reply_to(array $t): ?string
{
    foreach (array_map('trim', explode(',', (string) ($t['report_emails'] ?? ''))) as $a) {
        if (filter_var($a, FILTER_VALIDATE_EMAIL)) {
            return $a;
        }
    }
    $o = owner_email($t);
    return $o && filter_var($o, FILTER_VALIDATE_EMAIL) ? $o : null;
}

/** The email for one open sign-in. Returns [to, subject, html] or [null, reason]. */
function reminder_message(array $t, array $ev, string $mode): array
{
    $day = (new DateTime($ev['event_date']))->format('l, M j');
    $time = fmt_time($ev['event_time']);
    $org = (string) $t['name'];
    $box = fn(string $inner) => '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;color:#161B36;font-size:15px;line-height:1.55">'
        . '<p style="font-size:13px;color:#6B7090;margin:0 0 14px">' . e($org) . '</p>' . $inner
        . '<p style="font-size:11px;color:#A0A4BC;margin-top:18px">' . powered_by_html('reminder') . '</p></div>';
    if ($ev['person_type'] === 'teacher') {
        if ($mode === 'parents') {
            return [null, 'off'];
        }
        $p = row('SELECT first_name, email, active FROM teachers WHERE id = ? AND tenant_id = ?', [$ev['person_id'], $t['id']]);
        $to = $p ? (string) decrypt_pii((string) $p['email']) : '';
        if (!$p || !$p['active'] || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [null, 'no_email'];
        }
        return [$to, '⏰ ' . $p['first_name'] . ', you didn\'t sign out on ' . (new DateTime($ev['event_date']))->format('l'), $box(
            '<p>Hi ' . e($p['first_name']) . ',</p>'
            . '<p>You signed in at <b>' . e($org) . '</b> on <b>' . e($day) . '</b> at <b>' . e($time) . '</b>, but there is no sign-out for that day.</p>'
            . '<p>Please sign out on the kiosk every time you leave: it keeps your hours and the on-site list right.</p>'
            . '<p>If your hours for that day need correcting, just reply to this email and it goes to your administrator.</p>')];
    }
    if ($ev['person_type'] === 'student') {
        if ($mode === 'staff') {
            return [null, 'off'];
        }
        if (val('SELECT 1 FROM reminder_optout WHERE tenant_id = ? AND person_type = ? AND person_id = ?', [$t['id'], 'guardian', (int) $ev['guardian_id']])) {
            return [null, 'opted_out'];
        }
        $g = $ev['guardian_id'] ? row('SELECT id, name, email FROM guardians WHERE id = ? AND tenant_id = ?', [$ev['guardian_id'], $t['id']]) : null;
        $to = $g ? (string) decrypt_pii((string) $g['email']) : '';
        if (!$g || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [null, 'no_email']; // self, authorized relative, or no email on file
        }
        $child = trim(explode(' ', (string) $ev['person_name'])[0]) ?: (string) $ev['person_name'];
        $stop = tenant_url($t, '/reminders/stop?g=' . (int) $g['id'] . '&k=' . reminder_token((int) $t['id'], 'guardian', (int) $g['id']));
        return [$to, $child . ' wasn\'t signed out at pickup on ' . (new DateTime($ev['event_date']))->format('l'), $box(
            '<p>Hello ' . e(explode(' ', (string) $g['name'])[0]) . ',</p>'
            . '<p><b>' . e($ev['person_name']) . '</b> was signed in at <b>' . e($org) . '</b> on <b>' . e($day) . '</b> at <b>' . e($time) . '</b>, but was not signed out at pickup.</p>'
            . '<p>Please sign out on the kiosk each time you collect ' . e($child) . '. It keeps a dated record of who picked up, which protects your child.</p>'
            . '<p>Questions? Reply to this email; it goes to ' . e($org) . '.</p>'
            . '<p style="font-size:12px;color:#8A8FA8;margin-top:22px">Don\'t want these reminders? <a href="' . e($stop) . '" style="color:#8A8FA8">Stop them</a>.</p>')];
    }
    return [null, 'off']; // visitors are not reminded
}

/** Hourly: sends due reminders for every portal that turned the feature on. Returns log lines. */
function run_signout_reminders(): array
{
    $log = [];
    $GLOBALS['audit_system'] = true;
    foreach (rows("SELECT * FROM tenants WHERE status IN ('trial','active') AND deleted_at IS NULL AND features LIKE '%signout_reminders%'") as $t) {
        if (!in_array('signout_reminders', enabled_features($t), true)) {
            continue;
        }
        $GLOBALS['tenant'] = $t;
        try {
            tenant_db_migrate($t);
            use_tenant_db($t);
            $now = tenant_now();
            if ((int) $now->format('G') < (int) ($t['report_hour'] ?? 7)) {
                continue;
            }
            $mode = isset(REMINDER_MODES[$t['signout_mode'] ?? '']) ? $t['signout_mode'] : 'both';
            $replyTo = reminder_reply_to($t);
            $sent = $none = 0;
            foreach (reminder_dates($now, (bool) ($t['signout_weekdays'] ?? 1)) as $date) {
                foreach (report_build((int) $t['id'], $date, $date)['not_signed_out'] as $ev) {
                    if ($ev['person_type'] === 'visitor' || val('SELECT 1 FROM reminder_log WHERE tenant_id = ? AND person_type = ? AND person_id = ? AND event_date = ?',
                            [$t['id'], $ev['person_type'], $ev['person_id'], $date])) {
                        continue;
                    }
                    $msg = reminder_message($t, $ev, $mode);
                    if ($msg[0] === null) {
                        $status = $msg[1];
                        $none += $status === 'no_email' ? 1 : 0;
                    } else {
                        [$to, $subject, $html] = $msg;
                        $ok = send_mail([$to], $subject, $html, [], 'reports', [], $replyTo ? ['Reply-To: ' . $replyTo] : []);
                        $status = $ok ? 'sent' : 'failed';
                        $sent += $ok ? 1 : 0;
                    }
                    q('INSERT IGNORE INTO reminder_log (tenant_id, person_type, person_id, event_date, status, created_at) VALUES (?,?,?,?,?,?)',
                        [$t['id'], $ev['person_type'], $ev['person_id'], $date, $status, gmdate('Y-m-d H:i:s')]);
                }
            }
            if ($sent || $none) {
                audit('Missed sign-out reminders', null, true, "$sent emailed, $none without an email on file");
                $log[] = $t['slug'] . ": sign-out reminders $sent sent" . ($none ? ", $none without email" : '');
            }
        } catch (Throwable $e) {
            $log[] = $t['slug'] . ': sign-out reminders failed: ' . $e->getMessage();
        }
    }
    unset($GLOBALS['tenant']);
    use_tenant_db(null);
    return $log;
}

/** For the daily report: what happened with this open sign-in ("reminder emailed", "no email on file", "keeps forgetting"). */
function reminder_note(int $tenantId, array $ev): string
{
    try {
        $st = val('SELECT status FROM reminder_log WHERE tenant_id = ? AND person_type = ? AND person_id = ? AND event_date = ?',
            [$tenantId, $ev['person_type'], $ev['person_id'], $ev['event_date']]);
        if (!$st) {
            return '';
        }
        $n = (int) val("SELECT COUNT(*) FROM reminder_log WHERE tenant_id = ? AND person_type = ? AND person_id = ? AND created_at >= ? AND status IN ('sent','no_email','opted_out')",
            [$tenantId, $ev['person_type'], $ev['person_id'], gmdate('Y-m-d H:i:s', time() - 30 * 86400)]);
        $what = ['sent' => 'reminder emailed', 'no_email' => 'no email on file, not reminded', 'opted_out' => 'family stopped reminders', 'failed' => 'reminder could not be sent', 'off' => ''][$st] ?? '';
        $repeat = $n >= REMINDER_REPEAT ? ($what !== '' ? '; ' : '') . $n . ' times in 30 days' : '';
        return $what . $repeat;
    } catch (Throwable $e) {
        return ''; // table not there yet during an update
    }
}

/**
 * Optional closing-time check (safety, admin only): when the admin set a closing time, the first hourly run after it
 * emails the report recipients if anyone is still signed in today, with who brought them and a phone number.
 * Nothing is sent when everyone has left. Once per day.
 */
function run_closing_checks(): array
{
    $log = [];
    $GLOBALS['audit_system'] = true;
    foreach (rows("SELECT * FROM tenants WHERE status IN ('trial','active') AND deleted_at IS NULL AND closing_time IS NOT NULL AND report_emails IS NOT NULL AND report_emails <> ''") as $t) {
        if (!in_array('signout_reminders', enabled_features($t), true)) {
            continue; // the closing time is a setting of the missed sign-out reminders feature
        }
        $GLOBALS['tenant'] = $t;
        try {
            tenant_db_migrate($t);
            use_tenant_db($t);
            $now = tenant_now();
            $today = $now->format('Y-m-d');
            if ($now->format('H:i:s') < (string) $t['closing_time']
                || val("SELECT 1 FROM reminder_log WHERE tenant_id = ? AND person_type = 'closing' AND person_id = 0 AND event_date = ?", [$t['id'], $today])) {
                continue;
            }
            $open = report_build((int) $t['id'], $today, $today)['not_signed_out'];
            q("INSERT IGNORE INTO reminder_log (tenant_id, person_type, person_id, event_date, status, created_at) VALUES (?, 'closing', 0, ?, ?, ?)",
                [$t['id'], $today, $open ? 'sent' : 'none', gmdate('Y-m-d H:i:s')]);
            if (!$open) {
                continue;
            }
            $rows = '';
            foreach ($open as $ev) {
                $who = $ev['person_type'] === 'student' ? term('a1', $t) : ($ev['person_type'] === 'teacher' ? term('b1', $t) : 'Visitor');
                $contact = '';
                if ($ev['person_type'] === 'student' && $ev['guardian_id']) {
                    $g = row('SELECT name, phone FROM guardians WHERE id = ? AND tenant_id = ?', [$ev['guardian_id'], $t['id']]);
                    $ph = $g ? (string) decrypt_pii((string) $g['phone']) : '';
                    $contact = $g ? e($g['name']) . ($ph !== '' ? ' · <a href="tel:' . e(preg_replace('/[^0-9+]/', '', $ph)) . '">' . e($ph) . '</a>' : '') : '';
                } elseif ($ev['guardian_name']) {
                    $contact = e((string) $ev['guardian_name']);
                }
                $rows .= '<tr><td style="padding:8px;border-bottom:1px solid #E2E4EF"><b>' . e($ev['person_name']) . '</b><br><span style="color:#6B7090">' . e($who) . '</span></td>'
                    . '<td style="padding:8px;border-bottom:1px solid #E2E4EF">in at ' . e(fmt_time($ev['event_time'])) . '</td>'
                    . '<td style="padding:8px;border-bottom:1px solid #E2E4EF">' . $contact . '</td></tr>';
            }
            $n = count($open);
            $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#161B36">'
                . '<h2 style="margin:0 0 6px">' . $n . ' still signed in after closing</h2>'
                . '<p style="margin:0 0 12px;color:#6B7090">' . e($t['name']) . ' · ' . e($now->format('l, M j, g:i A')) . ' (closing time ' . e(fmt_time((string) $t['closing_time'])) . ')</p>'
                . '<p>Please check that everyone has really left. If they have, sign them out in Admin → Today\'s attendance so tomorrow\'s records are right.</p>'
                . '<table style="border-collapse:collapse;font-size:14px;width:100%">' . $rows . '</table>'
                . '<p style="margin-top:16px"><a href="' . e(tenant_url($t, '/admin/attendance')) . '">Open today\'s attendance</a></p></div>';
            $to = array_values(array_filter(array_map('trim', explode(',', (string) $t['report_emails'])), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
            $ok = $to && send_mail($to, '⚠️ ' . $n . ' still signed in after closing at ' . $t['name'], $html, [], 'reports');
            audit('Closing-time check', null, (bool) $ok, "$n still signed in");
            $log[] = $t['slug'] . ": closing check, $n still signed in, email " . ($ok ? 'sent' : 'FAILED');
        } catch (Throwable $e) {
            $log[] = $t['slug'] . ': closing check failed: ' . $e->getMessage();
        }
    }
    unset($GLOBALS['tenant']);
    use_tenant_db(null);
    return $log;
}
