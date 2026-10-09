<?php
// JSON endpoints used by the kiosk screen (public/assets/js/kiosk.js).

/**
 * Latest sign-in/out of each person on a day.
 * @return array<int, array{state:string,time:string,guardian:?string}>  keyed by person id
 */
function day_status(string $type, array $ids, string $date): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $out = [];
    $events = rows(
        "SELECT person_id, kind, event_time, guardian_name FROM attendance
         WHERE tenant_id = ? AND person_type = ? AND event_date = ? AND kind IN ('sign_in','sign_out')
           AND person_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
         ORDER BY event_time, id",
        array_merge([tid(), $type, $date], $ids)
    );
    foreach ($events as $ev) { // later rows overwrite earlier ones, so the last event wins
        $out[(int) $ev['person_id']] = [
            'state' => $ev['kind'] === 'sign_in' ? 'in' : 'out',
            'time' => fmt_time($ev['event_time']),
            'guardian' => $ev['guardian_name'],
        ];
    }
    return $out;
}

function api_guard(): void
{
    if (!tenant()['kiosk_public'] && !current_user()) {
        json_out(['error' => 'Please log in to use the kiosk.'], 401);
    }
}

/** Type a few letters → matching students or teachers (first name, last name or full name prefix). */
function api_search(): void
{
    api_guard();
    $q = mb_substr((string) input('q'), 0, 50);
    if ($q === '') {
        json_out([]);
    }
    $like = addcslashes($q, '%_\\') . '%';
    $table = input('type') === 'teacher' ? 'teachers' : 'students';
    $extra = $table === 'students' ? ', grade' : ', NULL AS grade';
    $list = rows(
        "SELECT id, first_name, last_name $extra FROM $table
         WHERE tenant_id = ? AND active = 1
           AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
         ORDER BY first_name, last_name LIMIT 12",
        [tid(), $like, $like, $like]
    );
    $date = (string) input('date');
    $status = day_status($table === 'teachers' ? 'teacher' : 'student', array_column($list, 'id'), valid_date($date) ? $date : tenant_today());
    json_out(array_map(fn($p) => [
        'id' => (int) $p['id'],
        'name' => full_name($p),
        'initials' => initials(full_name($p)),
        'grade' => $p['grade'],
        'status' => $status[(int) $p['id']] ?? null,
    ], $list));
}

/** Only the guardians linked to the chosen student. */
function api_guardians(): void
{
    api_guard();
    $sid = (int) input('student_id');
    $list = rows('SELECT id, name, relationship FROM guardians WHERE tenant_id = ? AND student_id = ? ORDER BY id', [tid(), $sid]);
    json_out(array_map(fn($g) => ['id' => (int) $g['id'], 'name' => $g['name'], 'relationship' => $g['relationship'], 'initials' => initials($g['name'])], $list));
}

/** Is this person currently signed in on the chosen day? Used to suggest In vs Out. */
function api_status(): void
{
    api_guard();
    $type = input('type') === 'teacher' ? 'teacher' : 'student';
    $date = (string) input('date');
    if (!valid_date($date)) {
        $date = tenant_today();
    }
    $st = day_status($type, [(int) input('id')], $date)[(int) input('id')] ?? null;
    json_out([
        'state' => $st['state'] ?? 'none',
        'time' => $st['time'] ?? null,
        'guardian' => $st['guardian'] ?? null,
    ]);
}

function api_record(): void
{
    api_guard();
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) {
        $in = $_POST;
    }
    $mode = (string) ($in['mode'] ?? '');
    $personId = (int) ($in['person_id'] ?? 0);
    $guardianId = (int) ($in['guardian_id'] ?? 0);
    $action = (string) ($in['action'] ?? '');
    $date = (string) ($in['date'] ?? '');
    $note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 255);

    // Which day and time?
    $today = tenant_today();
    $earliest = tenant_now()->modify('-' . (int) tenant()['allow_past_days'] . ' days')->format('Y-m-d');
    if (!valid_date($date) || $date > $today || $date < $earliest) {
        json_out(['error' => 'Please choose a valid day.'], 422);
    }
    if ($date === $today) {
        $time = tenant_now()->format('H:i:s');
    } else {
        $time = (string) ($in['time'] ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            json_out(['error' => 'Please enter the time for an earlier day.'], 422);
        }
        $time .= ':00';
    }

    $type = $mode === 'teacher' ? 'teacher' : 'student';
    $person = row("SELECT * FROM {$type}s WHERE id = ? AND tenant_id = ? AND active = 1", [$personId, tid()]);
    if (!$person) {
        json_out(['error' => 'Person not found.'], 404);
    }

    $guardian = null;
    if ($type === 'student') {
        $guardian = row('SELECT * FROM guardians WHERE id = ? AND student_id = ? AND tenant_id = ?', [$guardianId, $personId, tid()]);
        if (!$guardian) {
            json_out(['error' => 'Please choose who is dropping off or picking up.'], 422);
        }
    }

    $materials = null;
    if ($mode === 'material') {
        $kind = 'material_pickup';
        $ids = array_map('intval', (array) ($in['materials'] ?? []));
        $names = $ids ? array_column(rows(
            'SELECT name FROM materials WHERE tenant_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            array_merge([tid()], $ids)
        ), 'name') : [];
        $other = mb_substr(trim((string) ($in['other'] ?? '')), 0, 120);
        if ($other !== '') {
            $names[] = $other;
        }
        if (!$names) {
            json_out(['error' => 'Please choose at least one item.'], 422);
        }
        $materials = implode(', ', $names);
    } elseif (in_array($action, ['sign_in', 'sign_out'], true)) {
        $kind = $action;
    } else {
        json_out(['error' => 'Please choose Sign In or Sign Out.'], 422);
    }

    $name = full_name($person);

    // One sign-in at a time: someone already in can only sign out, and vice versa.
    if ($kind !== 'material_pickup') {
        $st = day_status($type, [$personId], $date)[$personId] ?? null;
        $when = $date === $today ? 'today' : 'on ' . date('M j', strtotime($date));
        if ($kind === 'sign_in' && ($st['state'] ?? '') === 'in') {
            json_out(['error' => "$name is already signed in $when (since {$st['time']}" . ($st['guardian'] ? ", by {$st['guardian']}" : '') . '). Please sign out instead.'], 409);
        }
        if ($kind === 'sign_out' && ($st['state'] ?? '') !== 'in') {
            json_out(['error' => $st ? "$name already signed out $when at {$st['time']}." : "$name has not signed in $when yet, so there is nothing to sign out."], 409);
        }
    }

    insert('attendance', [
        'tenant_id'     => tid(),
        'person_type'   => $type,
        'person_id'     => $personId,
        'person_name'   => $name,
        'guardian_id'   => $guardian['id'] ?? null,
        'guardian_name' => $guardian['name'] ?? null,
        'kind'          => $kind,
        'event_date'    => $date,
        'event_time'    => $time,
        'materials'     => $materials,
        'note'          => $note ?: null,
        'recorded_by'   => current_user()['id'] ?? null,
        'ip'            => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);

    $msg = [
        'sign_in' => "$name is signed in",
        'sign_out' => "$name is signed out",
        'material_pickup' => 'Pickup recorded for ' . $name,
    ][$kind];
    json_out([
        'ok' => true,
        'message' => $msg,
        'detail' => trim(($guardian ? 'By ' . $guardian['name'] . ' · ' : '') . fmt_time($time) . ' · ' . date('D, M j', strtotime($date)) . ($materials ? ' · ' . $materials : '')),
    ]);
}
