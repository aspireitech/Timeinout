<?php
// Daily / weekly / monthly summaries, used by the Reports page and the
// scheduled email job.

/** @return array{0:string,1:string,2:string} [from, to, label] */
function report_period(string $period, string $anchor): array
{
    $d = new DateTime($anchor);
    switch ($period) {
        case 'weekly':
            $from = (clone $d)->modify('monday this week');
            $to = (clone $from)->modify('+6 days');
            return [$from->format('Y-m-d'), $to->format('Y-m-d'), 'Week of ' . $from->format('M j, Y')];
        case 'monthly':
            $from = (clone $d)->modify('first day of this month');
            $to = (clone $d)->modify('last day of this month');
            return [$from->format('Y-m-d'), $to->format('Y-m-d'), $from->format('F Y')];
        default:
            return [$d->format('Y-m-d'), $d->format('Y-m-d'), $d->format('l, M j, Y')];
    }
}

/** The most recent *complete* period before $today (what the email job sends). */
function previous_period_anchor(string $period, string $today): string
{
    $d = new DateTime($today);
    if ($period === 'weekly') {
        return $d->modify('monday last week')->format('Y-m-d');
    }
    if ($period === 'monthly') {
        return $d->modify('first day of last month')->format('Y-m-d');
    }
    return $d->modify('-1 day')->format('Y-m-d');
}

function report_build(int $tenantId, string $from, string $to): array
{
    $events = rows(
        'SELECT * FROM attendance WHERE tenant_id = ? AND event_date BETWEEN ? AND ? ORDER BY event_date, event_time, id',
        [$tenantId, $from, $to]
    );

    $r = [
        'from' => $from, 'to' => $to,
        'totals' => ['student_in' => 0, 'student_out' => 0, 'teacher_in' => 0, 'teacher_out' => 0, 'pickups' => 0],
        'unique_students' => 0, 'unique_teachers' => 0,
        'by_day' => [], 'teacher_minutes' => [], 'not_signed_out' => [], 'pickups' => [], 'events' => $events,
    ];
    for ($d = new DateTime($from); $d->format('Y-m-d') <= $to; $d->modify('+1 day')) {
        $r['by_day'][$d->format('Y-m-d')] = ['student_in' => 0, 'student_out' => 0, 'teacher_in' => 0, 'pickups' => 0];
    }

    $students = $teachers = [];
    $open = [];      // "type:id:date" => last sign_in event while still on-site
    $teacherIn = []; // "id:date" => time signed in
    foreach ($events as $ev) {
        $day = $ev['event_date'];
        $key = $ev['person_type'] . ':' . $ev['person_id'] . ':' . $day;
        if ($ev['kind'] === 'material_pickup') {
            $r['totals']['pickups']++;
            $r['by_day'][$day]['pickups']++;
            $r['pickups'][] = $ev;
            continue;
        }
        $isIn = $ev['kind'] === 'sign_in';
        if ($ev['person_type'] === 'student') {
            $students[$ev['person_id']] = true;
            $r['totals'][$isIn ? 'student_in' : 'student_out']++;
            $r['by_day'][$day][$isIn ? 'student_in' : 'student_out']++;
        } else {
            $teachers[$ev['person_id']] = true;
            $r['totals'][$isIn ? 'teacher_in' : 'teacher_out']++;
            if ($isIn) {
                $r['by_day'][$day]['teacher_in']++;
            }
            $tk = $ev['person_id'] . ':' . $day;
            if ($isIn) {
                $teacherIn[$tk] = $ev['event_time'];
            } elseif (isset($teacherIn[$tk])) {
                $mins = (int) round((strtotime($ev['event_time']) - strtotime($teacherIn[$tk])) / 60);
                $r['teacher_minutes'][$ev['person_name']] = ($r['teacher_minutes'][$ev['person_name']] ?? 0) + max(0, $mins);
                unset($teacherIn[$tk]);
            }
        }
        if ($isIn) {
            $open[$key] = $ev;
        } else {
            unset($open[$key]);
        }
    }
    $r['unique_students'] = count($students);
    $r['unique_teachers'] = count($teachers);
    $r['not_signed_out'] = array_values($open);
    arsort($r['teacher_minutes']);
    return $r;
}

function fmt_minutes(int $m): string
{
    return intdiv($m, 60) . 'h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . 'm';
}

