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
        if (people_room() === 0) {
            audit_skip();
            flash('error', plan_limit_message('people'));
            redirect(url('/admin/students'));
        }
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
    } elseif (people_room() === 0) {
        audit_skip();
        flash('error', plan_limit_message('people'));
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
/** Step 1: upload a CSV or Excel file. It is read and kept briefly, then the columns are matched. */
function admin_import(): void
{
    require_admin();
    if (is_post()) {
        audit_skip(); // the import itself is logged when the columns are confirmed
        $type = input('type') === 'teachers' ? 'teachers' : 'students';
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) {
            flash('error', 'Please choose a CSV or Excel (.xlsx) file under 5 MB.');
            redirect(url('/admin/import?type=' . $type));
        }
        try {
            $table = sheet_table($f['tmp_name'], (string) ($f['name'] ?? ''));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect(url('/admin/import?type=' . $type));
        }
        $table = array_values(array_filter($table, fn($r) => implode('', array_map('strval', $r)) !== ''));
        $headers = array_map(fn($h) => trim((string) $h), $table[0] ?? []);
        $rows = array_slice($table, 1);
        if (!$headers || !array_filter($headers) || !$rows) {
            flash('error', 'The file looks empty. The first row must be the column headers, with people on the rows below.');
            redirect(url('/admin/import?type=' . $type));
        }
        import_pending_clear();
        $token = bin2hex(random_bytes(16));
        file_put_contents(import_pending_file($token), json_encode(['headers' => $headers, 'rows' => $rows]));
        $_SESSION['import'] = ['token' => $token, 'type' => $type, 'name' => basename((string) ($f['name'] ?? 'file')), 'tenant' => tid()];
        redirect(url('/admin/import/map'));
    }
    admin_view('import');
}

function import_pending_file(string $token): string
{
    return APP_DIR . '/sessions/import_' . preg_replace('/[^a-f0-9]/', '', $token) . '.json';
}

/** Removes this person's pending file and any left over for more than a day. */
function import_pending_clear(): void
{
    if (!empty($_SESSION['import']['token'])) {
        @unlink(import_pending_file($_SESSION['import']['token']));
    }
    unset($_SESSION['import']);
    foreach (glob(APP_DIR . '/sessions/import_*.json') ?: [] as $old) {
        if (filemtime($old) < time() - 86400) {
            @unlink($old);
        }
    }
}

/** Step 2: match each column to a field (or keep it as extra info), then import. */
function admin_import_map(): void
{
    require_admin();
    $p = $_SESSION['import'] ?? null;
    $data = $p && (int) $p['tenant'] === tid() && is_file(import_pending_file($p['token'])) ? json_decode((string) file_get_contents(import_pending_file($p['token'])), true) : null;
    if (!$data) {
        audit_skip();
        flash('error', 'Please upload the file again.');
        redirect(url('/admin/import'));
    }
    $type = $p['type'];
    [$headers, $rows] = [$data['headers'], $data['rows']];
    if (!is_post() && in_array(input('as'), ['students', 'teachers'], true)) { // "Import as … instead"
        audit_skip();
        $_SESSION['import']['type'] = (string) input('as');
        redirect(url('/admin/import/map'));
    }
    if (input('cancel') === '1') {
        audit_skip();
        import_pending_clear();
        redirect(url('/admin/import'));
    }
    $map = suggest_mapping($type, $headers, saved_mapping($type));
    $error = null;
    if (is_post()) {
        $fields = import_fields($type);
        foreach ($headers as $i => $h) {
            $field = (string) ($_POST['field'][$i] ?? 'ignore');
            $map[$i] = [isset($fields[$field]) ? $field : 'ignore', mb_substr(trim((string) ($_POST['label'][$i] ?? '')), 0, 40),
                ($_POST['target'][$i] ?? '') === '' ? null : (int) $_POST['target'][$i]];
            if ($map[$i][0] !== 'guardian') {
                $map[$i][1] = '';
            }
            if (!str_starts_with($map[$i][0], 'g_')) {
                $map[$i][2] = null;
            }
        }
        $error = mapping_problem($type, $map);
        if ($error === null) {
            save_mapping($type, $headers, $map);
            $records = mapped_records($type, $headers, $rows, $map);
            db()->beginTransaction();
            $GLOBALS['import_new_ids'] = [];
            $room = people_room();
            $stats = $type === 'teachers' ? import_teacher_records(tid(), $records, $room) : import_student_records(tid(), $records, $room);
            db()->commit();
            $_SESSION['last_import'] = ['tenant' => tid(), 'type' => $type, 'ids' => $GLOBALS['import_new_ids'], 'name' => $p['name'], 'at' => time()];
            import_pending_clear();
            $parts = [];
            foreach ($stats as $k => $v) {
                $parts[] = str_replace('_', ' ', $k) . ': ' . $v;
            }
            $extra = array_values(array_map(fn($i) => $headers[$i], array_keys(array_filter($map, fn($m) => $m[0] === 'extra'))));
            audit_note(ucfirst($type) . ' file: ' . $p['name'], implode(', ', $parts) . ' (' . count($rows) . ' rows)' . ($extra ? '; extra info: ' . implode(', ', $extra) : ''));
            flash(empty($stats['over_plan_limit']) ? 'success' : 'info', 'Import finished — ' . implode(', ', $parts) . '.' . ($extra ? ' Kept as extra info: ' . implode(', ', $extra) . '.' : '')
                . (empty($stats['over_plan_limit']) ? '' : ' ' . plan_limit_message('people')));
            redirect(url($type === 'teachers' ? '/admin/teachers' : '/admin/students'));
        }
        audit_skip();
    }
    $samples = [];
    foreach ($headers as $i => $h) {
        $vals = [];
        foreach ($rows as $r) {
            $x = trim((string) ($r[$i] ?? ''));
            if ($x !== '' && count($vals) < 3) {
                $vals[] = mb_substr($x, 0, 40);
            }
        }
        $samples[$i] = $vals;
    }
    admin_view('import_map', ['type' => $type, 'fileName' => $p['name'], 'headers' => $headers, 'samples' => $samples, 'map' => $map,
        'fields' => import_fields($type), 'count' => count($rows), 'error' => $error, 'guess' => import_type_guess($headers)]);
}

