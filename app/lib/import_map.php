<?php
// Spreadsheet import with a "match your columns" step. Any file layout works: each column is
// matched to a core field (ID, names, group, guardians…) or kept as extra info on the person.
// Suggestions come from the header text and from the portal's last confirmed choices.

/** What a column can be used as, per import type. */
function import_fields(string $type): array
{
    if ($type === 'teachers') {
        return ['ignore' => 'Ignore this column', 'extra' => 'Keep as extra info', 'id' => 'Employee ID', 'first' => 'First name',
                'last' => 'Last name', 'full' => 'Full name (first and last together)', 'email' => 'Email', 'phone' => 'Phone'];
    }
    return ['ignore' => 'Ignore this column', 'extra' => 'Keep as extra info', 'id' => 'Student ID', 'first' => 'First name',
            'last' => 'Last name', 'full' => 'Full name (first and last together)', 'group' => 'Grade / group',
            'guardian' => 'Guardian / pickup person (name)', 'g_rel' => 'Relationship of a guardian', 'g_phone' => 'Phone of a guardian',
            'g_email' => 'Email of a guardian'];
}

/** Fields a file may use only once. */
const IMPORT_SINGLE = ['id', 'first', 'last', 'full', 'group', 'email', 'phone'];

function header_key(string $h): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower($h));
}

/** The guardian a column belongs to: "fatherphone" and "Guardian (Father)" both give "father". */
function guardian_base(string $k): string
{
    $k = preg_replace('/(phone|mobile|cell|email|relationship|relation|sname|name)$/', '', $k);
    $k = preg_replace('/^guardian(father|mother)$/', '$1', $k);
    $k = preg_replace('/^(father|mother)guardian$/', '$1', $k);
    $k = strtr($k, ['dad' => 'father', 'mom' => 'mother']);
    $k = preg_replace('/^parent(\d*)$/', 'guardian$1', $k);
    $k = preg_replace('/^other(\d+)$/', 'relative$1', $k);
    $k = preg_replace('/^(authorized(relatives?|pickup|person)|relatives)$/', 'relative', $k);
    return $k === '' ? 'guardian' : $k;
}

/** Best guess for one header: [field, label]. */
function suggest_field(string $type, string $header): array
{
    $k = header_key($header);
    $in = fn(array $list) => in_array($k, $list, true);
    if ($type === 'teachers') {
        return match (true) {
            (bool) preg_match('/^((employee|emp|staff|teacher|worker)(id|no|num|number|code)?|id|code|idno)$/', $k) => ['id', ''],
            (bool) preg_match('/^badge(id|no|num|number)?$/', $k) => ['id', ''],
            $in(['firstname', 'first', 'givenname']) => ['first', ''],
            $in(['lastname', 'last', 'surname', 'familyname']) => ['last', ''],
            $in(['name', 'fullname', 'teachername', 'employeename', 'staffname']) => ['full', ''],
            (bool) preg_match('/(email|emailaddress)$/', $k) => ['email', ''],
            (bool) preg_match('/(phone|mobile|cell|phonenumber|mobilenumber)$/', $k) && !str_contains($k, 'emergency') => ['phone', ''],
            default => ['extra', ''],
        };
    }
    if (preg_match('/^((student|learner|child|member|pupil)(id|no|num|number|code)|id|code|idno)$/', $k)) {
        return ['id', ''];
    }
    if ($in(['firstname', 'studentfirstname', 'first', 'givenname', 'childfirstname'])) {
        return ['first', ''];
    }
    if ($in(['lastname', 'studentlastname', 'last', 'surname', 'familyname', 'childlastname'])) {
        return ['last', ''];
    }
    if ($in(['studentname', 'name', 'student', 'fullname', 'childname', 'child'])) {
        return ['full', ''];
    }
    if ($in(['grade', 'class', 'classroom', 'group', 'level', 'room'])) {
        return ['group', ''];
    }
    if (preg_match('/(phone|mobile|cell)$/', $k)) {
        return ['g_phone', ''];
    }
    if (preg_match('/email$/', $k)) {
        return ['g_email', ''];
    }
    if (preg_match('/(relationship|relation)$/', $k)) {
        return ['g_rel', ''];
    }
    $base = guardian_base($k);
    if (preg_match('/^(father|mother|guardian\d*|relative\d*)$/', $base)) {
        $label = match (true) {
            $base === 'father' => 'Father',
            $base === 'mother' => 'Mother',
            str_starts_with($base, 'relative') => str_contains($k, 'authorized') ? 'Authorized relative' : 'Relative',
            default => 'Guardian',
        };
        return ['guardian', $label];
    }
    return ['extra', ''];
}

