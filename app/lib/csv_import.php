<?php
// Spreadsheet import for students+guardians and teachers: CSV, or Excel .xlsx
// (first sheet) read directly with no extra packages. Headers are matched loosely
// (case, spaces and dashes are ignored). Re-importing the same file is safe:
// existing people are matched by ID (or by name) and updated, not duplicated.

/** Reads an uploaded CSV or .xlsx file into header-keyed rows. */
function sheet_read(string $file, string $originalName): array
{
    $head = (string) file_get_contents($file, false, null, 0, 4);
    if ($head === "PK\x03\x04" || str_ends_with(strtolower($originalName), '.xlsx')) {
        return xlsx_read($file);
    }
    if ($head === "\xD0\xCF\x11\xE0") {
        throw new RuntimeException('This is an old Excel .xls file. In Excel choose File → Save As → Excel Workbook (.xlsx) or CSV, then upload again.');
    }
    return csv_read($file);
}

/** Header row + data rows → the same shape csv_read() returns. */
function sheet_rows(array $table): array
{
    $header = null;
    $out = [];
    foreach ($table as $r) {
        if (implode('', array_map('strval', $r)) === '') {
            continue;
        }
        if ($header === null) {
            $header = array_map(fn($h) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $h)), $r);
            continue;
        }
        $row = [];
        foreach ($header as $i => $h) {
            if ($h !== '') {
                $row[$h] = trim((string) ($r[$i] ?? ''));
            }
        }
        $out[] = $row;
    }
    return $out;
}

/** First worksheet of an .xlsx workbook (Office Open XML), values only. */
function xlsx_read(string $file): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('This server cannot open .xlsx files (PHP zip extension missing). Save the sheet as CSV and upload that.');
    }
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        throw new RuntimeException('This .xlsx file could not be opened. Save it again in Excel, or upload a CSV.');
    }
    $xml = function (string $name) use ($zip): ?SimpleXMLElement {
        $raw = $zip->getFromName($name);
        if ($raw === false) {
            return null;
        }
        $prev = libxml_use_internal_errors(true);
        $x = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET);
        libxml_use_internal_errors($prev);
        return $x ?: null;
    };
    // Shared strings (most text cells point into this list)
    $strings = [];
    if ($ss = $xml('xl/sharedStrings.xml')) {
        foreach ($ss->si as $si) {
            $t = isset($si->t) ? (string) $si->t : '';
            foreach ($si->r as $run) { // rich text: several runs
                $t .= (string) $run->t;
            }
            $strings[] = $t;
        }
    }
    // First sheet in workbook order
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wb = $xml('xl/workbook.xml');
    $rels = $xml('xl/_rels/workbook.xml.rels');
    if ($wb && $rels && isset($wb->sheets->sheet[0])) {
        $rid = (string) $wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = ltrim((string) $rel['Target'], '/');
                $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }
    }
    $sheet = $xml($sheetPath);
    $zip->close();
    if (!$sheet) {
        throw new RuntimeException('No worksheet found in this .xlsx file.');
    }
    $table = [];
    foreach ($sheet->sheetData->row as $r) {
        $cells = [];
        $next = 0;
        foreach ($r->c as $c) {
            $col = $next;
            if (preg_match('/^([A-Z]+)/', (string) $c['r'], $m)) {
                $col = 0;
                foreach (str_split($m[1]) as $ch) {
                    $col = $col * 26 + (ord($ch) - 64);
                }
                $col--;
            }
            $type = (string) $c['t'];
            if ($type === 's') {
                $v = $strings[(int) $c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $v = '';
                if (isset($c->is)) { // empty cells have no <is> element
                    $v = (string) $c->is->t;
                    foreach ($c->is->r as $run) {
                        $v .= (string) $run->t;
                    }
                }
            } elseif ($type === 'b') {
                $v = ((string) $c->v) === '1' ? 'TRUE' : 'FALSE';
            } else {
                $v = (string) $c->v;
                // Whole numbers such as phone numbers or IDs: no "8015550100.0" or "8.01E+9"
                if ($type !== 'str' && is_numeric($v) && (float) $v == floor((float) $v) && abs((float) $v) < 1e15) {
                    $v = number_format((float) $v, 0, '.', '');
                }
            }
            $cells[$col] = $v;
            $next = $col + 1;
        }
        if ($cells) {
            $row = array_fill(0, max(array_keys($cells)) + 1, '');
            $table[] = array_replace($row, $cells);
        }
    }
    return sheet_rows($table);
}

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
        // Simple layout: Father / Mother columns, plus up to two relatives with their relationship
        foreach (['father' => 'Father', 'mother' => 'Mother'] as $k => $relName) {
            $guardians[] = [csv_pick($row, [$k, $k . 'name']), $relName, csv_pick($row, [$k . 'phone', $k . 'mobile']), csv_pick($row, [$k . 'email'])];
        }
        for ($i = 1; $i <= 2; $i++) {
            $guardians[] = [
                csv_pick($row, ["relative{$i}name", "relative{$i}", "other{$i}name", "other{$i}"]),
                csv_pick($row, ["relative{$i}relationship", "relative{$i}relation", "other{$i}relationship"]) ?: 'Relative',
                csv_pick($row, ["relative{$i}phone", "other{$i}phone"]),
                csv_pick($row, ["relative{$i}email", "other{$i}email"]),
            ];
        }
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
            if (!$exists && $rel !== '') {
                // A real name replaces a placeholder such as "Ava's Father", so past check-ins stay linked
                $exists = val('SELECT id FROM guardians WHERE tenant_id = ? AND student_id = ? AND relationship = ? AND name LIKE ?', [$tenantId, $sid, $rel, "%'s " . $rel]);
                if ($exists) {
                    update('guardians', ['name' => $gName], 'id = ?', [$exists]);
                }
            }
            if ($exists) {
                update('guardians', ['relationship' => $rel ?: null, 'phone' => encrypt_pii($phone), 'email' => encrypt_pii($email)], 'id = ?', [$exists]);
            } else {
                insert('guardians', ['tenant_id' => $tenantId, 'student_id' => $sid, 'name' => $gName, 'relationship' => $rel ?: null, 'phone' => encrypt_pii($phone), 'email' => encrypt_pii($email)]);
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
        $data = ['first_name' => $first, 'last_name' => $last, 'email' => encrypt_pii(csv_pick($row, ['email'])), 'phone' => encrypt_pii(csv_pick($row, ['phone', 'mobile'])), 'active' => 1];
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
