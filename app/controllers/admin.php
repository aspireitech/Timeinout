<?php
// Subscriber admin portal. Every query is scoped with tenant_id = tid().

function admin_view(string $name, array $data = []): void
{
    view('admin/' . $name, $data, 'admin');
}

/** People whose most recent sign-in/out on $date is a sign-in. */
function on_site(string $type, string $date): array
{
    return rows(
        "SELECT a.* FROM attendance a
         JOIN (SELECT person_id, MAX(CONCAT(event_time, LPAD(id, 10, '0'))) AS k
               FROM attendance
               WHERE tenant_id = ? AND person_type = ? AND event_date = ? AND kind IN ('sign_in','sign_out')
               GROUP BY person_id) last
           ON last.person_id = a.person_id AND CONCAT(a.event_time, LPAD(a.id, 10, '0')) = last.k
         WHERE a.tenant_id = ? AND a.person_type = ? AND a.kind = 'sign_in'
         ORDER BY a.event_time",
        [tid(), $type, $date, tid(), $type]
    );
}

function admin_dashboard(): void
{
    require_admin();
    $today = tenant_today();
    $counts = row(
        "SELECT SUM(person_type='student' AND kind='sign_in') AS s_in, SUM(person_type='student' AND kind='sign_out') AS s_out,
                SUM(person_type='teacher' AND kind='sign_in') AS t_in, SUM(kind='material_pickup') AS pickups
         FROM attendance WHERE tenant_id = ? AND event_date = ?",
        [tid(), $today]
    );
    $trend = [];
    $from = tenant_now()->modify('-13 days')->format('Y-m-d');
    foreach (rows("SELECT event_date, COUNT(*) AS n FROM attendance WHERE tenant_id = ? AND event_date BETWEEN ? AND ? AND kind = 'sign_in' AND person_type = 'student' GROUP BY event_date", [tid(), $from, $today]) as $r) {
        $trend[$r['event_date']] = (int) $r['n'];
    }
    $days = [];
    for ($d = new DateTime($from); $d->format('Y-m-d') <= $today; $d->modify('+1 day')) {
        $days[$d->format('Y-m-d')] = $trend[$d->format('Y-m-d')] ?? 0;
    }
    admin_view('dashboard', [
        'today'     => $today,
        'counts'    => array_map('intval', $counts ?: []),
        'students'  => on_site('student', $today),
        'teachers'  => on_site('teacher', $today),
        'recent'    => rows('SELECT * FROM attendance WHERE tenant_id = ? ORDER BY id DESC LIMIT 12', [tid()]),
        'trend'     => $days,
        'totals'    => row('SELECT (SELECT COUNT(*) FROM students WHERE tenant_id = ? AND active = 1) AS students, (SELECT COUNT(*) FROM teachers WHERE tenant_id = ? AND active = 1) AS teachers', [tid(), tid()]),
    ]);
}

// ---------- Students & guardians ----------
function admin_students(): void
{
    require_admin();
    $q = (string) input('q');
    $grade = (string) input('grade');
    $where = 's.tenant_id = ?';
    $p = [tid()];
    if ($q !== '') {
        $where .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name,' ',s.last_name) LIKE ? OR s.student_code = ?)";
        $like = '%' . addcslashes($q, '%_\\') . '%';
        array_push($p, $like, $like, $like, $q);
    }
    if ($grade !== '') {
        $where .= ' AND s.grade = ?';
        $p[] = $grade;
    }
    $students = rows(
        "SELECT s.*, GROUP_CONCAT(CONCAT(g.name, IF(g.relationship IS NULL OR g.relationship = '', '', CONCAT(' (', g.relationship, ')'))) ORDER BY g.id SEPARATOR '||') AS guardian_list
         FROM students s LEFT JOIN guardians g ON g.student_id = s.id
         WHERE $where GROUP BY s.id ORDER BY s.active DESC, s.first_name, s.last_name LIMIT 500",
        $p
    );
    $grades = array_column(rows('SELECT DISTINCT grade FROM students WHERE tenant_id = ? AND grade IS NOT NULL ORDER BY grade', [tid()]), 'grade');
    admin_view('students', compact('students', 'grades', 'q', 'grade'));
}

