<?php
// CSV ("flat file") import for students+guardians and teachers.
// Save from Excel / Google Sheets as CSV. Headers are matched loosely
// (case, spaces and dashes are ignored). Re-importing the same file is safe:
// existing people are matched by ID (or by name) and updated, not duplicated.

function csv_read(string $file): array
{
    $raw = file_get_contents($file);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) $raw); // Excel BOM
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }
    $firstLine = strtok($raw, "\n");
    $delim = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ',';
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    $header = null;
    $out = [];
    while (($r = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
        if ($r === [null] || implode('', $r) === '') {
            continue;
        }
        if ($header === null) {
            $header = array_map(fn($h) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $h)), $r);
            continue;
        }
        $row = [];
        foreach ($header as $i => $h) {
            $row[$h] = trim((string) ($r[$i] ?? ''));
        }
        $out[] = $row;
    }
    fclose($fh);
    return $out;
}

/** First non-empty value among several header spellings. */
function csv_pick(array $row, array $keys): string
{
    foreach ($keys as $k) {
        if (($row[$k] ?? '') !== '') {
            return $row[$k];
        }
    }
    return '';
}

function split_name(string $full): array
{
    $full = trim(preg_replace('/\s+/', ' ', $full));
    if (str_contains($full, ',')) { // "Last, First"
        [$l, $f] = array_map('trim', explode(',', $full, 2));
        return [$f, $l];
    }
    $pos = strrpos($full, ' ');
    return $pos === false ? [$full, ''] : [substr($full, 0, $pos), substr($full, $pos + 1)];
}

/**
 * Columns: student_id, first_name, last_name (or student_name), grade,
 *   guardian_name, relationship, phone, email          (one guardian per row), and/or
 *   guardian1_name, guardian1_relationship, guardian1_phone, guardian1_email, guardian2_... (up to 4)
 */
function import_students(int $tenantId, array $rows): array
{
    $stats = ['students_new' => 0, 'students_updated' => 0, 'guardians_new' => 0, 'skipped' => 0];
    foreach ($rows as $row) {
        $code = csv_pick($row, ['studentid', 'studentcode', 'id', 'code']);
        $first = csv_pick($row, ['firstname', 'studentfirstname', 'first']);
        $last = csv_pick($row, ['lastname', 'studentlastname', 'last']);
        if ($first === '' && ($full = csv_pick($row, ['studentname', 'name', 'student', 'fullname'])) !== '') {
            [$first, $last] = split_name($full);
        }
        if ($first === '') {
            $stats['skipped']++;
            continue;
        }
        $grade = csv_pick($row, ['grade', 'class', 'classroom', 'group']);

        $student = $code !== ''
            ? row('SELECT * FROM students WHERE tenant_id = ? AND student_code = ?', [$tenantId, $code])
            : row('SELECT * FROM students WHERE tenant_id = ? AND first_name = ? AND last_name = ?', [$tenantId, $first, $last]);
        if ($student) {
            update('students', ['first_name' => $first, 'last_name' => $last, 'grade' => $grade ?: $student['grade'], 'active' => 1], 'id = ?', [$student['id']]);
            $sid = (int) $student['id'];
            $stats['students_updated']++;
        } else {
            $sid = insert('students', ['tenant_id' => $tenantId, 'student_code' => $code ?: null, 'first_name' => $first, 'last_name' => $last, 'grade' => $grade ?: null]);
            $stats['students_new']++;
        }

        $guardians = [[
            csv_pick($row, ['guardianname', 'guardian', 'parentname', 'parent']),
            csv_pick($row, ['relationship', 'relation', 'guardianrelationship']),
            csv_pick($row, ['phone', 'guardianphone', 'parentphone']),
            csv_pick($row, ['email', 'guardianemail', 'parentemail']),
        ]];
        for ($i = 1; $i <= 4; $i++) {
            $guardians[] = [
                csv_pick($row, ["guardian{$i}name", "guardian{$i}", "parent{$i}name", "parent{$i}"]),
                csv_pick($row, ["guardian{$i}relationship", "guardian{$i}relation", "parent{$i}relationship"]),
                csv_pick($row, ["guardian{$i}phone", "parent{$i}phone"]),
                csv_pick($row, ["guardian{$i}email", "parent{$i}email"]),
            ];
        }
        foreach ($guardians as [$gName, $rel, $phone, $email]) {
            if ($gName === '') {
                continue;
            }
            $exists = val('SELECT id FROM guardians WHERE tenant_id = ? AND student_id = ? AND name = ?', [$tenantId, $sid, $gName]);
            if ($exists) {
                update('guardians', ['relationship' => $rel ?: null, 'phone' => $phone ?: null, 'email' => $email ?: null], 'id = ?', [$exists]);
            } else {
                insert('guardians', ['tenant_id' => $tenantId, 'student_id' => $sid, 'name' => $gName, 'relationship' => $rel ?: null, 'phone' => $phone ?: null, 'email' => $email ?: null]);
                $stats['guardians_new']++;
            }
        }
    }
    return $stats;
}

/** Columns: employee_id, first_name, last_name (or name), email, phone */
function import_teachers(int $tenantId, array $rows): array
{
    $stats = ['teachers_new' => 0, 'teachers_updated' => 0, 'skipped' => 0];
    foreach ($rows as $row) {
        $code = csv_pick($row, ['employeeid', 'employeecode', 'teacherid', 'staffid', 'id', 'code']);
        $first = csv_pick($row, ['firstname', 'first']);
        $last = csv_pick($row, ['lastname', 'last']);
        if ($first === '' && ($full = csv_pick($row, ['name', 'teachername', 'employeename', 'fullname'])) !== '') {
            [$first, $last] = split_name($full);
        }
        if ($first === '') {
            $stats['skipped']++;
            continue;
        }
        $data = ['first_name' => $first, 'last_name' => $last, 'email' => csv_pick($row, ['email']) ?: null, 'phone' => csv_pick($row, ['phone', 'mobile']) ?: null, 'active' => 1];
        $t = $code !== ''
            ? row('SELECT id FROM teachers WHERE tenant_id = ? AND employee_code = ?', [$tenantId, $code])
            : row('SELECT id FROM teachers WHERE tenant_id = ? AND first_name = ? AND last_name = ?', [$tenantId, $first, $last]);
        if ($t) {
            update('teachers', $data, 'id = ?', [$t['id']]);
            $stats['teachers_updated']++;
        } else {
            insert('teachers', $data + ['tenant_id' => $tenantId, 'employee_code' => $code ?: null]);
            $stats['teachers_new']++;
        }
    }
    return $stats;
}
