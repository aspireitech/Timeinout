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