function admin_student_edit(int $id): void
{
    require_admin();
    $student = row('SELECT * FROM students WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
    $guardians = rows('SELECT * FROM guardians WHERE student_id = ? AND tenant_id = ? ORDER BY id', [$id, tid()]);
    $history = rows("SELECT * FROM attendance WHERE tenant_id = ? AND person_type = 'student' AND person_id = ? ORDER BY event_date DESC, event_time DESC LIMIT 20", [tid(), $id]);
    admin_view('student_edit', compact('student', 'guardians', 'history'));
}

function admin_student_save(int $id = 0): void
{
    require_admin();
    $data = [
        'first_name'   => mb_substr((string) input('first_name'), 0, 80),
        'last_name'    => mb_substr((string) input('last_name'), 0, 80),
        'grade'        => mb_substr((string) input('grade'), 0, 30) ?: null,
        'student_code' => mb_substr((string) input('student_code'), 0, 50) ?: null,
        'active'       => input('active', '1') === '1' ? 1 : 0,
    ];
    if ($data['first_name'] === '') {
        flash('error', 'First name is required.');
        redirect(url($id ? "/admin/students/$id" : '/admin/students'));
    }
    if ($data['student_code'] && val('SELECT id FROM students WHERE tenant_id = ? AND student_code = ? AND id <> ?', [tid(), $data['student_code'], $id])) {
        flash('error', 'Another student already uses that ID.');
        redirect(url($id ? "/admin/students/$id" : '/admin/students'));
    }
    if ($id) {
        $before = row('SELECT * FROM students WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
        update('students', $data, 'id = ? AND tenant_id = ?', [$id, tid()]);
        audit_note(full_name($data) . " (#$id)", audit_diff($before, $data));
        flash('success', 'Student saved.');
    } else {
        $id = insert('students', $data + ['tenant_id' => tid()]);
        audit_note(full_name($data) . " (#$id)", 'Grade: ' . ($data['grade'] ?? '-'));
        foreach ([1, 2] as $i) { // quick-add up to two guardians with the student
            $gName = mb_substr((string) input("g{$i}_name"), 0, 120);
            if ($gName !== '') {
                insert('guardians', ['tenant_id' => tid(), 'student_id' => $id, 'name' => $gName, 'relationship' => mb_substr((string) input("g{$i}_rel"), 0, 40) ?: null, 'phone' => encrypt_pii(mb_substr((string) input("g{$i}_phone"), 0, 40))]);
            }
        }
        flash('success', 'Student added.');
    }
    redirect(url("/admin/students/$id"));
}

function admin_student_delete(int $id): void
{
    require_admin();
    $st = row('SELECT * FROM students WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
    audit_note(full_name($st) . " (#$id)", 'With ' . val('SELECT COUNT(*) FROM guardians WHERE student_id = ?', [$id]) . ' guardian(s)');
    q('DELETE FROM students WHERE id = ? AND tenant_id = ?', [$id, tid()]);
    flash('success', 'Student deleted. Their past check-ins remain in the log.');
    redirect(url('/admin/students'));
}

function admin_guardian_add(int $id): void
{
    require_admin();
    if (!val('SELECT id FROM students WHERE id = ? AND tenant_id = ?', [$id, tid()])) {
        not_found();
    }
    $name = mb_substr((string) input('name'), 0, 120);
    if ($name !== '') {
        insert('guardians', [
            'tenant_id' => tid(), 'student_id' => $id, 'name' => $name,
            'relationship' => mb_substr((string) input('relationship'), 0, 40) ?: null,
            'phone' => encrypt_pii(mb_substr((string) input('phone'), 0, 40)),
            'email' => encrypt_pii(mb_substr((string) input('email'), 0, 190)),
        ]);
        audit_note($name . ' (' . (input('relationship') ?: 'guardian') . ')', 'For ' . full_name(row('SELECT first_name, last_name FROM students WHERE id = ?', [$id])) . " (#$id)");
        flash('success', 'Guardian added.');
    }
    redirect(url("/admin/students/$id"));
}

function admin_guardian_delete(int $id): void
{
    require_admin();
    $g = row('SELECT * FROM guardians WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
    audit_note($g['name'] . ($g['relationship'] ? ' (' . $g['relationship'] . ')' : ''), 'From ' . full_name(row('SELECT first_name, last_name FROM students WHERE id = ?', [$g['student_id']]) ?? ['first_name' => '?', 'last_name' => '']));
    q('DELETE FROM guardians WHERE id = ?', [$id]);
    flash('success', 'Guardian removed.');
    redirect(url('/admin/students/' . $g['student_id']));
}

// ---------- Teachers ----------
function admin_teachers(): void
{
    require_admin();
    $q = (string) input('q');
    $p = [tid()];
    $where = 'tenant_id = ?';
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name,' ',last_name) LIKE ? OR employee_code = ?)";
        array_push($p, $like, $like, $like, $q);
    }
    $teachers = rows("SELECT * FROM teachers WHERE $where ORDER BY active DESC, first_name, last_name LIMIT 500", $p);
    admin_view('teachers', compact('teachers', 'q'));
}

function admin_teacher_edit(int $id): void
{
    require_admin();
    $teacher = row('SELECT * FROM teachers WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
    $history = rows("SELECT * FROM attendance WHERE tenant_id = ? AND person_type = 'teacher' AND person_id = ? ORDER BY event_date DESC, event_time DESC LIMIT 20", [tid(), $id]);
    admin_view('teacher_edit', compact('teacher', 'history'));
}

function admin_teacher_save(int $id = 0): void
{
    require_admin();
    $data = [
        'first_name'    => mb_substr((string) input('first_name'), 0, 80),
        'last_name'     => mb_substr((string) input('last_name'), 0, 80),
        'employee_code' => mb_substr((string) input('employee_code'), 0, 50) ?: null,
        'email'         => mb_substr((string) input('email'), 0, 190) ?: null,
        'phone'         => mb_substr((string) input('phone'), 0, 40) ?: null,
        'active'        => input('active', '1') === '1' ? 1 : 0,
    ];
    audit_note(full_name($data) . ($id ? " (#$id)" : ''));
    $plain = $data;
    $data['email'] = encrypt_pii($data['email']);
    $data['phone'] = encrypt_pii($data['phone']);
    if ($data['first_name'] === '') {
        flash('error', 'First name is required.');
    } elseif ($data['employee_code'] && val('SELECT id FROM teachers WHERE tenant_id = ? AND employee_code = ? AND id <> ?', [tid(), $data['employee_code'], $id])) {
        flash('error', 'Another teacher already uses that ID.');
    } elseif ($id) {
        $before = row('SELECT * FROM teachers WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
        $before['email'] = decrypt_pii($before['email']);
        $before['phone'] = decrypt_pii($before['phone']);
        update('teachers', $data, 'id = ? AND tenant_id = ?', [$id, tid()]);
        audit_note(null, audit_diff($before, $plain));
        flash('success', 'Teacher saved.');
    } else {
        insert('teachers', $data + ['tenant_id' => tid()]);
        flash('success', 'Teacher added.');
    }
    redirect(url('/admin/teachers'));
}

function admin_teacher_delete(int $id): void
{
    require_admin();
    $tc = row('SELECT * FROM teachers WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
    audit_note(full_name($tc) . " (#$id)");
    q('DELETE FROM teachers WHERE id = ? AND tenant_id = ?', [$id, tid()]);
    flash('success', 'Teacher deleted. Their past check-ins remain in the log.');
    redirect(url('/admin/teachers'));
}

// ---------- Materials ----------
function admin_materials(): void
{
    require_admin();
    if (is_post()) {
        $name = mb_substr((string) input('name'), 0, 120);
        if ($name !== '') {
            insert('materials', ['tenant_id' => tid(), 'name' => $name, 'sort_order' => (int) val('SELECT COALESCE(MAX(sort_order),0)+1 FROM materials WHERE tenant_id = ?', [tid()])]);
            audit_note($name, 'Added item');
            flash('success', 'Item added.');
        }
        if ($toggle = (int) input('toggle')) {
            q('UPDATE materials SET active = 1 - active WHERE id = ? AND tenant_id = ?', [$toggle, tid()]);
            $m = row('SELECT name, active FROM materials WHERE id = ? AND tenant_id = ?', [$toggle, tid()]);
            audit_note($m['name'] ?? "#$toggle", ($m['active'] ?? 0) ? 'Shown on kiosk' : 'Hidden from kiosk');
        }
        redirect(url('/admin/materials'));
    }
    $materials = rows('SELECT * FROM materials WHERE tenant_id = ? ORDER BY sort_order, name', [tid()]);
    admin_view('materials', compact('materials'));
}

function admin_material_delete(int $id): void
{
    require_admin();
    audit_note((string) (val('SELECT name FROM materials WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? "#$id"));
    q('DELETE FROM materials WHERE id = ? AND tenant_id = ?', [$id, tid()]);
    flash('success', 'Item removed.');
    redirect(url('/admin/materials'));
}

// ---------- CSV import ----------
function admin_import(): void
{
    require_admin();
    if (is_post()) {
        $type = input('type') === 'teachers' ? 'teachers' : 'students';
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) {
            flash('error', 'Please choose a CSV file under 5 MB.');
            redirect(url('/admin/import'));
        }
        audit_note(ucfirst($type) . ' file: ' . basename((string) ($f['name'] ?? '')));
        $rows = csv_read($f['tmp_name']);
        if (!$rows) {
            flash('error', 'The file looks empty. The first row must be the column headers.');
            redirect(url('/admin/import'));
        }
        db()->beginTransaction();
        $stats = $type === 'teachers' ? import_teachers(tid(), $rows) : import_students(tid(), $rows);
        db()->commit();
        $parts = [];
        foreach ($stats as $k => $v) {
            $parts[] = str_replace('_', ' ', $k) . ': ' . $v;
        }
        audit_note(ucfirst($type) . ' file: ' . basename((string) $f['name']), implode(', ', $parts) . ' (' . count($rows) . ' rows)');
        flash('success', 'Import finished — ' . implode(', ', $parts) . '.');
        redirect(url($type === 'teachers' ? '/admin/teachers' : '/admin/students'));
    }
    admin_view('import');
}

function csv_download(string $filename, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 names correctly
    foreach ($rows as $r) {
        fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r), ',', '"', '\\');
    }
    exit;
}

function admin_sample_students(): void
{
    require_admin();
    csv_download('students-sample.csv', [
        ['student_id', 'first_name', 'last_name', 'grade', 'guardian1_name', 'guardian1_relationship', 'guardian1_phone', 'guardian2_name', 'guardian2_relationship', 'guardian2_phone'],
        ['S101', 'Olivia', 'Martin', 'Grade 1', 'Paul Martin', 'Father', '555-0201', 'Anna Martin', 'Mother', '555-0202'],
        ['S102', 'Lucas', 'Nguyen', 'Grade 3', 'Minh Nguyen', 'Father', '555-0211', 'Lan Nguyen', 'Mother', '555-0212'],
        ['S103', 'Sofia', 'Rossi', 'KG', 'Marco Rossi', 'Father', '555-0221', '', '', ''],
    ]);
}

function admin_sample_teachers(): void
{
    require_admin();
    csv_download('teachers-sample.csv', [
        ['employee_id', 'first_name', 'last_name', 'email', 'phone'],
        ['T101', 'Grace', 'Hopper', 'grace@example.com', '555-0301'],
        ['T102', 'Alan', 'Turing', 'alan@example.com', '555-0302'],
    ]);
}

// ---------- Attendance log ----------
function log_filters(): array
{
    $today = tenant_today();
    $f = [
        'from' => valid_date((string) input('from')) ? (string) input('from') : $today,
        'to'   => valid_date((string) input('to')) ? (string) input('to') : $today,
        'type' => in_array(input('type'), ['student', 'teacher'], true) ? (string) input('type') : '',
        'kind' => in_array(input('kind'), ['sign_in', 'sign_out', 'material_pickup'], true) ? (string) input('kind') : '',
        'q'    => mb_substr((string) input('q'), 0, 60),
    ];
    $where = 'tenant_id = ? AND event_date BETWEEN ? AND ?';
    $p = [tid(), $f['from'], $f['to']];
    if ($f['type']) {
        $where .= ' AND person_type = ?';
        $p[] = $f['type'];
    }
    if ($f['kind']) {
        $where .= ' AND kind = ?';
        $p[] = $f['kind'];
    }
    if ($f['q'] !== '') {
        $where .= ' AND (person_name LIKE ? OR guardian_name LIKE ?)';
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        array_push($p, $like, $like);
    }
    return [$f, $where, $p];
}

function admin_logs(): void
{
    require_admin();
    [$f, $where, $p] = log_filters();
    $page = max(1, (int) input('page', '1'));
    $total = (int) val("SELECT COUNT(*) FROM attendance WHERE $where", $p);
    $logs = rows("SELECT * FROM attendance WHERE $where ORDER BY event_date DESC, event_time DESC, id DESC LIMIT 100 OFFSET " . (($page - 1) * 100), $p);
    admin_view('logs', compact('f', 'logs', 'total', 'page'));
}

function admin_logs_export(): void
{
    require_admin();
    [$f, $where, $p] = log_filters();
    $out = [['Date', 'Time', 'Type', 'Name', 'Action', 'Guardian', 'Materials', 'Note']];
    foreach (rows("SELECT * FROM attendance WHERE $where ORDER BY event_date, event_time, id", $p) as $r) {
        $out[] = [$r['event_date'], substr($r['event_time'], 0, 5), ucfirst($r['person_type']), $r['person_name'], kind_label($r['kind']), $r['guardian_name'], $r['materials'], $r['note']];
    }
    csv_download('attendance-' . $f['from'] . '-to-' . $f['to'] . '.csv', $out);
}

function admin_log_delete(int $id): void
{
    require_admin();
    $ev = row('SELECT * FROM attendance WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
    audit_note($ev['person_name'] . ' — ' . kind_label($ev['kind']), $ev['event_date'] . ' ' . fmt_time($ev['event_time']) . ($ev['guardian_name'] ? ' by ' . $ev['guardian_name'] : ''));
    q('DELETE FROM attendance WHERE id = ? AND tenant_id = ?', [$id, tid()]);
    flash('success', 'Entry deleted.');
    redirect(url('/admin/logs'));
}

// ---------- Reports ----------
function report_params(): array
{
    $period = in_array(input('period'), ['daily', 'weekly', 'monthly'], true) ? (string) input('period') : 'daily';
    $date = valid_date((string) input('date')) ? (string) input('date') : tenant_today();
    return [$period, $date];
}

function admin_reports(): void
{
    require_admin();
    [$period, $date] = report_params();
    [$from, $to, $label] = report_period($period, $date);
    $r = report_build(tid(), $from, $to);
    $sent = rows('SELECT * FROM report_log WHERE tenant_id = ? ORDER BY sent_at DESC LIMIT 8', [tid()]);
    admin_view('reports', compact('period', 'date', 'from', 'to', 'label', 'r', 'sent'));
}

function send_download(string $filename, string $mime, string $data): void
{
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: private, no-store');
    echo $data;
    exit;
}

function admin_report_download(): void
{
    require_admin();
    [$period, $date] = report_params();
    [$from, $to, $label] = report_period($period, $date);
    $r = report_build(tid(), $from, $to);
    $pdf = input('format') === 'pdf';
    audit('Downloaded ' . $period . ' report', $label, true, $pdf ? 'PDF' : 'CSV');
    if ($pdf) {
        send_download("$period-report-$from.pdf", 'application/pdf', pdf_report(tenant(), ucfirst($period) . ' report — ' . $label, $r));
    }
    send_download("$period-activity-$from.csv", 'text/csv; charset=utf-8', csv_string(report_csv_rows($r)));
}

function admin_report_settings(): void
{
    require_admin();
    $t = tenant();
    $emails = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) input('report_emails'))), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
    $attach = array_values(array_intersect(['pdf', 'csv'], (array) ($_POST['attach'] ?? [])));
    $data = [
        'report_emails'  => implode(',', $emails) ?: null,
        'report_daily'   => input('report_daily') === '1' ? 1 : 0,
        'report_weekly'  => input('report_weekly') === '1' ? 1 : 0,
        'report_monthly' => input('report_monthly') === '1' ? 1 : 0,
        'report_hour'    => max(0, min(23, (int) input('report_hour', '7'))),
        'report_weekday' => max(1, min(7, (int) input('report_weekday', '1'))),
        'report_attach'  => implode(',', $attach),
    ];
    update('tenants', $data, 'id = ?', [tid()]);
    audit_note('Report schedule', audit_diff($t, $data));
    flash('success', 'Report schedule saved.');
    redirect(url('/admin/reports'));
}

function admin_report_send(): void
{
    require_admin();
    audit_skip(); // report_send() writes its own entry with recipients and attachments
    [$period, $date] = report_params();
    $t = tenant();
    if (input('to') === 'me') {
        $t['report_emails'] = current_user()['email'];
    }
    [$ok, $err] = report_send($t, $period, $date, 'manual');
    flash($ok ? 'success' : 'error', $ok ? 'Report emailed to ' . str_replace(',', ', ', (string) $t['report_emails']) . '.' : 'The report was not sent: ' . $err);
    redirect(url("/admin/reports?period=$period&date=$date"));
}

// ---------- Branding & settings ----------
function admin_settings(): void
{
    require_admin();
    $t = tenant();
    if (is_post()) {
        $data = [
            'name'            => mb_substr((string) input('name'), 0, 120) ?: $t['name'],
            'welcome_title'   => mb_substr((string) input('welcome_title'), 0, 150) ?: null,
            'welcome_text'    => mb_substr((string) input('welcome_text'), 0, 500) ?: null,
            'primary_color'   => hex_color((string) input('primary_color'), $t['primary_color']),
            'accent_color'    => hex_color((string) input('accent_color'), $t['accent_color']),
            'timezone'        => valid_tz((string) input('timezone')) ? (string) input('timezone') : '',
            'kiosk_public'    => input('kiosk_public') === '1' ? 1 : 0,
            'allow_past_days' => max(0, min(60, (int) input('allow_past_days'))),
        ];
        $domain = strtolower(trim((string) input('custom_domain')));
        $domain = preg_replace('#^https?://#', '', rtrim($domain, '/'));
        if ($domain === '') {
            $data['custom_domain'] = null;
        } elseif (!preg_match('/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $domain)
            || str_ends_with($domain, '.' . cfg('base_domain')) || $domain === cfg('base_domain')) {
            flash('error', 'Custom domain must look like checkin.yourschool.org.');
        } elseif (val('SELECT id FROM tenants WHERE custom_domain = ? AND id <> ?', [$domain, tid()])) {
            flash('error', 'That custom domain is already used by another portal.');
        } else {
            $data['custom_domain'] = $domain;
        }

        if (input('remove_logo') === '1') {
            $data['logo_path'] = null;
        }
        $f = $_FILES['logo'] ?? null;
        if ($f && $f['error'] === UPLOAD_ERR_OK) {
            $types = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if ($f['size'] > 2 * 1024 * 1024 || !isset($types[$mime]) || !getimagesize($f['tmp_name'])) {
                flash('error', 'Logo must be a PNG, JPG, WEBP or GIF under 2 MB.');
            } else {
                $rel = 'uploads/logos/' . tid() . '-' . bin2hex(random_bytes(6)) . '.' . $types[$mime];
                if (move_uploaded_file($f['tmp_name'], PUBLIC_DIR . '/' . $rel)) {
                    $data['logo_path'] = $rel;
                } else {
                    flash('error', 'Could not save the logo. Make sure public/uploads/logos is writable.');
                }
            }
        }
        update('tenants', $data, 'id = ?', [tid()]);
        audit_note('Branding & settings', audit_diff($t, $data));
        flash('success', 'Settings saved.');
        redirect(url('/admin/settings'));
    }
    admin_view('settings', ['t' => $t]);
}

// ---------- Users ----------
function admin_users(): void
{
    require_admin();
    $users = rows('SELECT id, name, email, role, last_login_at FROM users WHERE tenant_id = ? ORDER BY role, name', [tid()]);
    admin_view('users', compact('users'));
}

function password_problem(string $pass, string $role): ?string
{
    $min = in_array($role, ['owner', 'admin'], true) ? 10 : 8;
    if (strlen($pass) < $min) {
        return "Use at least $min characters" . ($min === 10 ? ' for admin passwords' : '') . '.';
    }
    if (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        return 'Use letters and at least one number.';
    }
    return null;
}

function admin_user_save(): void
{
    require_admin();
    $email = strtolower((string) input('email'));
    $role = in_array(input('role'), ['admin', 'staff'], true) ? (string) input('role') : 'staff';
    $pass = (string) ($_POST['password'] ?? '');
    audit_note($email . ' (' . $role . ')');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || input('name') === '') {
        flash('error', 'Name and a valid email are required.');
    } elseif ($p = password_problem($pass, $role)) {
        flash('error', $p);
    } elseif (val('SELECT id FROM users WHERE tenant_id = ? AND email = ?', [tid(), $email])) {
        flash('error', 'A user with that email already exists.');
    } else {
        insert('users', ['tenant_id' => tid(), 'name' => mb_substr((string) input('name'), 0, 120), 'email' => $email, 'role' => $role, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT)]);
        flash('success', 'User added. Share the portal link and password with them.');
    }
    redirect(url('/admin/users'));
}

function admin_user_delete(int $id): void
{
    $me = require_admin();
    $u = row('SELECT * FROM users WHERE id = ? AND tenant_id = ?', [$id, tid()]);
    audit_note($u ? $u['email'] . ' (' . $u['role'] . ')' : "#$id");
    if (!$u || $u['role'] === 'owner' || $u['id'] == $me['id']) {
        flash('error', 'That user cannot be removed.');
    } else {
        q('DELETE FROM users WHERE id = ?', [$id]);
        flash('success', 'User removed.');
    }
    redirect(url('/admin/users'));
}

/** Set a new password for a staff member or another admin (never the owner). */
function admin_user_password(int $id): void
{
    $me = require_admin();
    $u = row('SELECT * FROM users WHERE id = ? AND tenant_id = ?', [$id, tid()]);
    audit_note($u ? $u['email'] . ' (' . $u['role'] . ')' : "#$id");
    $pass = (string) ($_POST['password'] ?? '');
    if (!$u || ($u['role'] === 'owner' && $u['id'] != $me['id'])) {
        flash('error', "That user's password cannot be changed here.");
    } elseif ($p = password_problem($pass, $u['role'])) {
        flash('error', $p);
    } else {
        update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$id]);
        flash('success', 'New password set for ' . $u['name'] . '.');
    }
    redirect(url('/admin/users'));
}

