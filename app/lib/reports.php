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
        return $d->modify('monday this week')->modify('-7 days')->format('Y-m-d'); // last full Mon–Sun week
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
        . $tile(term('a1', $t) . ' sign-ins', $r['totals']['student_in'], '#6C5CE7')
        . $tile(term('a1', $t) . ' sign-outs', $r['totals']['student_out'], '#0984E3')
        . $tile(term('b1', $t) . ' sign-ins', $r['totals']['teacher_in'], '#00B894')
        . $tile('Material pickups', $r['totals']['pickups'], '#E17055')
        . '</tr></table>'
        . '<p style="margin:14px 6px">' . e(term('a2', $t)) . ': <b>' . $r['unique_students'] . '</b> &nbsp;·&nbsp; ' . e(term('b2', $t)) . ': <b>' . $r['unique_teachers'] . '</b></p>';

    if (count($r['by_day']) > 1) {
        $h .= '<h3 style="margin:18px 6px 6px">By day</h3><table width="100%" cellpadding="6" style="border-collapse:collapse;font-size:13px">'
            . '<tr style="background:#f0f1f8"><th align="left">Day</th><th>' . e(term('a2', $t)) . ' in</th><th>' . e(term('a2', $t)) . ' out</th><th>' . e(term('b2', $t)) . ' in</th><th>Pickups</th></tr>';
        foreach ($r['by_day'] as $day => $v) {
            $h .= '<tr style="border-bottom:1px solid #eee"><td>' . e(date('D M j', strtotime($day))) . '</td><td align="center">' . $v['student_in']
                . '</td><td align="center">' . $v['student_out'] . '</td><td align="center">' . $v['teacher_in'] . '</td><td align="center">' . $v['pickups'] . '</td></tr>';
        }
        $h .= '</table>';
    }
    if ($r['teacher_minutes']) {
        $h .= '<h3 style="margin:18px 6px 6px">' . e(term('b1', $t)) . ' hours</h3><table width="100%" cellpadding="6" style="font-size:13px">';
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

// ---------- Daily attendance sheet (also attached to the daily email) ----------
/**
 * One row per visit: who dropped off, when, who picked up, when, and the final status.
 * People with no sign-in that day are listed as absent.
 */
function attendance_sheet(string $type, string $date): array
{
    $events = rows(
        "SELECT * FROM attendance WHERE tenant_id = ? AND person_type = ? AND event_date = ? AND kind IN ('sign_in','sign_out')
         ORDER BY event_time, id",
        [tid(), $type, $date]
    );
    $visits = [];
    $open = []; // person_id => index of their visit still waiting for a sign-out
    foreach ($events as $ev) {
        $pid = (int) $ev['person_id'];
        if ($ev['kind'] === 'sign_in') {
            $visits[] = ['person_id' => $pid, 'name' => $ev['person_name'], 'in_by' => $ev['guardian_name'], 'in' => $ev['event_time'], 'out_by' => null, 'out' => null,
                'host' => preg_replace('/^Visiting: /', '', (string) $ev['note'])];
            $open[$pid] = count($visits) - 1;
        } elseif (isset($open[$pid])) {
            $visits[$open[$pid]]['out_by'] = $ev['guardian_name'];
            $visits[$open[$pid]]['out'] = $ev['event_time'];
            unset($open[$pid]);
        } else { // a sign-out with no matching sign-in (e.g. sign-in entry was deleted)
            $visits[] = ['person_id' => $pid, 'name' => $ev['person_name'], 'in_by' => null, 'in' => null, 'out_by' => $ev['guardian_name'], 'out' => $ev['event_time']];
        }
    }
    $table = $type === 'teacher' ? 'teachers' : 'students';
    $people = $type === 'visitor' ? [] : rows("SELECT id, first_name, last_name, " . ($type === 'student' ? 'grade' : 'NULL AS grade') . " FROM $table WHERE tenant_id = ? AND active = 1 ORDER BY first_name, last_name", [tid()]);
    $grades = array_column($people, 'grade', 'id');
    $seen = [];
    foreach ($visits as &$v) {
        $seen[$v['person_id']] = true;
        $v['grade'] = $type === 'visitor' ? ($v['host'] ?? null) : ($grades[$v['person_id']] ?? null); // visitors: who they visited
        $v['status'] = $v['out'] ? 'out' : 'in';
        $v['minutes'] = ($v['in'] && $v['out']) ? max(0, (int) round((strtotime($v['out']) - strtotime($v['in'])) / 60)) : null;
    }
    unset($v);
    foreach ($people as $p) {
        if (!isset($seen[(int) $p['id']])) {
            $visits[] = ['person_id' => (int) $p['id'], 'name' => full_name($p), 'grade' => $p['grade'], 'in_by' => null, 'in' => null, 'out_by' => null, 'out' => null, 'status' => 'absent', 'minutes' => null];
        }
    }
    return $visits;
}


/** Counts for the attendance sheet's summary. */
function attendance_counts(array $rows): array
{
    $count = ['present' => 0, 'in' => 0, 'out' => 0, 'absent' => 0];
    $present = [];
    foreach ($rows as $r) {
        $count[$r['status']]++;
        if ($r['status'] !== 'absent') {
            $present[$r['person_id']] = true;
        }
    }
    $count['present'] = count($present);
    return $count;
}

function csv_string(array $rows): string
{
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, "\xEF\xBB\xBF"); // so Excel reads UTF-8 names correctly
    foreach ($rows as $r) {
        // Prefix cells that Excel would treat as formulas
        fputcsv($fh, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r), ',', '"', '\\');
    }
    rewind($fh);
    $s = stream_get_contents($fh);
    fclose($fh);
    return $s;
}