/**
 * Suggested mapping for a file: one entry per column, [field, label, target column index].
 * The portal's saved choices for the same header text win over guesses.
 */
function suggest_mapping(string $type, array $headers, array $saved = []): array
{
    $map = [];
    foreach ($headers as $i => $h) {
        $k = header_key((string) $h);
        if ($k === '') {
            $map[$i] = ['ignore', '', null];
            continue;
        }
        if (isset($saved[$k])) {
            $map[$i] = [$saved[$k]['field'], (string) ($saved[$k]['label'] ?? ''), $saved[$k]['target'] ?? null];
            continue;
        }
        [$field, $label] = suggest_field($type, (string) $h);
        $map[$i] = [$field, $label, null];
    }
    // A field that may appear once: keep the first column, keep the rest as extra info
    $seen = [];
    foreach ($map as $i => [$field]) {
        if (in_array($field, IMPORT_SINGLE, true)) {
            if (isset($seen[$field])) {
                $map[$i] = ['extra', '', null];
            }
            $seen[$field] = true;
        }
    }
    // Link phone / email / relationship columns to their guardian column
    $guardians = array_keys(array_filter($map, fn($m) => $m[0] === 'guardian'));
    foreach ($map as $i => [$field, $label, $target]) {
        if (!str_starts_with($field, 'g_')) {
            continue;
        }
        if (is_string($target)) { // saved as a header key
            $target = array_search($target, array_map(fn($h) => header_key((string) $h), $headers), true);
            $target = $target === false ? null : $target;
        }
        if ($target === null || !in_array($target, $guardians, true)) {
            $base = guardian_base(header_key((string) $headers[$i]));
            $target = null;
            foreach ($guardians as $g) {
                if (guardian_base(header_key((string) $headers[$g])) === $base) {
                    $target = $g;
                    break;
                }
            }
            if ($target === null && count($guardians) === 1) {
                $target = $guardians[0];
            }
        }
        $map[$i] = $target === null ? ['extra', '', null] : [$field, '', $target];
    }
    return $map;
}

/** Checks a mapping chosen on the screen. Returns the problem, or null. */
function mapping_problem(string $type, array $map): ?string
{
    $fields = array_column($map, 0);
    if (!in_array('first', $fields, true) && !in_array('full', $fields, true)) {
        return 'Choose which column holds the first name (or the full name). It is what people type on the kiosk.';
    }
    foreach (array_count_values($fields) as $f => $n) {
        if ($n > 1 && in_array($f, IMPORT_SINGLE, true)) {
            return '"' . import_fields($type)[$f] . '" is chosen for ' . $n . ' columns. Choose it for one column only.';
        }
    }
    foreach ($map as $i => [$field, , $target]) {
        if (str_starts_with($field, 'g_') && ($target === null || ($map[$target][0] ?? '') !== 'guardian')) {
            return 'Column ' . ($i + 1) . ': choose which guardian column this ' . strtolower(import_fields($type)[$field]) . ' belongs to.';
        }
    }
    return null;
}

/** Splits a guardian cell: "Rose Ng (Grandmother); Tom Ng" → [[name, relationship|null], …]. */
function split_people(string $cell): array
{
    $out = [];
    foreach (preg_split('/\s*[;\n]\s*/', trim($cell)) as $p) {
        $p = trim($p);
        if ($p === '' || is_blank_value($p) || strcasecmp($p, 'self') === 0) { // "Self": no guardian, the kiosk offers Self
            continue;
        }
        $out[] = preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $p, $m) ? [trim($m[1]), trim($m[2])] : [$p, null];
    }
    return $out;
}