/** The last import in this session, if it added people and is less than a day old. */
function last_import(string $type): ?array
{
    $l = $_SESSION['last_import'] ?? null;
    return $l && (int) $l['tenant'] === tid() && $l['type'] === $type && $l['ids'] && $l['at'] > time() - 86400 ? $l : null;
}

/** Removes the people the last import added (not ones that already have check-ins). Updates to existing people stay. */
function admin_import_undo(): void
{
    require_admin();
    $type = input('type') === 'teachers' ? 'teachers' : 'students';
    $l = last_import($type);
    if (!$l) {
        audit_skip();
        flash('error', 'There is no recent import to undo.');
        redirect(url('/admin/import'));
    }
    $removed = $kept = 0;
    foreach ($l['ids'] as $id) {
        if (val('SELECT id FROM attendance WHERE tenant_id = ? AND person_type = ? AND person_id = ? LIMIT 1', [tid(), $type === 'teachers' ? 'teacher' : 'student', $id])) {
            $kept++;
            continue;
        }
        $removed += q("DELETE FROM $type WHERE id = ? AND tenant_id = ?", [$id, tid()])->rowCount();
    }
    unset($_SESSION['last_import']);
    audit_note(ucfirst($type) . ' file: ' . $l['name'], "removed $removed added by the import" . ($kept ? ", kept $kept who already checked in" : ''));
    flash('success', "Import undone: removed $removed " . ($type === 'teachers' ? strtolower(term('b2')) : strtolower(term('a2'))) . ' it had added.'
        . ($kept ? " $kept already checked in, so they were kept." : ''));
    redirect(url('/admin/' . $type));
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
        ['Student ID', 'First Name', 'Last Name', 'Grade', 'Guardian (Father)', 'Guardian (Mother)', 'Authorized Relative'],
        ['S101', 'Olivia', 'Martin', 'Grade 1', 'Paul Martin', 'Anna Martin', 'Rose Martin (Grandmother)'],
        ['S102', 'Lucas', 'Nguyen', 'Grade 3', 'Minh Nguyen', 'N/A', 'Lan Tran (Aunt); Bao Tran (Uncle)'],
        ['S103', 'Daniel', 'Reyes', 'Adult', 'N/A', 'N/A', 'N/A'],
    ]);
}