function admin_password(): void
{
    $me = require_admin();
    $u = row('SELECT * FROM users WHERE id = ?', [$me['id']]);
    $new = (string) ($_POST['new_password'] ?? '');
    audit_note($me['email']);
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $u['password_hash'])) {
        flash('error', 'Current password is not correct.');
    } elseif ($p = password_problem($new, $u['role'])) {
        flash('error', $p);
    } else {
        update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$me['id']]);
        flash('success', 'Password changed.');
    }
    redirect(url('/admin/users'));
}

// ---------- Daily attendance sheet ----------
function admin_attendance(): void
{
    require_admin();
    $type = in_array(input('type'), ['teacher', 'visitor'], true) ? (string) input('type') : 'student';
    $date = valid_date((string) input('date')) ? (string) input('date') : tenant_today();
    $status = in_array(input('status'), ['in', 'out', 'absent', 'present'], true) ? (string) input('status') : '';
    $q = mb_strtolower(trim((string) input('q')));
    $all = attendance_sheet($type, $date);
    $count = attendance_counts($all);

    $rows = array_values(array_filter($all, function ($r) use ($status, $q) {
        if ($status === 'present' ? $r['status'] === 'absent' : ($status !== '' && $r['status'] !== $status)) {
            return false;
        }
        return $q === '' || str_contains(mb_strtolower($r['name'] . ' ' . $r['in_by'] . ' ' . $r['out_by']), $q);
    }));

    $export = (string) input('export');
    if (in_array($export, ['csv', 'pdf'], true)) {
        audit('Downloaded attendance sheet', term($type === 'teacher' ? 'b2' : ($type === 'visitor' ? 'v2' : 'a2')) . ' ' . $date, true, strtoupper($export));
        if ($export === 'pdf') {
            send_download("attendance-{$type}s-$date.pdf", 'application/pdf', pdf_attendance(tenant(), $type, $date, $rows, $count));
        }
        send_download("attendance-{$type}s-$date.csv", 'text/csv; charset=utf-8', csv_string(attendance_csv_rows($type, $rows)));
    }
    admin_view('attendance', compact('type', 'date', 'status', 'q', 'rows', 'count'));
}

