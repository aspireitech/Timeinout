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
    $table = in_array(input('type'), ['teacher', 'staff'], true) ? 'teachers' : 'students';
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

/** The kiosk tile a request came from (decides which list, contacts and action apply). */
function request_tile(array $in): array
{
    $tile = row('SELECT * FROM kiosk_tiles WHERE id = ? AND tenant_id = ? AND active = 1', [(int) ($in['tile_id'] ?? 0), tid()]);
    if (!$tile) {
        json_out(['error' => 'This button is no longer available. Please reload the page.'], 422);
    }
    return $tile;
}

/** [date, time] for an entry: now for today, or the typed time for an earlier day. */
function entry_moment(array $in): array
{
    $date = (string) ($in['date'] ?? '');
    $today = tenant_today();
    $earliest = tenant_now()->modify('-' . (int) tenant()['allow_past_days'] . ' days')->format('Y-m-d');
    if (!valid_date($date) || $date > $today || $date < $earliest) {
        json_out(['error' => 'Please choose a valid day.'], 422);
    }
    if ($date === $today) {
        return [$date, tenant_now()->format('H:i:s')];
    }
    $time = (string) ($in['time'] ?? '');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
        json_out(['error' => 'Please enter the time for an earlier day.'], 422);
    }
    return [$date, $time . ':00'];
}

function api_record(): void
{
    api_guard();
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) {
        $in = $_POST;
    }
    $tile = request_tile($in);
    if ($tile['type'] === 'visitor') {
        visitor_record($tile, $in);
    }
    $personId = (int) ($in['person_id'] ?? 0);
    $guardianId = (int) ($in['guardian_id'] ?? 0);
    $action = (string) ($in['action'] ?? '');
    $note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 255);
    [$date, $time] = entry_moment($in);
    $today = tenant_today();

    $type = $tile['type'] === 'staff' ? 'teacher' : 'student';
    $person = row("SELECT * FROM {$type}s WHERE id = ? AND tenant_id = ? AND active = 1", [$personId, tid()]);
    if (!$person) {
        json_out(['error' => 'Person not found.'], 404);
    }

    $guardian = null;
    if ($type === 'student' && $tile['needs_contact']) {
        $guardian = row('SELECT * FROM guardians WHERE id = ? AND student_id = ? AND tenant_id = ?', [$guardianId, $personId, tid()]);
        if (!$guardian) {
            json_out(['error' => 'Please choose who is with them.'], 422);
        }
    }

    $materials = null;
    if ($tile['type'] === 'pickup') {
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
        'tile_id'       => $tile['id'],
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

// ---------- Visitors: not on any list, they type their details ----------
/** Visitors signed in on a day and not yet signed out. A visit's id is its sign-in row id. */
function visitors_on_site(string $date): array
{
    return rows(
        "SELECT a.id AS visit_id, a.person_name AS name, a.guardian_name AS company, a.note, a.event_time
         FROM attendance a
         WHERE a.tenant_id = ? AND a.person_type = 'visitor' AND a.kind = 'sign_in' AND a.event_date = ?
           AND NOT EXISTS (SELECT 1 FROM attendance o WHERE o.tenant_id = a.tenant_id AND o.person_type = 'visitor' AND o.kind = 'sign_out' AND o.person_id = a.id)
         ORDER BY a.event_time DESC",
        [tid(), $date]
    );
}

function api_visitors(): void
{
    api_guard();
    $date = valid_date((string) input('date')) ? (string) input('date') : tenant_today();
    json_out(array_map(fn($v) => [
        'visit_id' => (int) $v['visit_id'], 'name' => $v['name'], 'company' => $v['company'],
        'host' => preg_replace('/^Visiting: /', '', (string) $v['note']), 'since' => fmt_time($v['event_time']), 'initials' => initials($v['name']),
    ], visitors_on_site($date)));
}

function visitor_record(array $tile, array $in): void
{
    [$date, $time] = entry_moment($in);
    if (($in['action'] ?? '') === 'sign_out') {
        $visit = row("SELECT * FROM attendance WHERE id = ? AND tenant_id = ? AND person_type = 'visitor' AND kind = 'sign_in'", [(int) ($in['visit_id'] ?? 0), tid()]);
        if (!$visit) {
            json_out(['error' => 'That visit was not found.'], 404);
        }
        if (val("SELECT 1 FROM attendance WHERE tenant_id = ? AND person_type = 'visitor' AND kind = 'sign_out' AND person_id = ?", [tid(), $visit['id']])) {
            json_out(['error' => $visit['person_name'] . ' has already signed out.'], 409);
        }
        insert('attendance', ['tenant_id' => tid(), 'person_type' => 'visitor', 'tile_id' => $tile['id'], 'person_id' => $visit['id'],
            'person_name' => $visit['person_name'], 'guardian_name' => $visit['guardian_name'], 'kind' => 'sign_out', 'event_date' => $date,
            'event_time' => $time, 'note' => $visit['note'], 'recorded_by' => current_user()['id'] ?? null, 'ip' => client_ip()]);
        json_out(['ok' => true, 'message' => $visit['person_name'] . ' is signed out', 'detail' => fmt_time($time) . ' · ' . date('D, M j', strtotime($date))]);
    }
    $name = trim(preg_replace('/\s+/', ' ', mb_substr((string) ($in['visitor_name'] ?? ''), 0, 120)));
    $company = mb_substr(trim((string) ($in['company'] ?? '')), 0, 120);
    $host = mb_substr(trim((string) ($in['host'] ?? '')), 0, 120);
    if (mb_strlen($name) < 2) {
        json_out(['error' => 'Please type your full name.'], 422);
    }
    $id = insert('attendance', ['tenant_id' => tid(), 'person_type' => 'visitor', 'tile_id' => $tile['id'], 'person_id' => 0,
        'person_name' => $name, 'guardian_name' => $company ?: null, 'kind' => 'sign_in', 'event_date' => $date, 'event_time' => $time,
        'note' => $host !== '' ? 'Visiting: ' . $host : null, 'recorded_by' => current_user()['id'] ?? null, 'ip' => client_ip()]);
    q('UPDATE attendance SET person_id = id WHERE id = ?', [$id]); // the visit's own id links its sign-out
    json_out(['ok' => true, 'message' => "Welcome, $name!", 'detail' => trim(($company ? $company . ' · ' : '') . ($host ? 'Visiting ' . $host . ' · ' : '') . fmt_time($time))]);
}