/** Turns table rows into person records using the mapping. */
function mapped_records(string $type, array $headers, array $rows, array $map): array
{
    $records = [];
    foreach ($rows as $r) {
        $v = function (int $i) use ($r): string {
            $x = trim((string) ($r[$i] ?? ''));
            return is_blank_value($x) ? '' : $x;
        };
        $rec = ['code' => '', 'first' => '', 'last' => '', 'group' => '', 'email' => '', 'phone' => '', 'extra' => [], 'guardians' => []];
        $full = '';
        foreach ($map as $i => [$field]) {
            $x = $v($i);
            switch ($field) {
                case 'id': $rec['code'] = $x; break;
                case 'first': $rec['first'] = $x; break;
                case 'last': $rec['last'] = $x; break;
                case 'full': $full = $x; break;
                case 'group': $rec['group'] = $x; break;
                case 'email': $rec['email'] = $x; break;
                case 'phone': $rec['phone'] = $x; break;
                case 'extra':
                    if ($x !== '') {
                        $rec['extra'][mb_substr(trim((string) $headers[$i]), 0, 60)] = mb_substr($x, 0, 500);
                    }
                    break;
            }
        }
        if ($rec['first'] === '' && $full !== '') {
            [$rec['first'], $last] = split_name($full);
            $rec['last'] = $rec['last'] ?: $last;
        }
        foreach ($map as $i => [$field, $label]) {
            if ($field !== 'guardian') {
                continue;
            }
            $people = split_people($v($i));
            $extraOf = fn(string $f) => ($j = array_search(true, array_map(fn($m) => $m[0] === $f && $m[2] === $i, $map), true)) === false ? '' : $v((int) $j);
            foreach ($people as [$name, $rel]) {
                $rec['guardians'][] = [$name, $rel ?: ($extraOf('g_rel') ?: ($label ?: 'Guardian')),
                    count($people) === 1 ? $extraOf('g_phone') : '', count($people) === 1 ? $extraOf('g_email') : ''];
            }
        }
        $records[] = $rec;
    }
    return $records;
}

/** Extra info stored on a person: encrypted JSON, merged so new values update and old ones stay. */
function merge_extra(?string $stored, array $new): ?string
{
    $old = $stored ? (json_decode((string) decrypt_pii($stored), true) ?: []) : [];
    $all = array_merge($old, $new);
    return $all ? encrypt_pii(json_encode($all, JSON_UNESCAPED_UNICODE)) : null;
}

function person_extra(array $person): array
{
    return !empty($person['extra']) ? (json_decode((string) decrypt_pii($person['extra']), true) ?: []) : [];
}

/** Adds or updates students (matched by ID, else by name) and their guardians. Never removes anyone. */
function import_student_records(int $tenantId, array $records, ?int $room = null): array
{
    $stats = ['students_new' => 0, 'students_updated' => 0, 'guardians_new' => 0, 'skipped' => 0, 'over_plan_limit' => 0];
    foreach ($records as $rec) {
        $first = mb_substr($rec['first'], 0, 80);
        $last = mb_substr($rec['last'], 0, 80);
        if ($first === '') {
            $stats['skipped']++;
            continue;
        }
        $code = mb_substr($rec['code'], 0, 50);
        $student = $code !== ''
            ? row('SELECT * FROM students WHERE tenant_id = ? AND student_code = ?', [$tenantId, $code])
            : row('SELECT * FROM students WHERE tenant_id = ? AND first_name = ? AND last_name = ?', [$tenantId, $first, $last]);
        $group = mb_substr($rec['group'], 0, 30);
        if ($student) {
            update('students', ['first_name' => $first, 'last_name' => $last, 'grade' => $group ?: $student['grade'], 'active' => 1,
                'extra' => $rec['extra'] ? merge_extra($student['extra'] ?? null, $rec['extra']) : ($student['extra'] ?? null)], 'id = ?', [$student['id']]);
            $sid = (int) $student['id'];
            $stats['students_updated']++;
        } elseif ($room !== null && $room-- <= 0) {
            $stats['over_plan_limit']++; // the plan's people limit is reached: not added
            continue;
        } else {
            $sid = insert('students', ['tenant_id' => $tenantId, 'student_code' => $code ?: null, 'first_name' => $first, 'last_name' => $last,
                'grade' => $group ?: null, 'extra' => merge_extra(null, $rec['extra'])]);
            $stats['students_new']++;
        }
        foreach ($rec['guardians'] as [$gName, $rel, $phone, $email]) {
            $gName = mb_substr(trim($gName), 0, 120);
            $rel = mb_substr((string) $rel, 0, 40);
            if ($gName === '') {
                continue;
            }
            $exists = val('SELECT id FROM guardians WHERE tenant_id = ? AND student_id = ? AND name = ?', [$tenantId, $sid, $gName]);
            if (!$exists && $rel !== '') {
                // A real name replaces a placeholder such as "Ava's Father", so past check-ins stay linked
                $exists = val('SELECT id FROM guardians WHERE tenant_id = ? AND student_id = ? AND relationship = ? AND name LIKE ?', [$tenantId, $sid, $rel, "%'s " . $rel]);
                if ($exists) {
                    update('guardians', ['name' => $gName], 'id = ?', [$exists]);
                }
            }
            if ($exists) {
                $data = ['relationship' => $rel ?: null];
                if ($phone !== '') {
                    $data['phone'] = encrypt_pii(mb_substr($phone, 0, 40));
                }
                if ($email !== '') {
                    $data['email'] = encrypt_pii(mb_substr($email, 0, 190));
                }
                update('guardians', $data, 'id = ?', [$exists]);
            } else {
                insert('guardians', ['tenant_id' => $tenantId, 'student_id' => $sid, 'name' => $gName, 'relationship' => $rel ?: null,
                    'phone' => encrypt_pii(mb_substr($phone, 0, 40)), 'email' => encrypt_pii(mb_substr($email, 0, 190))]);
                $stats['guardians_new']++;
            }
        }
    }
    return $stats;
}