// ---------- Audit log ----------
function audit_filters(?int $tenantId): array
{
    $f = [
        'from'   => valid_date((string) input('from')) ? (string) input('from') : (tenant() ? tenant_now() : new DateTime())->modify('-30 days')->format('Y-m-d'),
        'to'     => valid_date((string) input('to')) ? (string) input('to') : (tenant() ? tenant_today() : gmdate('Y-m-d')),
        'result' => in_array(input('result'), ['success', 'failed'], true) ? (string) input('result') : '',
        'q'      => mb_substr(trim((string) input('q')), 0, 80),
    ];
    // Dates are picked in local time; the log is stored in UTC
    $tz = new DateTimeZone(tenant() ? tenant_tz() : 'UTC');
    $utc = fn(string $local) => (new DateTime($local, $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $where = 'created_at >= ? AND created_at < ?';
    $p = [$utc($f['from'] . ' 00:00:00'), $utc((new DateTime($f['to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00')];
    if ($tenantId !== null) {
        $where .= ' AND tenant_id = ?';
        $p[] = $tenantId;
    }
    if ($f['result']) {
        $where .= ' AND result = ?';
        $p[] = $f['result'];
    }
    if ($f['q'] !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $where .= ' AND (action LIKE ? OR target LIKE ? OR details LIKE ? OR user_email LIKE ? OR user_name LIKE ?)';
        array_push($p, $like, $like, $like, $like, $like);
    }
    return [$f, $where, $p];
}

function audit_csv_rows(array $rows, bool $withSchool = false): array
{
    $tz = tenant() ? tenant_tz() : 'UTC';
    $out = [array_merge(["When ($tz)"], $withSchool ? ['School'] : [], ['User', 'Email', 'Action', 'Target', 'Details', 'Result', 'IP address'])];
    foreach ($rows as $r) {
        $out[] = array_merge([audit_time($r['created_at'], $tz)], $withSchool ? [$r['school'] ?? ''] : [], [$r['user_name'], $r['user_email'], $r['action'], $r['target'], $r['details'], $r['result'], $r['ip']]);
    }
    return $out;
}

function admin_audit(): void
{
    require_admin();
    [$f, $where, $p] = audit_filters(tid());
    if (input('export') === 'csv') {
        send_download('audit-log-' . $f['from'] . '-to-' . $f['to'] . '.csv', 'text/csv; charset=utf-8',
            csv_string(audit_csv_rows(rows("SELECT * FROM audit_log WHERE $where ORDER BY id DESC LIMIT 20000", $p))));
    }
    $page = max(1, (int) input('page', '1'));
    $total = (int) val("SELECT COUNT(*) FROM audit_log WHERE $where", $p);
    $logs = rows("SELECT * FROM audit_log WHERE $where ORDER BY id DESC LIMIT 100 OFFSET " . (($page - 1) * 100), $p);
    admin_view('audit', compact('f', 'logs', 'total', 'page'));
}

// ---------- Kiosk tiles & the words used in the app ----------
function admin_tiles(): void
{
    require_admin();
    admin_view('tiles', ['tiles' => kiosk_tiles(false), 'ind' => industry(tenant()['industry'] ?? 'school')]);
}

function admin_tile_save(): void
{
    require_admin();
    $id = (int) input('id');
    $data = [
        'label'         => mb_substr(trim((string) input('label')), 0, 40),
        'subtitle'      => mb_substr(trim((string) input('subtitle')), 0, 120) ?: null,
        'icon'          => in_array(input('icon'), TILE_ICONS, true) ? (string) input('icon') : 'users',
        'color'         => isset(TILE_COLORS[(string) input('color')]) ? (string) input('color') : 'violet',
        'needs_contact' => input('needs_contact') === '1' ? 1 : 0,
        'active'        => input('active', '1') === '1' ? 1 : 0,
    ];
    if ($data['label'] === '') {
        flash('error', 'Each tile needs a name.');
        redirect(url('/admin/tiles'));
    }
    if ($id) {
        $before = row('SELECT * FROM kiosk_tiles WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
        if (in_array($before['type'], ['staff', 'visitor'], true)) {
            $data['needs_contact'] = 0;
        }
        update('kiosk_tiles', $data, 'id = ? AND tenant_id = ?', [$id, tid()]);
        audit_note($data['label'] . " (#$id)", audit_diff($before, $data));
        flash('success', 'Tile saved.');
    } else {
        $type = array_key_exists((string) input('type'), TILE_TYPES) ? (string) input('type') : 'members';
        if (in_array($type, ['staff', 'visitor'], true)) {
            $data['needs_contact'] = 0;
        }
        $id = insert('kiosk_tiles', $data + ['tenant_id' => tid(), 'type' => $type,
            'sort_order' => (int) val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM kiosk_tiles WHERE tenant_id = ?', [tid()])]);
        audit_note($data['label'] . " (#$id)", 'New ' . TILE_TYPES[$type] . ' tile');
        flash('success', 'Tile added to the kiosk.');
    }
    redirect(url('/admin/tiles'));
}

function admin_tile_move(int $id): void
{
    require_admin();
    $tiles = kiosk_tiles(false);
    $ids = array_map('intval', array_column($tiles, 'id'));
    $i = array_search($id, $ids, true);
    $j = $i === false ? false : $i + (input('dir') === 'up' ? -1 : 1);
    if ($i !== false && isset($ids[$j])) {
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $n => $tileId) {
            q('UPDATE kiosk_tiles SET sort_order = ? WHERE id = ? AND tenant_id = ?', [$n, $tileId, tid()]);
        }
        audit_note($tiles[$i]['label'], 'Moved ' . (input('dir') === 'up' ? 'up' : 'down'));
    }
    redirect(url('/admin/tiles'));
}

function admin_tile_delete(int $id): void
{
    require_admin();
    $tile = row('SELECT * FROM kiosk_tiles WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
    audit_note($tile['label'] . " (#$id)");
    q('DELETE FROM kiosk_tiles WHERE id = ? AND tenant_id = ?', [$id, tid()]);
    flash('success', 'Tile removed. Past entries made with it are kept.');
    redirect(url('/admin/tiles'));
}

function admin_tiles_reset(): void
{
    require_admin();
    $industry = array_key_exists((string) input('industry'), industries()) ? (string) input('industry') : (tenant()['industry'] ?? 'school');
    update('tenants', ['industry' => $industry, 'terms' => null], 'id = ?', [tid()]);
    seed_tiles(tid(), $industry);
    audit_note(industry($industry)['name'], 'Tiles and names reset to the industry defaults');
    flash('success', 'Kiosk tiles and names now match: ' . industry($industry)['name'] . '.');
    redirect(url('/admin/tiles'));
}

function admin_terms_save(): void
{
    require_admin();
    $t = tenant();
    $new = [];
    foreach (['a1', 'a2', 'c1', 'c2', 'b1', 'b2', 'items'] as $k) {
        $v = mb_substr(trim((string) input($k)), 0, 40);
        if ($v !== '' && $v !== term($k)) {
            $new[$k] = $v;
        }
    }
    $old = json_decode((string) ($t['terms'] ?? ''), true) ?: [];
    $merged = array_merge($old, $new);
    update('tenants', ['terms' => $merged ? json_encode($merged) : null], 'id = ?', [tid()]);
    audit_note('Names used in the app', $new ? implode('; ', array_map(fn($k, $v) => "$k → $v", array_keys($new), $new)) : 'No changes');
    flash('success', 'Names saved.');
    redirect(url('/admin/tiles'));
}

// ---------- Billing (subscription for this portal) ----------
function admin_billing(): void
{
    require_admin();
    $payments = rows('SELECT * FROM payments WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 50', [tid()]);
    if (input('paid') === '1') {
        flash('success', 'Thank you! Your payment is being confirmed by Stripe; this page updates within a minute.');
        redirect(url('/admin/billing'));
    }
    admin_view('billing', ['t' => tenant(), 'plans' => plans(), 'payments' => $payments, 'state' => billing_state()]);
}

function admin_billing_stripe(): void
{
    require_admin();
    $plan = (string) input('plan');
    try {
        if (!isset(plans()[$plan])) {
            throw new RuntimeException('Please choose a plan.');
        }
        $url = stripe_checkout_url(tenant(), $plan);
        audit('Started card checkout', plans()[$plan]['name'] . ' plan', true, 'Stripe Checkout');
        redirect($url);
    } catch (Throwable $e) {
        audit('Started card checkout', $plan, false, $e->getMessage());
        flash('error', $e->getMessage());
        redirect(url('/admin/billing'));
    }
}

function admin_billing_portal(): void
{
    require_admin();
    try {
        if (!tenant()['stripe_customer_id']) {
            throw new RuntimeException('There is no card subscription to manage yet.');
        }
        $url = stripe_portal_url(tenant());
        audit('Opened billing portal', 'Stripe', true);
        redirect($url);
    } catch (Throwable $e) {
        audit('Opened billing portal', 'Stripe', false, $e->getMessage());
        flash('error', $e->getMessage());
        redirect(url('/admin/billing'));
    }
}

function admin_billing_wave(): void
{
    require_admin();
    $plan = (string) input('plan');
    try {
        if (!isset(plans()[$plan])) {
            throw new RuntimeException('Please choose a plan.');
        }
        if (tenant()['wave_invoice_id']) {
            throw new RuntimeException('An invoice is already waiting to be paid. Check your email, or see the payments below.');
        }
        wave_send_invoice(tenant(), $plan);
        audit('Requested invoice', plans()[$plan]['name'] . ' plan', true, 'Wave invoice emailed to ' . owner_email(tenant()));
        flash('success', 'Your invoice is on its way to ' . owner_email(tenant()) . '. Your portal activates as soon as it is paid.');
    } catch (Throwable $e) {
        audit('Requested invoice', $plan, false, $e->getMessage());
        flash('error', $e->getMessage());
    }
    redirect(url('/admin/billing'));
}
