<?php
// Industry templates. Each business picks one at sign-up; it sets the words
// used everywhere (Student/Employee/Member…) and the starting kiosk tiles.
// Admins can rename, recolor, reorder, hide and add tiles afterwards.
//
// Two people lists exist for every business:
//   list A ("members")  — stored in `students`, optional contacts (stored in `guardians`)
//   list B ("staff")    — stored in `teachers`
// Tile types:
//   members  sign in/out from list A (optionally choosing the contact who came with them)
//   staff    sign in/out from list B
//   pickup   items collected for someone on list A (optionally by one of their contacts)
//   visitor  anyone not on a list types their name, company and who they are visiting

const TILE_TYPES = [
    'members' => 'Sign in / out (main list)',
    'staff'   => 'Sign in / out (staff list)',
    'pickup'  => 'Item pickup',
    'visitor' => 'Visitor (types their name)',
];

const TILE_COLORS = [
    'violet' => ['#6C5CE7', '#A66CFF'], 'teal' => ['#00B894', '#00CEC9'], 'coral' => ['#FF7A59', '#FDB44B'],
    'blue'   => ['#0984E3', '#3EB4FF'], 'pink' => ['#E84393', '#FD79A8'], 'amber' => ['#E59400', '#F2C14E'],
    'ink'    => ['#2D3436', '#636E72'], 'green' => ['#2E9E4F', '#7BD389'],
];

const TILE_ICONS = ['users', 'teacher', 'box', 'badge', 'briefcase', 'heart', 'tool', 'truck', 'dumbbell', 'book', 'calendar', 'kiosk', 'shield', 'home'];