function admin_sample_teachers(): void
{
    require_admin();
    csv_download('teachers-sample.csv', [
        ['Employee ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Department'],
        ['T101', 'Grace', 'Hopper', 'grace@example.com', '555-0301', 'Front desk'],
        ['T102', 'Alan', 'Turing', 'alan@example.com', '555-0302', 'Teaching'],
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
/** @return array{0:string,1:string,2:?string} period, date (start), end date for custom ranges */
function report_params(): array
{
    $period = in_array(input('period'), ['daily', 'weekly', 'monthly', 'custom'], true) ? (string) input('period') : 'daily';
    $date = valid_date((string) input('date')) ? (string) input('date') : tenant_today();
    $end = $period === 'custom' ? (valid_date((string) input('to')) ? (string) input('to') : $date) : null;
    if ($period === 'custom') {
        [$date, $end] = report_period('custom', $date, $end); // ordered and capped at 6 months
    }
    return [$period, $date, $end];
}

function admin_reports(): void
{
    require_admin();
    [$period, $date, $end] = report_params();
    [$from, $to, $label] = report_period($period, $date, $end);
    $r = report_build(tid(), $from, $to);
    $sent = rows('SELECT * FROM report_log WHERE tenant_id = ? ORDER BY sent_at DESC LIMIT 8', [tid()]);
    admin_view('reports', compact('period', 'date', 'end', 'from', 'to', 'label', 'r', 'sent'));
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
    [$period, $date, $end] = report_params();
    [$from, $to, $label] = report_period($period, $date, $end);
    $r = report_build(tid(), $from, $to);
    $pdf = input('format') === 'pdf';
    audit('Downloaded ' . $period . ' report', $label, true, $pdf ? 'PDF' : 'CSV');
    if ($pdf) {
        send_download("$period-report-$from.pdf", 'application/pdf', pdf_report(tenant(), ($period === 'custom' ? 'Report' : ucfirst($period) . ' report') . ' — ' . $label, $r));
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
    [$period, $date, $end] = report_params();
    $t = tenant();
    if (input('send_to') === 'me') {
        $t['report_emails'] = current_user()['email'];
    }
    [$ok, $err] = report_send($t, $period, $date, 'manual', $end);
    flash($ok ? 'success' : 'error', $ok ? 'Report emailed to ' . str_replace(',', ', ', (string) $t['report_emails']) . '.' : 'The report was not sent: ' . $err);
    redirect(url("/admin/reports?period=$period&date=$date" . ($end ? "&to=$end" : '')));
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
    $users = rows('SELECT id, name, email, username, role, must_change_password, last_login_at FROM users WHERE tenant_id = ? ORDER BY role, name', [tid()]);
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

/**
 * Add a login. Staff sign in with a user ID (email optional, used for notices); admins need an
 * email for sign-in codes. Leave the password empty to generate one. Optionally the person must
 * choose a new password at first sign-in, and the details are emailed to them.
 */
function admin_user_save(): void
{
    require_admin();
    $name = mb_substr(trim((string) input('name')), 0, 120);
    $email = strtolower(trim((string) input('email')));
    $username = strtolower(trim((string) input('username')));
    $role = in_array(input('role'), ['admin', 'staff'], true) ? (string) input('role') : 'staff';
    $pass = (string) ($_POST['password'] ?? '');
    $generated = $pass === '';
    if ($generated) {
        $pass = temp_password();
    }
    $mustChange = input('must_change') === '1';
    audit_note(($username ?: $email) . ' (' . $role . ')');
    $error = null;
    if ($name === '') {
        $error = 'Enter the person\'s name.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That email address does not look right.';
    } elseif ($role === 'admin' && $email === '') {
        $error = 'Admins need an email address: their sign-in code is sent there.';
    } elseif ($username === '' && $email === '') {
        $error = 'Enter a user ID or an email address to sign in with.';
    } elseif ($username !== '' && !preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/', $username)) {
        $error = 'User ID: 3–40 letters, numbers, dots, dashes or underscores (for example frontdesk or maria.k).';
    } elseif ($username !== '' && str_contains($username, '@')) {
        $error = 'A user ID cannot contain @.';
    } elseif (!login_room()) {
        $error = plan_limit_message('logins');
    } elseif ($p = password_problem($pass, $role)) {
        $error = $p;
    } elseif ($email !== '' && val('SELECT id FROM users WHERE tenant_id = ? AND (email = ? OR username = ?)', [tid(), $email, $email])) {
        $error = 'A login with that email already exists.';
    } elseif ($username !== '' && val('SELECT id FROM users WHERE tenant_id = ? AND (username = ? OR email = ?)', [tid(), $username, $username])) {
        $error = 'That user ID is already taken in this portal.';
    }
    if ($error) {
        audit_skip();
        flash('error', $error);
        redirect(url('/admin/users'));
    }
    $id = insert('users', ['tenant_id' => tid(), 'name' => $name, 'email' => $email ?: null, 'username' => $username ?: null, 'role' => $role,
        'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'must_change_password' => $mustChange ? 1 : 0]);
    $u = row('SELECT * FROM users WHERE id = ?', [$id]);
    user_credentials_result($u, $pass, $mustChange, false, input('send_email') === '1', $generated);
    redirect(url('/admin/users'));
}

/** Emails the login details when asked; otherwise (or when email fails) shows the password once. */
function user_credentials_result(array $u, string $pass, bool $temporary, bool $reset, bool $send, bool $generated): void
{
    $sent = $send && !empty($u['email']) && user_credentials_mail($u, $pass, $temporary, $reset);
    $what = $reset ? 'New password set for ' . $u['name'] : $u['name'] . ' was added';
    if ($sent) {
        flash('success', $what . '. The sign-in details were emailed to ' . $u['email'] . '.');
        audit_note(login_name($u) . ' (' . $u['role'] . ')', 'Sign-in details emailed to ' . $u['email'] . ($temporary ? '; must change password at first sign-in' : ''));
        return;
    }
    $why = $send && empty($u['email']) ? ' (no email address on this login, so nothing was emailed)' : ($send ? ' (the email could not be sent: ' . ($GLOBALS['mail_error'] ?? 'unknown error') . ')' : '');
    flash($send ? 'error' : 'success', $what . $why . '. Give them these details yourself — shown only now: sign in with ' . login_name($u)
        . ', ' . ($temporary ? 'temporary password ' : 'password ') . ($generated || $send ? $pass : '(the one you entered)') . ($temporary ? '. They will choose a new one at first sign-in.' : '.'));
    audit_note(login_name($u) . ' (' . $u['role'] . ')', ($send ? 'Email not sent' : 'Not emailed') . ($temporary ? '; must change password at first sign-in' : ''));
}

/** Professional welcome / new-password email with the sign-in details. */
function user_credentials_mail(array $u, string $pass, bool $temporary, bool $reset): bool
{
    $t = tenant();
    $app = cfg('app_name');
    $login = tenant_url($t, '/login');
    $isAdmin = in_array($u['role'], ['owner', 'admin'], true);
    $row = fn(string $k, string $v, bool $mono = false) => '<tr><td style="padding:10px 14px;color:#6b7090;font-size:13px;width:150px;border-bottom:1px solid #eceef6">' . $k . '</td>'
        . '<td style="padding:10px 14px;font-size:15px;font-weight:600;color:#161b36;border-bottom:1px solid #eceef6;' . ($mono ? 'font-family:Consolas,Menlo,monospace;letter-spacing:.5px' : '') . '">' . $v . '</td></tr>';
    $intro = $reset
        ? 'A new ' . ($temporary ? 'temporary ' : '') . 'password has been set for your account at <b>' . e($t['name']) . '</b>.'
        : 'An account has been created for you at <b>' . e($t['name']) . '</b>' . ($isAdmin ? ' with administrator access.' : ' so you can open the check-in kiosk.');
    $next = $temporary
        ? '<p style="margin:16px 0 0">For your security, you will be asked to choose your own password the first time you sign in. This temporary password stops working once you change it.</p>'
        : '<p style="margin:16px 0 0">Please keep this password private. You can change it at any time after signing in.</p>';
    $otp = $isAdmin && admin_otp_enabled() ? '<p style="margin:12px 0 0">When you sign in, we will also email you a 6-digit code to confirm it is you.</p>' : '';
    $html = '<div style="background:#f3f4fa;padding:28px 12px;font-family:Arial,Helvetica,sans-serif;color:#1f2340">'
        . '<div style="max-width:560px;margin:auto;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 8px 30px rgba(22,27,54,.08)">'
        . '<div style="background:#5B4CE0;color:#fff;padding:22px 26px"><div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;opacity:.85">' . e($app) . '</div>'
        . '<div style="font-size:21px;font-weight:700;margin-top:4px">' . ($reset ? 'Your new password' : 'Welcome to ' . e($t['name'])) . '</div></div>'
        . '<div style="padding:22px 26px;font-size:15px;line-height:1.55">'
        . '<p style="margin:0 0 14px">Hello ' . e($u['name']) . ',</p><p style="margin:0 0 16px">' . $intro . '</p>'
        . '<table style="width:100%;border-collapse:collapse;border:1px solid #eceef6;border-radius:12px">'
        . $row('Sign-in page', '<a href="' . e($login) . '" style="color:#5B4CE0">' . e(preg_replace('#^https?://#', '', $login)) . '</a>')
        . $row(!empty($u['username']) ? 'User ID' : 'Email', e(login_name($u)), true)
        . $row($temporary ? 'Temporary password' : 'Password', e($pass), true)
        . '</table>' . $next . $otp
        . '<p style="margin:22px 0 0"><a href="' . e($login) . '" style="display:inline-block;background:#5B4CE0;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:10px">Sign in</a></p>'
        . '<p style="margin:22px 0 0;color:#6b7090;font-size:13px">If you were not expecting this email, please ignore it and let your administrator know, or contact us at ' . e(contact_email()) . '.</p>'
        . '<p style="margin:16px 0 0">Kind regards,<br>' . e($t['name']) . ' · ' . e($app) . '</p></div></div></div>';
    $subject = $reset ? 'Your new ' . ($temporary ? 'temporary ' : '') . 'password for ' . $t['name'] : 'Your ' . $t['name'] . ' account on ' . $app;
    return send_mail([(string) $u['email']], $subject, $html);
}

function admin_user_delete(int $id): void
{
    $me = require_admin();
    $u = row('SELECT * FROM users WHERE id = ? AND tenant_id = ?', [$id, tid()]);
    audit_note($u ? login_name($u) . ' (' . $u['role'] . ')' : "#$id");
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
    audit_note($u ? login_name($u) . ' (' . $u['role'] . ')' : "#$id");
    $pass = (string) ($_POST['password'] ?? '');
    $generated = $pass === '';
    if ($generated) {
        $pass = temp_password();
    }
    $mustChange = input('must_change') === '1';
    if (!$u || ($u['role'] === 'owner' && $u['id'] != $me['id'])) {
        audit_skip();
        flash('error', "That user's password cannot be changed here.");
    } elseif ($p = password_problem($pass, $u['role'])) {
        audit_skip();
        flash('error', $p);
    } else {
        update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'must_change_password' => $mustChange ? 1 : 0], 'id = ?', [$id]);
        user_credentials_result($u, $pass, $mustChange, true, input('send_email') === '1', $generated);
    }
    redirect(url('/admin/users'));
}

function admin_password(): void
{
    $me = require_admin();
    $u = row('SELECT * FROM users WHERE id = ?', [$me['id']]);
    $new = (string) ($_POST['new_password'] ?? '');
    audit_note(login_name($u));
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
    admin_view('billing', ['t' => tenant(), 'plans' => plans(), 'payments' => $payments, 'state' => billing_state(), 'cycle' => cycle_input(),
        'people' => people_count(), 'logins' => (int) val('SELECT COUNT(*) FROM users WHERE tenant_id = ?', [tid()])]);
}

function admin_billing_stripe(): void
{
    require_admin();
    $plan = (string) input('plan');
    try {
        if (!isset(plans()[$plan])) {
            throw new RuntimeException('Please choose a plan.');
        }
        $url = stripe_checkout_url(tenant(), $plan, cycle_input());
        audit('Started card checkout', plans()[$plan]['name'] . ' plan (' . cycle_input() . ')', true, 'Stripe Checkout');
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
        wave_send_invoice(tenant(), $plan, cycle_input());
        audit('Requested invoice', plans()[$plan]['name'] . ' plan (' . cycle_input() . ')', true, 'Wave invoice emailed to ' . owner_email(tenant()));
        flash('success', 'Your invoice is on its way to ' . owner_email(tenant()) . '. Your portal activates as soon as it is paid.');
    } catch (Throwable $e) {
        audit('Requested invoice', $plan, false, $e->getMessage());
        flash('error', $e->getMessage());
    }
    redirect(url('/admin/billing'));
}

// ---------- Help & FAQ, What's new, feature requests ----------
function admin_help(): void
{
    require_admin();
    admin_view('help', ['t' => tenant()]);
}

function admin_whats_new(): void
{
    require_admin();
    $mine = rows('SELECT * FROM feature_requests WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 50', [tid()]);
    $old = $_SESSION['fr_old'] ?? [];
    $errors = $_SESSION['fr_errors'] ?? [];
    unset($_SESSION['fr_old'], $_SESSION['fr_errors']);
    admin_view('whats_new', ['mine' => $mine, 'old' => $old, 'errors' => $errors]);
}

/** A customer asks for a feature: stored for the provider (/super → Requests) and emailed to contact@. */
function admin_feature_request(): void
{
    $me = require_admin();
    unset($_SESSION['fr_old'], $_SESSION['fr_errors']);
    $d = [
        'title'    => mb_substr(trim((string) input('title')), 0, 150),
        'category' => (string) input('category'),
        'priority' => (string) input('priority'),
        'details'  => mb_substr(trim((string) input('details')), 0, 4000),
        'problem'  => mb_substr(trim((string) input('problem')), 0, 2000),
        'people'   => mb_substr(trim((string) input('people')), 0, 60),
    ];
    $errors = [];
    if (mb_strlen($d['title']) < 4) {
        $errors['title'] = 'Give the feature a short name.';
    }
    if (!isset(FEATURE_CATEGORIES[$d['category']])) {
        $errors['category'] = 'Choose an area.';
    }
    if (!isset(FEATURE_PRIORITIES[$d['priority']])) {
        $errors['priority'] = 'Choose how important it is.';
    }
    if (mb_strlen($d['details']) < 15) {
        $errors['details'] = 'Describe what you would like it to do (a sentence or two).';
    }
    if (!$errors && (int) val('SELECT COUNT(*) FROM feature_requests WHERE tenant_id = ? AND created_at > ?', [tid(), gmdate('Y-m-d H:i:s', time() - 86400)]) >= 10) {
        $errors['title'] = 'You have sent 10 requests today. Please send more tomorrow, or email us.';
    }
    if ($errors) {
        audit_skip();
        $_SESSION['fr_old'] = $d;
        $_SESSION['fr_errors'] = $errors;
        redirect(url('/admin/whats-new#request'));
    }
    $t = tenant();
    $now = gmdate('Y-m-d H:i:s');
    $id = insert('feature_requests', $d + ['tenant_id' => tid(), 'portal' => $t['name'], 'user_name' => $me['name'], 'user_email' => $me['email'],
        'status' => 'new', 'created_at' => $now, 'updated_at' => $now]);
    audit_note($d['title'], FEATURE_CATEGORIES[$d['category']] . ' · ' . FEATURE_PRIORITIES[$d['priority']]);
    $row = fn($k, $v) => '<tr><td style="padding:9px 12px;color:#6b7090;font-size:13px;width:150px;border-bottom:1px solid #eceef6">' . $k . '</td><td style="padding:9px 12px;font-size:14px;color:#161b36;border-bottom:1px solid #eceef6">' . $v . '</td></tr>';
    $html = '<div style="background:#f3f4fa;padding:24px 12px;font-family:Arial,Helvetica,sans-serif"><div style="max-width:580px;margin:auto;background:#fff;border-radius:18px;overflow:hidden">'
        . '<div style="background:#5B4CE0;color:#fff;padding:20px 24px"><div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;opacity:.85">Feature request #' . $id . '</div>'
        . '<div style="font-size:20px;font-weight:700;margin-top:4px">' . e($d['title']) . '</div></div><div style="padding:18px 22px"><table style="width:100%;border-collapse:collapse">'
        . $row('Portal', e($t['name']) . ' (' . e($t['slug']) . ')') . $row('From', e($me['name']) . ' · ' . e((string) $me['email']))
        . $row('Area', e(FEATURE_CATEGORIES[$d['category']])) . $row('Importance', e(FEATURE_PRIORITIES[$d['priority']])) . $row('People affected', e($d['people'] ?: '—'))
        . $row('What it should do', nl2br(e($d['details']))) . $row('Problem it solves', nl2br(e($d['problem'] ?: '—')))
        . '</table><p style="color:#6b7090;font-size:12px;margin-top:16px">Reply and set a status in the provider console → Requests.</p></div></div></div>';
    send_mail([contact_email()], 'Feature request: ' . $d['title'] . ' — ' . $t['name'], $html);
    flash('success', 'Thank you! Your request was sent to our team. You can follow its status below.');
    redirect(url('/admin/whats-new#mine'));
}
