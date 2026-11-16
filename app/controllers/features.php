<?php
// Admin → Features & add-ons, add-on / support requests, and Emergency roll call.

function admin_features(): void
{
    require_admin();
    $t = tenant();
    $avail = available_features($t);
    if (is_post()) {
        $want = array_map('strval', (array) ($_POST['f'] ?? []));
        $on = [];
        foreach ($avail as $key => $f) {
            if ($f['ready'] && in_array($key, $want, true) && ($f['pack'] === null || pack_active($t))) {
                $on[] = $key;
            }
        }
        // keep pack features the admin turned on before, even while the pack is not active (they come back once it is)
        foreach (enabled_features($t) as $key) {
            if (isset($avail[$key]) && $avail[$key]['pack'] !== null && !pack_active($t) && !in_array($key, $on, true)) {
                $on[] = $key;
            }
        }
        audit_note('Optional features', implode(', ', array_map(fn($k) => FEATURES[$k]['name'], $on)) ?: 'all off');
        update('tenants', ['features' => json_encode(array_values($on))], 'id = ?', [tid()]);
        flash('success', 'Features saved.');
        redirect(url('/admin/features'));
    }
    admin_view('features', ['t' => $t, 'avail' => $avail, 'pack' => tenant_pack($t), 'on' => enabled_features($t)]);
}

/** The admin asks for the add-on pack or Premium Support (or to stop them). You get an alert and turn it on in /super. */
function admin_features_request(): void
{
    $me = require_admin();
    $t = tenant();
    $what = (string) input('what');
    $pack = PACKS[tenant_pack($t)];
    $map = [
        'pack'           => ['addon_status', 'requested', 'added the ' . $pack['name'] . ' pack ($' . PACK_PRICE . '/month)'],
        'pack_cancel'    => ['addon_status', 'cancel_requested', 'asked to stop the ' . $pack['name'] . ' pack'],
        'support'        => ['support_status', 'requested', 'asked for Premium Support ($' . SUPPORT_PRICE . '/month)'],
        'support_cancel' => ['support_status', 'cancel_requested', 'asked to stop Premium Support'],
    ];
    if (!isset($map[$what])) {
        redirect(url('/admin/features'));
    }
    [$col, $val, $text] = $map[$what];
    update('tenants', [$col => $val], 'id = ?', [tid()]);
    audit_note($t['name'], ucfirst($text));
    provider_alert('Add-on request: ' . $t['name'] . ' ' . $text,
        '<p><b>' . e($t['name']) . '</b> (' . e(tenant_url($t, '/')) . ') ' . e($text) . '.</p><p>Requested by ' . e($me['name'] ?? '') . ' &lt;' . e($me['email'] ?? '') . '&gt;.</p>'
        . '<p>Bill it (Stripe or invoice), then set it in <b>/super → Subscribers</b> (Add-on / Support).</p>');
    flash('success', str_contains($val, 'cancel') ? 'Request received. We will confirm by email.' : 'Thank you! We have your request and will confirm by email within one business day.');
    redirect(url('/admin/features'));
}

// ---------- QR badges ----------

/** Admin → Badges: printable cards, email badges, cancel and reissue a lost one. */
function admin_badges(): void
{
    require_admin();
    if (!feature_on('qr_badges')) {
        flash('error', 'Turn on QR badges in Features & add-ons first.');
        redirect(url('/admin/features'));
    }
    badges_ensure();
    $who = in_array(input('who'), ['students', 'staff'], true) ? (string) input('who') : 'all';
    $people = [];
    if ($who !== 'staff') {
        foreach (rows('SELECT id, first_name, last_name, grade, badge_code FROM students WHERE tenant_id = ? AND active = 1 ORDER BY first_name, last_name', [tid()]) as $r) {
            $people[] = ['type' => 'student', 'id' => (int) $r['id'], 'name' => full_name($r), 'group' => $r['grade'] ?: term('a1'), 'code' => $r['badge_code']];
        }
    }
    if ($who !== 'students') {
        foreach (rows('SELECT id, first_name, last_name, badge_code FROM teachers WHERE tenant_id = ? AND active = 1 ORDER BY first_name, last_name', [tid()]) as $r) {
            $people[] = ['type' => 'teacher', 'id' => (int) $r['id'], 'name' => full_name($r), 'group' => term('b1'), 'code' => $r['badge_code']];
        }
    }
    admin_view('badges', ['people' => $people, 'who' => $who, 't' => tenant()]);
}