function industries(): array
{
    // terms: a1/a2 = main list (singular/plural), c1/c2 = contacts (null = none), b1/b2 = staff list
    $t = fn($a1, $a2, $c1, $c2, $b1, $b2, $items = 'Items') => compact('a1', 'a2', 'c1', 'c2', 'b1', 'b2', 'items');
    // tile: [type, label, subtitle, icon, color, needs_contact, active]
    return [
        'school' => ['name' => 'School / daycare', 'icon' => 'book',
            'terms' => $t('Student', 'Students', 'Parent or guardian', 'Parents & guardians', 'Teacher', 'Teachers & staff', 'Materials'),
            'tiles' => [['members', 'Student', 'Sign in or out with your parent or guardian', 'users', 'violet', 1, 1],
                        ['staff', 'Teacher / Staff', 'Start or end your day', 'teacher', 'teal', 0, 1],
                        ['pickup', 'Material Pickup', 'Pick up homework, books or other items', 'box', 'coral', 1, 1],
                        ['visitor', 'Visitor', 'Sign in as a guest', 'badge', 'blue', 0, 0]],
            'items' => ['Homework folder', 'Books', 'Uniform', 'Lunch box', 'Art project']],
        'tutoring' => ['name' => 'Tutoring / learning center', 'icon' => 'book',
            'terms' => $t('Student', 'Students', 'Parent or guardian', 'Parents & guardians', 'Tutor', 'Tutors & staff', 'Materials'),
            'tiles' => [['members', 'Student', 'Sign in or out with your parent or guardian', 'users', 'violet', 1, 1],
                        ['staff', 'Tutor / Staff', 'Start or end your session', 'teacher', 'teal', 0, 1],
                        ['pickup', 'Material Pickup', 'Collect worksheets or books', 'box', 'coral', 1, 1]],
            'items' => ['Worksheets', 'Books', 'Progress report']],
        'office' => ['name' => 'Office', 'icon' => 'briefcase',
            'terms' => $t('Employee', 'Employees', 'Emergency contact', 'Emergency contacts', 'Manager', 'Managers'),
            'tiles' => [['members', 'Employee', 'Clock in or out', 'briefcase', 'violet', 0, 1],
                        ['visitor', 'Visitor', 'Sign in as a guest', 'badge', 'blue', 0, 1],
                        ['pickup', 'Package Pickup', 'Collect a delivery or package', 'box', 'coral', 0, 1]],
            'items' => ['Package', 'Mail', 'Keys', 'Laptop']],
        'clinic' => ['name' => 'Clinic / care home', 'icon' => 'heart',
            'terms' => $t('Staff member', 'Staff', 'Emergency contact', 'Emergency contacts', 'Provider', 'Providers'),
            'tiles' => [['members', 'Staff', 'Start or end your shift', 'users', 'teal', 0, 1],
                        ['staff', 'Provider', 'Doctors and nurses', 'heart', 'violet', 0, 1],
                        ['visitor', 'Visitor', 'Visiting a patient or resident', 'badge', 'blue', 0, 1]],
            'items' => ['Supplies', 'Keys']],
        'construction' => ['name' => 'Construction', 'icon' => 'tool',
            'terms' => $t('Crew member', 'Crew', 'Emergency contact', 'Emergency contacts', 'Supervisor', 'Supervisors', 'Equipment'),
            'tiles' => [['members', 'Crew', 'Clock in or out at this site', 'tool', 'amber', 0, 1],
                        ['staff', 'Supervisor', 'Start or end your day', 'shield', 'ink', 0, 1],
                        ['visitor', 'Visitor / Subcontractor', 'Sign in before entering the site', 'badge', 'blue', 0, 1],
                        ['pickup', 'Equipment', 'Check out tools or equipment', 'box', 'coral', 0, 1]],
            'items' => ['Hard hat', 'Power tools', 'Ladder', 'Safety harness']],
        'retail' => ['name' => 'Retail store', 'icon' => 'box',
            'terms' => $t('Employee', 'Employees', 'Emergency contact', 'Emergency contacts', 'Manager', 'Managers'),
            'tiles' => [['members', 'Employee', 'Start or end your shift', 'users', 'violet', 0, 1],
                        ['staff', 'Manager', 'Open or close the store', 'shield', 'ink', 0, 1],
                        ['visitor', 'Vendor / Visitor', 'Deliveries and guests', 'truck', 'blue', 0, 1]],
            'items' => ['Keys', 'Cash drawer']],
        'restaurant' => ['name' => 'Restaurant / café', 'icon' => 'home',
            'terms' => $t('Team member', 'Team', 'Emergency contact', 'Emergency contacts', 'Manager', 'Managers'),
            'tiles' => [['members', 'Team', 'Start or end your shift', 'users', 'coral', 0, 1],
                        ['staff', 'Manager', 'Open or close', 'shield', 'ink', 0, 1],
                        ['visitor', 'Delivery / Vendor', 'Suppliers and inspectors', 'truck', 'blue', 0, 1]],
            'items' => ['Keys', 'Uniform']],
        'gym' => ['name' => 'Gym / studio', 'icon' => 'dumbbell',
            'terms' => $t('Member', 'Members', 'Emergency contact', 'Emergency contacts', 'Trainer', 'Trainers & staff'),
            'tiles' => [['members', 'Member', 'Check in for your visit', 'dumbbell', 'violet', 0, 1],
                        ['staff', 'Trainer / Staff', 'Start or end your shift', 'teacher', 'teal', 0, 1],
                        ['visitor', 'Guest', 'First visit or day pass', 'badge', 'pink', 0, 1]],
            'items' => ['Towel', 'Locker key']],
        'warehouse' => ['name' => 'Warehouse / logistics', 'icon' => 'truck',
            'terms' => $t('Worker', 'Workers', 'Emergency contact', 'Emergency contacts', 'Supervisor', 'Supervisors', 'Equipment'),
            'tiles' => [['members', 'Worker', 'Clock in or out', 'users', 'amber', 0, 1],
                        ['visitor', 'Driver / Visitor', 'Drivers, vendors and guests', 'truck', 'blue', 0, 1],
                        ['pickup', 'Equipment', 'Scanner, radio or vest', 'box', 'coral', 0, 1]],
            'items' => ['Scanner', 'Radio', 'Safety vest', 'Pallet jack']],
        'events' => ['name' => 'Events / venue', 'icon' => 'calendar',
            'terms' => $t('Volunteer', 'Volunteers', 'Emergency contact', 'Emergency contacts', 'Staff member', 'Staff'),
            'tiles' => [['members', 'Volunteer', 'Start or end your shift', 'heart', 'pink', 0, 1],
                        ['staff', 'Staff', 'Event staff', 'shield', 'ink', 0, 1],
                        ['visitor', 'Guest', 'Sign in at the door', 'badge', 'blue', 0, 1],
                        ['pickup', 'Kit Pickup', 'Collect your badge or kit', 'box', 'coral', 0, 1]],
            'items' => ['Badge', 'T-shirt', 'Welcome kit']],
        'nonprofit' => ['name' => 'Nonprofit / church', 'icon' => 'heart',
            'terms' => $t('Volunteer', 'Volunteers', 'Emergency contact', 'Emergency contacts', 'Staff member', 'Staff'),
            'tiles' => [['members', 'Volunteer', 'Log your volunteer hours', 'heart', 'pink', 0, 1],
                        ['staff', 'Staff', 'Start or end your day', 'users', 'teal', 0, 1],
                        ['visitor', 'Visitor', 'Sign in as a guest', 'badge', 'blue', 0, 1]],
            'items' => ['Keys', 'Supplies']],
        'field' => ['name' => 'Field services', 'icon' => 'tool',
            'terms' => $t('Technician', 'Technicians', 'Emergency contact', 'Emergency contacts', 'Dispatcher', 'Dispatchers', 'Equipment'),
            'tiles' => [['members', 'Technician', 'Clock in or out', 'tool', 'violet', 0, 1],
                        ['pickup', 'Equipment', 'Check out tools or parts', 'box', 'coral', 0, 1],
                        ['visitor', 'Visitor', 'Sign in as a guest', 'badge', 'blue', 0, 1]],
            'items' => ['Van keys', 'Tool kit', 'Parts']],
    ];
}