/** Adds or updates staff (matched by employee ID, else by name). */
function import_teacher_records(int $tenantId, array $records, ?int $room = null): array
{
    $stats = ['teachers_new' => 0, 'teachers_updated' => 0, 'skipped' => 0, 'over_plan_limit' => 0];
    foreach ($records as $rec) {
        $first = mb_substr($rec['first'], 0, 80);
        $last = mb_substr($rec['last'], 0, 80);
        if ($first === '') {
            $stats['skipped']++;
            continue;
        }
        $code = mb_substr($rec['code'], 0, 50);
        $t = $code !== ''
            ? row('SELECT * FROM teachers WHERE tenant_id = ? AND employee_code = ?', [$tenantId, $code])
            : row('SELECT * FROM teachers WHERE tenant_id = ? AND first_name = ? AND last_name = ?', [$tenantId, $first, $last]);
        $data = ['first_name' => $first, 'last_name' => $last, 'active' => 1];
        if ($rec['email'] !== '' || !$t) {
            $data['email'] = encrypt_pii(mb_substr($rec['email'], 0, 190));
        }
        if ($rec['phone'] !== '' || !$t) {
            $data['phone'] = encrypt_pii(mb_substr($rec['phone'], 0, 40));
        }
        if ($t) {
            $data['extra'] = $rec['extra'] ? merge_extra($t['extra'] ?? null, $rec['extra']) : ($t['extra'] ?? null);
            update('teachers', $data, 'id = ?', [$t['id']]);
            $stats['teachers_updated']++;
        } elseif ($room !== null && $room-- <= 0) {
            $stats['over_plan_limit']++;
        } else {
            insert('teachers', $data + ['tenant_id' => $tenantId, 'employee_code' => $code ?: null, 'extra' => merge_extra(null, $rec['extra'])]);
            $stats['teachers_new']++;
        }
    }
    return $stats;
}

/** The portal's last confirmed choices, by header text. */
function saved_mapping(string $type): array
{
    $m = val('SELECT mapping FROM import_maps WHERE tenant_id = ? AND type = ?', [tid(), $type]);
    return $m ? (json_decode((string) $m, true) ?: []) : [];
}

function save_mapping(string $type, array $headers, array $map): void
{
    $saved = saved_mapping($type);
    foreach ($map as $i => [$field, $label, $target]) {
        $k = header_key((string) $headers[$i]);
        if ($k !== '') {
            $saved[$k] = ['field' => $field, 'label' => $label, 'target' => $target === null ? null : header_key((string) $headers[$target])];
        }
    }
    q('INSERT INTO import_maps (tenant_id, type, mapping, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE mapping = VALUES(mapping), updated_at = VALUES(updated_at)',
        [tid(), $type, json_encode($saved), gmdate('Y-m-d H:i:s')]);
}