function attendance_csv_rows(string $type, array $rows): array
{
    $label = ['in' => 'Not checked out', 'out' => 'Checked out', 'absent' => 'Absent'];
    if ($type === 'visitor') {
        $out = [[term('v1'), 'Company', 'Visiting', 'Sign-in time', 'Sign-out time', 'Time on site', 'Status']];
        foreach ($rows as $r) {
            $out[] = [$r['name'], $r['in_by'], $r['grade'], fmt_time($r['in']), fmt_time($r['out']), $r['minutes'] !== null ? fmt_minutes($r['minutes']) : '', $r['status'] === 'out' ? 'Signed out' : 'Still on site'];
        }
        return $out;
    }
    $out = $type === 'teacher'
        ? [[term('b1'), 'Check-in time', 'Check-out time', 'Hours', 'Status']]
        : [[term('a1'), 'Grade / group', 'Came with', 'Check-in time', 'Left with', 'Check-out time', 'Time on site', 'Status']];
    foreach ($rows as $r) {
        $dur = $r['minutes'] !== null ? fmt_minutes($r['minutes']) : '';
        $out[] = $type === 'teacher'
            ? [$r['name'], fmt_time($r['in']), fmt_time($r['out']), $dur, $label[$r['status']]]
            : [$r['name'], $r['grade'], $r['in_by'], fmt_time($r['in']), $r['out_by'], fmt_time($r['out']), $dur, $label[$r['status']]];
    }
    return $out;
}

function report_csv_rows(array $r): array
{
    $out = [['Date', 'Time', 'Type', 'Name', 'Action', 'Guardian', 'Materials']];
    foreach ($r['events'] as $ev) {
        $out[] = [$ev['event_date'], substr($ev['event_time'], 0, 5), ucfirst($ev['person_type']), $ev['person_name'], kind_label($ev['kind']), $ev['guardian_name'], $ev['materials']];
    }
    return $out;
}

/**
 * Builds and emails one report with the chosen attachments.
 * Daily reports attach the student attendance sheet; weekly/monthly attach the summary.
 * @return array{0:bool,1:?string} [sent?, error]
 */