function admin_badge_reissue(): void
{
    require_admin();
    $type = input('type') === 'teacher' ? 'teacher' : 'student';
    $id = (int) input('id');
    $code = feature_on('qr_badges') ? badge_reissue($type, $id) : null;
    audit_note(($type === 'teacher' ? 'Staff' : 'Student') . " #$id", $code ? 'Badge cancelled and reissued' : 'Badge reissue failed');
    flash($code ? 'success' : 'error', $code ? 'The old badge no longer works. Print or send the new one.' : 'Could not reissue that badge.');
    redirect(url('/admin/badges'));
}

/** Emails each staff member their own badge link, or each family the badge link of their child. */
function admin_badges_email(): void
{
    require_admin();
    if (!feature_on('qr_badges')) {
        redirect(url('/admin/features'));
    }
    badges_ensure();
    $t = tenant();
    $who = input('who') === 'students' ? 'students' : 'staff';
    $sent = $skipped = 0;
    $mail = function (string $to, string $name, string $code) use ($t, &$sent): void {
        $link = badge_link($t, $code);
        $html = '<div style="font-family:Arial,sans-serif;max-width:520px;color:#161B36;line-height:1.5"><h2 style="margin:0 0 10px">' . e($name) . '\'s badge for ' . e($t['name']) . '</h2>'
            . '<p>Open the link below on your phone and show the QR code to the kiosk camera to sign in or out in a second. Tip: save it to your home screen, or take a screenshot.</p>'
            . '<p style="margin:18px 0"><a href="' . e($link) . '" style="display:inline-block;background:#5B4CE0;color:#fff;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:bold">Open my badge</a></p>'
            . '<p style="color:#6b7090;font-size:13px">Keep this link private: it works like a key card. If it is lost, ask ' . e($t['name']) . ' for a new badge and the old one stops working.</p></div>';
        if (send_mail([$to], 'Your ' . $t['name'] . ' badge: ' . $name, $html)) {
            $sent++;
        }
    };
    if ($who === 'staff') {
        foreach (rows('SELECT first_name, last_name, email, badge_code FROM teachers WHERE tenant_id = ? AND active = 1 LIMIT 300', [tid()]) as $r) {
            $email = (string) (decrypt_pii((string) $r['email']) ?? '');
            filter_var($email, FILTER_VALIDATE_EMAIL) ? $mail($email, full_name($r), $r['badge_code']) : $skipped++;
        }
    } else {
        foreach (rows('SELECT s.first_name, s.last_name, s.badge_code, g.email FROM students s JOIN guardians g ON g.student_id = s.id AND g.tenant_id = s.tenant_id
                       WHERE s.tenant_id = ? AND s.active = 1 LIMIT 600', [tid()]) as $r) {
            $email = (string) (decrypt_pii((string) $r['email']) ?? '');
            filter_var($email, FILTER_VALIDATE_EMAIL) ? $mail($email, full_name($r), $r['badge_code']) : $skipped++;
        }
    }
    audit_note('Badges emailed (' . $who . ')', "$sent sent, $skipped without an email address");
    flash($sent ? 'success' : 'error', "$sent badge email(s) sent." . ($skipped ? " $skipped had no email address on file: print those badges instead." : ''));
    redirect(url('/admin/badges'));
}

/** Personal badge page (from the email): name and a big QR to show at the kiosk. No sign-in needed; the code is the key. */
function tenant_badge_page(): void
{
    $code = strtoupper(trim((string) input('c')));
    $found = feature_on('qr_badges') ? badge_find($code) : null;
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    view('kiosk/badge', ['t' => tenant(), 'code' => $code, 'person' => $found ? full_name($found['row']) : null], null);
}

/** Kiosk: a scanned badge → the right tile and person, with today's status, to continue the normal flow. */
function api_badge(): void
{
    api_guard();
    if (!feature_on('qr_badges')) {
        json_out(['error' => 'QR badges are not turned on.'], 404);
    }
    $found = badge_find(strtoupper(trim((string) input('code'))));
    if (!$found) {
        json_out(['error' => 'This badge is not recognised. It may have been replaced. Please type your name instead.'], 404);
    }
    $type = $found['type'];
    $tile = row('SELECT * FROM kiosk_tiles WHERE tenant_id = ? AND active = 1 AND type = ? ORDER BY sort_order, id LIMIT 1', [tid(), $type === 'teacher' ? 'staff' : 'members']);
    if (!$tile) {
        json_out(['error' => 'There is no ' . ($type === 'teacher' ? 'staff' : 'sign-in') . ' button on this kiosk. Please ask the front desk.'], 409);
    }
    $p = $found['row'];
    $date = (string) input('date');
    if (checkin_mode(checkin_group($type)) === 'tap') {
        json_out(['error' => 'Badges are not used for ' . strtolower($type === 'teacher' ? term('b2') : term('a2')) . ' here. Please tap your name instead.'], 409);
    }
    badge_pass_set($type, (int) $p['id']);
    $status = day_status($type, [(int) $p['id']], valid_date($date) ? $date : tenant_today());
    json_out([
        'tile' => ['id' => (int) $tile['id'], 'type' => $tile['type'], 'contact' => (bool) $tile['needs_contact'], 'label' => $tile['label']],
        'person' => ['id' => (int) $p['id'], 'name' => full_name($p), 'initials' => initials(full_name($p)),
                     'grade' => $type === 'student' ? $p['grade'] : null, 'status' => $status[(int) $p['id']] ?? null],
    ]);
}

/** Kiosk "Forgot my badge?": the front desk types its code; name search opens for this group for 2 minutes. */
function api_desk_unlock(): void
{
    api_guard();
    $in = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $group = checkin_group((string) ($in['group'] ?? 'members'));
    $key = 'tenant' . tid();
    if (login_blocked('desk', $key)) {
        json_out(['error' => 'Too many wrong codes. Please wait 15 minutes.'], 429);
    }
    $hash = (string) (tenant()['desk_pin_hash'] ?? '');
    $ok = $hash !== '' && password_verify((string) ($in['pin'] ?? ''), $hash);
    login_attempt('desk', $key, $ok);
    if (!$ok) {
        json_out(['error' => $hash === '' ? 'No front-desk code is set. An admin can set one in Admin → QR badges.' : 'That code is not right.'], 403);
    }
    $_SESSION['desk_unlock'][tid()][$group] = time() + DESK_UNLOCK_SECONDS;
    audit('Front desk unlocked name search', $group, true);
    json_out(['ok' => true]);
}

/** Admin → QR badges → Check-in method: per group, and the front-desk code. */
function admin_badges_settings(): void
{
    require_admin();
    $modes = [];
    foreach (['members', 'staff'] as $g) {
        $m = (string) input('mode_' . $g);
        $modes[$g] = isset(CHECKIN_MODES[$m]) ? $m : 'both';
    }
    $data = ['checkin_modes' => json_encode($modes)];
    $pin = trim((string) input('desk_pin'));
    if ($pin !== '') {
        if (!preg_match('/^\d{4,8}$/', $pin)) {
            flash('error', 'The front-desk code must be 4 to 8 digits.');
            redirect(url('/admin/badges'));
        }
        $data['desk_pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
    }
    update('tenants', $data, 'id = ?', [tid()]);
    audit_note('Check-in method', term('a2') . ': ' . CHECKIN_MODES[$modes['members']] . '; ' . term('b2') . ': ' . CHECKIN_MODES[$modes['staff']] . ($pin !== '' ? '; front-desk code changed' : ''));
    $needsPin = in_array('scan', $modes, true) && $pin === '' && empty(tenant()['desk_pin_hash']);
    flash($needsPin ? 'error' : 'success', $needsPin ? 'Saved. Set a front-desk code too, so people who forgot their badge can still sign in with the front desk\'s help.' : 'Check-in method saved.');
    redirect(url('/admin/badges'));
}

/** Emergency roll call: everyone signed in right now, to tick off at the assembly point. */
function admin_rollcall(): void
{
    require_admin();
    if (!feature_on('roll_call')) {
        flash('error', 'Turn on Emergency roll call in Features & add-ons first.');
        redirect(url('/admin/features'));
    }
    $today = tenant_today();
    $people = [];
    foreach (on_site('student', $today) as $r) {
        $people[] = ['group' => term('a2'), 'name' => $r['person_name'], 'sub' => 'In since ' . fmt_time($r['event_time']) . ($r['guardian_name'] ? ' · with ' . $r['guardian_name'] : '')];
    }
    foreach (on_site('teacher', $today) as $r) {
        $people[] = ['group' => term('b2'), 'name' => $r['person_name'], 'sub' => 'In since ' . fmt_time($r['event_time'])];
    }
    foreach (visitors_on_site($today) as $v) {
        $people[] = ['group' => 'Visitors', 'name' => $v['name'], 'sub' => trim(($v['company'] ? $v['company'] . ' · ' : '') . 'In since ' . fmt_time($v['event_time']))];
    }
    audit_note('Emergency roll call opened', count($people) . ' people on site');
    admin_view('rollcall', ['people' => $people, 'today' => $today]);
}