/** Self-contained HTML email (inline styles only — email clients ignore <style>). */
function report_email_html(array $t, string $title, array $r, string $portalUrl): string
{
    $c = hex_color($t['primary_color'] ?? '', '#6C5CE7');
    $tile = function (string $label, $value, string $color): string {
        return '<td style="padding:6px"><div style="background:' . $color . ';border-radius:12px;padding:14px;color:#fff;font-family:Arial,sans-serif">'
            . '<div style="font-size:26px;font-weight:bold">' . e($value) . '</div><div style="font-size:12px;opacity:.9">' . e($label) . '</div></div></td>';
    };
    $h = '<div style="background:#f4f5fb;padding:24px;font-family:Arial,sans-serif;color:#1f2340">'
        . '<div style="max-width:640px;margin:auto;background:#fff;border-radius:16px;overflow:hidden">'
        . '<div style="background:' . $c . ';color:#fff;padding:20px 24px"><div style="font-size:13px;opacity:.85">' . e($t['name']) . '</div>'
        . '<div style="font-size:22px;font-weight:bold">' . e($title) . '</div></div><div style="padding:18px">'
        . '<table width="100%" cellspacing="0" cellpadding="0"><tr>'
        . $tile('Student sign-ins', $r['totals']['student_in'], '#6C5CE7')
        . $tile('Student sign-outs', $r['totals']['student_out'], '#0984E3')
        . $tile('Teacher sign-ins', $r['totals']['teacher_in'], '#00B894')
        . $tile('Material pickups', $r['totals']['pickups'], '#E17055')
        . '</tr></table>'
        . '<p style="margin:14px 6px">Unique students: <b>' . $r['unique_students'] . '</b> &nbsp;·&nbsp; Unique teachers: <b>' . $r['unique_teachers'] . '</b></p>';

    if (count($r['by_day']) > 1) {
        $h .= '<h3 style="margin:18px 6px 6px">By day</h3><table width="100%" cellpadding="6" style="border-collapse:collapse;font-size:13px">'
            . '<tr style="background:#f0f1f8"><th align="left">Day</th><th>Students in</th><th>Students out</th><th>Teachers in</th><th>Pickups</th></tr>';
        foreach ($r['by_day'] as $day => $v) {
            $h .= '<tr style="border-bottom:1px solid #eee"><td>' . e(date('D M j', strtotime($day))) . '</td><td align="center">' . $v['student_in']
                . '</td><td align="center">' . $v['student_out'] . '</td><td align="center">' . $v['teacher_in'] . '</td><td align="center">' . $v['pickups'] . '</td></tr>';
        }
        $h .= '</table>';
    }
    if ($r['teacher_minutes']) {
        $h .= '<h3 style="margin:18px 6px 6px">Teacher hours</h3><table width="100%" cellpadding="6" style="font-size:13px">';
        foreach ($r['teacher_minutes'] as $name => $m) {
            $h .= '<tr style="border-bottom:1px solid #eee"><td>' . e($name) . '</td><td align="right">' . fmt_minutes($m) . '</td></tr>';
        }
        $h .= '</table>';
    }
    if ($r['not_signed_out']) {
        $h .= '<h3 style="margin:18px 6px 6px;color:#d63031">Signed in but never signed out (' . count($r['not_signed_out']) . ')</h3><ul style="font-size:13px">';
        foreach (array_slice($r['not_signed_out'], 0, 50) as $ev) {
            $h .= '<li>' . e($ev['person_name']) . ' — ' . e(date('M j', strtotime($ev['event_date']))) . ' in at ' . e(fmt_time($ev['event_time'])) . '</li>';
        }
        $h .= '</ul>';
    }
    $h .= '<p style="margin:22px 6px 6px"><a href="' . e($portalUrl) . '" style="background:' . $c . ';color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">Open full report</a></p>'
        . '</div></div><p style="text-align:center;font-size:11px;color:#888">Sent by ' . e(cfg('app_name')) . '. Change report emails in Admin → Reports.</p></div>';
    return $h;
}

/**
 * Called by cron (hourly or daily). Sends each enabled report once, for the
 * last complete day / week / month, after report_hour in the tenant's timezone.
 * @return string[] log lines
 */
function run_scheduled_reports(bool $force = false): array
{
    $log = [];
    $tenants = rows("SELECT * FROM tenants WHERE status IN ('trial','active') AND report_emails IS NOT NULL AND report_emails <> ''");
    foreach ($tenants as $t) {
        $GLOBALS['tenant'] = $t;
        $now = tenant_now();
        if (!$force && (int) $now->format('G') < (int) cfg('report_hour', 6)) {
            continue;
        }
        $today = $now->format('Y-m-d');
        $due = [];
        if ($t['report_daily']) {
            $due[] = 'daily';
        }
        if ($t['report_weekly'] && $now->format('N') === '1') {
            $due[] = 'weekly';
        }
        if ($t['report_monthly'] && $now->format('j') === '1') {
            $due[] = 'monthly';
        }
        foreach ($due as $period) {
            $anchor = previous_period_anchor($period, $today);
            [$from, $to, $label] = report_period($period, $anchor);
            if (val('SELECT 1 FROM report_log WHERE tenant_id = ? AND period = ? AND period_start = ?', [$t['id'], $period, $from])) {
                continue;
            }
            $r = report_build((int) $t['id'], $from, $to);
            $title = ucfirst($period) . ' report — ' . $label;
            $link = tenant_url($t, '/admin/reports?period=' . $period . '&date=' . $from);
            $ok = send_mail(explode(',', $t['report_emails']), $t['name'] . ': ' . $title, report_email_html($t, $title, $r, $link));
            if ($ok) {
                q('INSERT IGNORE INTO report_log (tenant_id, period, period_start) VALUES (?,?,?)', [$t['id'], $period, $from]);
            }
            $log[] = sprintf('%s %s %s → %s', $t['slug'], $period, $from, $ok ? 'sent' : 'FAILED');
        }
    }
    return $log;
}
