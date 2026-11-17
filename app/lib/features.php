<?php
// Optional features (Admin → Features & add-ons). Everything is OFF until the customer admin turns it on.
// Each customer only ever sees the add-on pack that fits its industry.

/** Add-on packs: one per industry group, same price. */
const PACKS = [
    'safe_pickup' => ['name' => 'Safe Pickup', 'industries' => ['school', 'tutoring'],
        'pitch' => 'Make every pickup safe and give parents peace of mind.'],
    'staff_hours' => ['name' => 'Staff Hours', 'industries' => ['office', 'clinic', 'construction', 'retail', 'restaurant', 'gym', 'warehouse', 'events', 'nonprofit', 'field'],
        'pitch' => 'Accurate staff hours without a tablet, straight into payroll.'],
];
const PACK_PRICE = 19;
const SUPPORT_PRICE = 99;

/** Feature catalog. pack = null: free on every plan. ready = built and usable now. */
const FEATURES = [
    'roll_call'      => ['name' => 'Emergency roll call', 'pack' => null, 'ready' => true,
        'what' => 'In a fire drill or evacuation, open Roll call on your phone: it lists everyone signed in right now (people, staff, visitors). Tick each person as they are counted outside; anyone left unticked is missing.'],
    'qr_badges'      => ['name' => 'QR badges', 'pack' => null, 'ready' => true,
        'what' => 'Every person gets a personal QR code (printed card or on their phone). The kiosk camera or a USB scanner reads it and signs them in or out in a second, without searching names.'],
    'signout_reminders' => ['name' => 'Missed sign-out reminders', 'pack' => null, 'ready' => true,
        'what' => 'The morning after someone signed in but did not sign out, they get a short friendly email: staff "please sign out before you leave", parents "please sign out at pickup". Your daily report shows who was reminded and who keeps forgetting.'],
    'family_pin'     => ['name' => 'Family PIN at pickup', 'pack' => 'safe_pickup', 'ready' => false,
        'what' => 'Each family gets a 4-digit PIN. Signing a child out on the kiosk needs the PIN, so nobody can tap a child\'s name and leave with them.'],
    'parent_alerts'  => ['name' => 'Parent notifications', 'pack' => 'safe_pickup', 'ready' => false,
        'what' => 'Parents get an email the moment their child is signed in or out, e.g. "Ava was picked up by Grandma (authorized relative) at 3:12 PM".'],
    'phone_checkin'  => ['name' => 'Phone check-in', 'pack' => 'staff_hours', 'ready' => false,
        'what' => 'Staff scan a QR poster at the door with their own phone and clock in or out. It only works at your location, so nobody clocks in from home or for a colleague.'],
    'payroll_export' => ['name' => 'Payroll export', 'pack' => 'staff_hours', 'ready' => false,
        'what' => 'One click gives a file of everyone\'s hours (with overtime) in the format QuickBooks, Gusto or ADP accepts.'],
];

/** The pack that fits a portal's industry. */
function tenant_pack(?array $t = null): string
{
    $t = $t ?? tenant();
    foreach (PACKS as $key => $p) {
        if (in_array($t['industry'] ?? 'school', $p['industries'], true)) {
            return $key;
        }
    }
    return 'staff_hours';
}

/** Features this portal may see: free ones plus its own pack. */
function available_features(?array $t = null): array
{
    $pack = tenant_pack($t);
    return array_filter(FEATURES, fn($f) => $f['pack'] === null || $f['pack'] === $pack);
}

/** The pack is usable: paid (active) or during the free trial. */
function pack_active(?array $t = null): bool
{
    $t = $t ?? tenant();
    return ($t['addon_status'] ?? '') === 'active' || billing_state($t) === 'trial';
}

function enabled_features(?array $t = null): array
{
    $t = $t ?? tenant();
    $list = json_decode((string) ($t['features'] ?? ''), true);
    return is_array($list) ? $list : [];
}

/** true when the customer turned this feature on and may use it. */
function feature_on(string $key, ?array $t = null): bool
{
    $t = $t ?? tenant();
    $avail = available_features($t);
    if (!isset($avail[$key]) || !$avail[$key]['ready'] || !in_array($key, enabled_features($t), true)) {
        return false;
    }
    return $avail[$key]['pack'] === null || pack_active($t);
}

function support_premium(?array $t = null): bool
{
    $t = $t ?? tenant();
    return ($t['support_plan'] ?? 'regular') === 'premium';
}
