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
    require_login();
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
        update('students', $data, 'id = ? AND tenant_id = ?', [$id, tid()]);
        flash('success', 'Student saved.');
    } else {
        $id = insert('students', $data + ['tenant_id' => tid()]);
        foreach ([1, 2] as $i) { // quick-add up to two guardians with the student
            $gName = mb_substr((string) input("g{$i}_name"), 0, 120);
            if ($gName !== '') {
                insert('guardians', ['tenant_id' => tid(), 'student_id' => $id, 'name' => $gName, 'relationship' => mb_substr((string) input("g{$i}_rel"), 0, 40) ?: null, 'phone' => mb_substr((string) input("g{$i}_phone"), 0, 40) ?: null]);
            }
        }
        flash('success', 'Student added.');
    }
    redirect(url("/admin/students/$id"));
}

function admin_student_delete(int $id): void
{
    require_admin();
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
            'phone' => mb_substr((string) input('phone'), 0, 40) ?: null,
            'email' => mb_substr((string) input('email'), 0, 190) ?: null,
        ]);
        flash('success', 'Guardian added.');
    }
    redirect(url("/admin/students/$id"));
}

function admin_guardian_delete(int $id): void
{
    require_admin();
    $g = row('SELECT * FROM guardians WHERE id = ? AND tenant_id = ?', [$id, tid()]) ?? not_found();
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
    if ($data['first_name'] === '') {
        flash('error', 'First name is required.');
    } elseif ($data['employee_code'] && val('SELECT id FROM teachers WHERE tenant_id = ? AND employee_code = ? AND id <> ?', [tid(), $data['employee_code'], $id])) {
        flash('error', 'Another teacher already uses that ID.');
    } elseif ($id) {
        update('teachers', $data, 'id = ? AND tenant_id = ?', [$id, tid()]);
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
            flash('success', 'Item added.');
        }
        if ($toggle = (int) input('toggle')) {
            q('UPDATE materials SET active = 1 - active WHERE id = ? AND tenant_id = ?', [$toggle, tid()]);
        }
        redirect(url('/admin/materials'));
    }
    $materials = rows('SELECT * FROM materials WHERE tenant_id = ? ORDER BY sort_order, name', [tid()]);
    admin_view('materials', compact('materials'));
}

function admin_material_delete(int $id): void
{
    require_admin();
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
    require_login();
    [$f, $where, $p] = log_filters();
    $page = max(1, (int) input('page', '1'));
    $total = (int) val("SELECT COUNT(*) FROM attendance WHERE $where", $p);
    $logs = rows("SELECT * FROM attendance WHERE $where ORDER BY event_date DESC, event_time DESC, id DESC LIMIT 100 OFFSET " . (($page - 1) * 100), $p);
    admin_view('logs', compact('f', 'logs', 'total', 'page'));
}

function admin_logs_export(): void
{
    require_login();
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
    q('DELETE FROM attendance WHERE id = ? AND tenant_id = ?', [$id, tid()]);
    flash('success', 'Entry deleted.');
    redirect($_SERVER['HTTP_REFERER'] ?? url('/admin/logs'));
}

// ---------- Reports ----------
function admin_reports(): void
{
    require_login();
    $period = in_array(input('period'), ['daily', 'weekly', 'monthly'], true) ? (string) input('period') : 'daily';
    $date = valid_date((string) input('date')) ? (string) input('date') : tenant_today();
    [$from, $to, $label] = report_period($period, $date);
    $r = report_build(tid(), $from, $to);
    if (input('export') === 'csv') {
        $out = [['Date', 'Time', 'Type', 'Name', 'Action', 'Guardian', 'Materials']];
        foreach ($r['events'] as $ev) {
            $out[] = [$ev['event_date'], substr($ev['event_time'], 0, 5), ucfirst($ev['person_type']), $ev['person_name'], kind_label($ev['kind']), $ev['guardian_name'], $ev['materials']];
        }
        csv_download("report-$period-$from.csv", $out);
    }
    $sent = rows('SELECT * FROM report_log WHERE tenant_id = ? ORDER BY sent_at DESC LIMIT 8', [tid()]);
    admin_view('reports', compact('period', 'date', 'from', 'to', 'label', 'r', 'sent'));
}

function admin_report_settings(): void
{
    require_admin();
    $emails = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) input('report_emails'))), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
    update('tenants', [
        'report_emails'  => implode(',', $emails) ?: null,
        'report_daily'   => input('report_daily') === '1' ? 1 : 0,
        'report_weekly'  => input('report_weekly') === '1' ? 1 : 0,
        'report_monthly' => input('report_monthly') === '1' ? 1 : 0,
    ], 'id = ?', [tid()]);
    flash('success', 'Report email settings saved.');
    redirect(url('/admin/reports'));
}

function admin_report_send(): void
{
    require_admin();
    $period = in_array(input('period'), ['daily', 'weekly', 'monthly'], true) ? (string) input('period') : 'daily';
    $date = valid_date((string) input('date')) ? (string) input('date') : tenant_today();
    [$from, $to, $label] = report_period($period, $date);
    $t = tenant();
    $title = ucfirst($period) . ' report — ' . $label;
    $ok = send_mail(explode(',', (string) $t['report_emails']), $t['name'] . ': ' . $title, report_email_html($t, $title, report_build(tid(), $from, $to), tenant_url($t, "/admin/reports?period=$period&date=$from")));
    flash($ok ? 'success' : 'error', $ok ? 'Report emailed to ' . $t['report_emails'] . '.' : 'Email could not be sent. Check the report emails and the mail settings in app/config.php.');
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
            'timezone'        => in_array(input('timezone'), DateTimeZone::listIdentifiers(), true) ? (string) input('timezone') : $t['timezone'],
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
        flash('success', 'Settings saved.');
        redirect(url('/admin/settings'));
    }
    admin_view('settings', ['t' => $t]);
}

// ---------- Users ----------
function admin_users(): void
{
    require_login(); // staff see only the change-password form
    $users = is_admin() ? rows('SELECT id, name, email, role, last_login_at FROM users WHERE tenant_id = ? ORDER BY role, name', [tid()]) : [];
    admin_view('users', compact('users'));
}

function admin_user_save(): void
{
    require_admin();
    $email = strtolower((string) input('email'));
    $role = in_array(input('role'), ['admin', 'staff'], true) ? (string) input('role') : 'staff';
    $pass = (string) ($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || input('name') === '' || strlen($pass) < 8) {
        flash('error', 'Name, a valid email and a password of 8+ characters are required.');
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
    if (!$u || $u['role'] === 'owner' || $u['id'] == $me['id']) {
        flash('error', 'That user cannot be removed.');
    } else {
        q('DELETE FROM users WHERE id = ?', [$id]);
        flash('success', 'User removed.');
    }
    redirect(url('/admin/users'));
}

function admin_password(): void
{
    $me = require_login();
    $u = row('SELECT * FROM users WHERE id = ?', [$me['id']]);
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $u['password_hash'])) {
        flash('error', 'Current password is not correct.');
    } elseif (strlen($new) < 8) {
        flash('error', 'New password must be at least 8 characters.');
    } else {
        update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$me['id']]);
        flash('success', 'Password changed.');
    }
    redirect(url('/admin/users'));
}