function report_send(array $t, string $period, string $anchor, string $trigger): array
{
    [$from, $to, $label] = report_period($period, $anchor);
    $r = report_build((int) $t['id'], $from, $to);
    $title = ucfirst($period) . ' report — ' . $label;
    $attach = [];
    $want = array_map('trim', explode(',', (string) ($t['report_attach'] ?? 'pdf,csv')));
    if ($period === 'daily') {
        $sheet = attendance_sheet('student', $from);
        if (in_array('pdf', $want, true)) {
            $attach[] = ["attendance-$from.pdf", 'application/pdf', pdf_attendance($t, 'student', $from, $sheet, attendance_counts($sheet))];
        }
        if (in_array('csv', $want, true)) {
            $attach[] = ["attendance-$from.csv", 'text/csv', csv_string(attendance_csv_rows('student', $sheet))];
        }
    } else {
        if (in_array('pdf', $want, true)) {
            $attach[] = ["$period-report-$from.pdf", 'application/pdf', pdf_report($t, $title, $r)];
        }
        if (in_array('csv', $want, true)) {
            $attach[] = ["$period-activity-$from.csv", 'text/csv', csv_string(report_csv_rows($r))];
        }
    }
    $recipients = array_filter(array_map('trim', explode(',', (string) $t['report_emails'])));
    $link = tenant_url($t, '/admin/reports?period=' . $period . '&date=' . $from);
    $ok = $recipients && send_mail($recipients, $t['name'] . ': ' . $title, report_email_html($t, $title, $r, $link), $attach);
    $error = $recipients ? $GLOBALS['mail_error'] : 'No report email addresses are set.';
    if ($ok && $trigger === 'scheduled') {
        q('INSERT IGNORE INTO report_log (tenant_id, period, period_start) VALUES (?,?,?)', [$t['id'], $period, $from]);
    }
    audit(($trigger === 'scheduled' ? 'Scheduled' : 'Sent') . ' ' . $period . ' report', $label, (bool) $ok,
        ($ok ? 'To ' . implode(', ', $recipients) . ($attach ? ' with ' . implode(', ', array_column($attach, 0)) : '') : $error));
    return [(bool) $ok, $error];
}

/**
 * Called by cron every hour. For each school, once its chosen send hour has passed
 * (school time): daily = yesterday, weekly = last week on the chosen weekday,
 * monthly = last month on the 1st. Each report is sent once.
 * @return string[] log lines
 */
function run_scheduled_reports(bool $force = false): array
{
    $log = [];
    $GLOBALS['audit_system'] = true; // entries show as "Scheduled job"
    $tenants = rows("SELECT * FROM tenants WHERE status IN ('trial','active') AND report_emails IS NOT NULL AND report_emails <> ''");
    foreach ($tenants as $t) {
        $GLOBALS['tenant'] = $t;
        $now = tenant_now();
        if (!$force && (int) $now->format('G') < (int) ($t['report_hour'] ?? 7)) {
            continue;
        }
        $today = $now->format('Y-m-d');
        $due = [];
        if ($t['report_daily']) {
            $due[] = 'daily';
        }
        if ($t['report_weekly'] && (int) $now->format('N') === (int) ($t['report_weekday'] ?? 1)) {
            $due[] = 'weekly';
        }
        if ($t['report_monthly'] && $now->format('j') === '1') {
            $due[] = 'monthly';
        }
        foreach ($due as $period) {
            $anchor = previous_period_anchor($period, $today);
            [$from] = report_period($period, $anchor);
            if (val('SELECT 1 FROM report_log WHERE tenant_id = ? AND period = ? AND period_start = ?', [$t['id'], $period, $from])) {
                continue;
            }
            [$ok, $err] = report_send($t, $period, $anchor, 'scheduled');
            $log[] = sprintf('%s %s %s → %s', $t['slug'], $period, $from, $ok ? 'sent' : 'FAILED: ' . $err);
        }
    }
    unset($GLOBALS['tenant']);
    return $log;
}