function industry(?string $key): array
{
    $all = industries();
    return $all[$key] ?? $all['school'];
}

/**
 * The words this business uses. term('a1') = "Student" / "Employee" / "Member"…
 * Keys: a1 a2 (main list), c1 c2 (contacts, '' when not used), b1 b2 (staff list), items, v1 (visitor).
 */
function term(string $key, ?array $t = null): string
{
    $t = $t ?? tenant();
    static $cache = [];
    $id = $t['id'] ?? 0;
    if (!isset($cache[$id]) || ($t['terms'] ?? null) !== ($cache[$id]['_raw'] ?? null)) {
        $base = industry($t['industry'] ?? 'school')['terms'] + ['v1' => 'Visitor', 'v2' => 'Visitors'];
        $own = json_decode((string) ($t['terms'] ?? ''), true) ?: [];
        $cache[$id] = array_merge($base, array_filter($own, fn($v) => $v !== null && $v !== '')) + ['_raw' => $t['terms'] ?? null];
    }
    return (string) ($cache[$id][$key] ?? '');
}

/** True when this business records contacts (parents, emergency contacts…) on its kiosk. */
function uses_contacts(): bool
{
    return (bool) val('SELECT 1 FROM kiosk_tiles WHERE tenant_id = ? AND needs_contact = 1 LIMIT 1', [tid()]);
}

/** Create the default kiosk tiles for an industry (replaces existing tiles). */
function seed_tiles(int $tenantId, string $industry): void
{
    q('DELETE FROM kiosk_tiles WHERE tenant_id = ?', [$tenantId]);
    foreach (industry($industry)['tiles'] as $i => [$type, $label, $sub, $icon, $color, $contact, $active]) {
        insert('kiosk_tiles', ['tenant_id' => $tenantId, 'type' => $type, 'label' => $label, 'subtitle' => $sub, 'icon' => $icon,
            'color' => $color, 'needs_contact' => $contact, 'active' => $active, 'sort_order' => $i]);
    }
}

function kiosk_tiles(bool $activeOnly = true): array
{
    return rows('SELECT * FROM kiosk_tiles WHERE tenant_id = ?' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY sort_order, id', [tid()]);
}

function tile_gradient(string $color): string
{
    [$a, $b] = TILE_COLORS[$color] ?? TILE_COLORS['violet'];
    return "linear-gradient(135deg, $a, $b)";
}

// ---------- Portal address: state prefix + optional city + name ----------
const US_STATES = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado',
    'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia',
    'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
    'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts',
    'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana',
    'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
    'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
    'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
    'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
    'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
];

/** "UT" + "Sandy" + "SJ Kumon" → "utsandysjkumon". Returns [slug, error]. */
function build_slug(string $state, string $city, string $name): array
{
    $state = strtoupper(trim($state));
    $prefix = isset(US_STATES[$state]) ? strtolower($state) : '';
    if ($state !== '' && $state !== 'XX' && $prefix === '') {
        return ['', 'Please choose a state.'];
    }
    $city = preg_replace('/[^a-z0-9]/', '', strtolower($city));
    $name = preg_replace('/[^a-z0-9]/', '', strtolower($name));
    if (strlen($city) > 15) {
        return ['', 'Keep the city part to 15 letters or fewer.'];
    }
    if (strlen($name) < 3 || strlen($name) > 10) {
        return ['', 'The name part must be 3 to 10 letters or numbers.'];
    }
    return [$prefix . $city . $name, null];
}
