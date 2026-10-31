<?php
// Multi-tenancy: one codebase + one database. Each subscriber ("tenant") is a
// row in `tenants`; every other table carries tenant_id. A request is mapped
// to a tenant by, in order:
//   1. path      https://abc.com/s/xyz/...
//   2. subdomain https://xyz.abc.com/...
//   3. custom    https://checkin.xyzschool.org/... (tenants.custom_domain)
// Anything else (abc.com, www.abc.com, localhost) is the marketing/platform site.

const RESERVED_SLUGS = ['www', 'dev', 'staging', 'test', 'contact', 'leads', 'admin', 'api', 'app', 'mail', 'ftp', 'cpanel', 'webmail', 'super', 's', 'static', 'assets', 'support', 'help', 'billing', 'demo', 'demo-admin'];

/**
 * @return array|null|false  tenant row, null for the platform site, false for an unknown tenant
 */
function resolve_tenant(string $host, string &$path)
{
    $host = strtolower(preg_replace('/:\d+$/', '', $host));
    $base = strtolower((string) cfg('base_domain'));

    if (preg_match('#^/s/([a-z0-9-]+)(/.*)?$#', $path, $m)) {
        $t = tenant_by_slug($m[1]);
        if (!$t) {
            return false;
        }
        $GLOBALS['tenant_prefix'] = '/s/' . $m[1];
        $path = $m[2] ?? '/';
        return $t;
    }

    if ($base !== '' && ($host === $base || $host === 'www.' . $base)) {
        return null;
    }
    if ($base !== '' && str_ends_with($host, '.' . $base)) {
        $sub = substr($host, 0, -strlen('.' . $base));
        return tenant_by_slug($sub) ?: false;
    }
    if ($host !== '' && $host !== 'localhost' && $host !== '127.0.0.1') {
        $t = row('SELECT * FROM tenants WHERE custom_domain = ?', [$host]);
        if ($t) {
            return $t;
        }
    }
    return null;
}

function tenant_by_slug(string $slug): ?array
{
    return row('SELECT * FROM tenants WHERE slug = ?', [$slug]);
}

function tenant(): ?array
{
    return $GLOBALS['tenant'] ?? null;
}

function tid(): int
{
    return (int) $GLOBALS['tenant']['id'];
}

function valid_tz(?string $tz): bool
{
    // Accept every zone PHP knows, including older names browsers still send (e.g. Asia/Calcutta)
    if ($tz === null || !preg_match('#^[A-Za-z]+(?:/[A-Za-z0-9_+\-]+){1,2}$|^UTC$|^[+-]\d{2}:\d{2}$#', $tz)) {
        return false;
    }
    try {
        new DateTimeZone($tz);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/** The time zone this device reported: its zone name (old names translated), else its UTC offset. */
function device_tz(): ?string
{
    $aliases = [
        'Asia/Calcutta' => 'Asia/Kolkata', 'Asia/Saigon' => 'Asia/Ho_Chi_Minh', 'Asia/Katmandu' => 'Asia/Kathmandu',
        'Asia/Rangoon' => 'Asia/Yangon', 'Asia/Dacca' => 'Asia/Dhaka', 'Europe/Kiev' => 'Europe/Kyiv',
        'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires', 'Pacific/Truk' => 'Pacific/Chuuk',
        'Atlantic/Faeroe' => 'Atlantic/Faroe', 'America/Godthab' => 'America/Nuuk', 'US/Eastern' => 'America/New_York',
        'US/Central' => 'America/Chicago', 'US/Mountain' => 'America/Denver', 'US/Pacific' => 'America/Los_Angeles',
    ];
    $name = (string) ($_COOKIE['tio_tz'] ?? '');
    $name = $aliases[$name] ?? $name;
    if (valid_tz($name)) {
        return $name;
    }
    $mins = $_COOKIE['tio_tzo'] ?? null; // minutes east of UTC, e.g. 330 for India
    if (is_string($mins) && preg_match('/^-?\d{1,4}$/', $mins) && abs((int) $mins) <= 14 * 60) {
        $m = (int) $mins;
        return sprintf('%s%02d:%02d', $m < 0 ? '-' : '+', intdiv(abs($m), 60), abs($m) % 60);
    }
    return null;
}

/**
 * The time zone used for "now" and "today":
 *  1. the school's chosen zone (Branding & settings), or, when set to Automatic,
 *  2. the zone of the device making this request (cookie set by the browser), else
 *  3. the zone last reported by one of the school's devices (used by the email cron), else UTC.
 */
function tenant_tz(): string
{
    $t = tenant();
    if (valid_tz($t['timezone'] ?? '')) {
        return $t['timezone'];
    }
    $device = device_tz();
    if ($device !== null) {
        remember_device_tz($device);
        return $device;
    }
    return valid_tz($t['device_timezone'] ?? '') ? $t['device_timezone'] : 'UTC';
}

/** Store the device's zone so scheduled reports know the school's local day. */
function remember_device_tz(string $tz): void
{
    $t = tenant();
    if (!$t || ($t['device_timezone'] ?? null) === $tz) {
        return;
    }
    try {
        q('UPDATE tenants SET device_timezone = ? WHERE id = ?', [$tz, $t['id']]);
    } catch (PDOException $e) { // installs from before this column existed: add it once
        try {
            q('ALTER TABLE tenants ADD COLUMN device_timezone VARCHAR(64) NULL AFTER timezone');
            q('UPDATE tenants SET device_timezone = ? WHERE id = ?', [$tz, $t['id']]);
        } catch (PDOException $e2) {
            error_log('Could not save device time zone: ' . $e2->getMessage());
        }
    }
    $GLOBALS['tenant']['device_timezone'] = $tz;
}

/** Current date and time for the school (see tenant_tz). */
function tenant_now(): DateTime
{
    return new DateTime('now', new DateTimeZone(tenant_tz()));
}

function tenant_today(): string
{
    return tenant_now()->format('Y-m-d');
}

/** Public link to a tenant portal, according to tenant_url_mode. */
function tenant_url(array $t, string $path = '/'): string
{
    if (!empty($t['custom_domain'])) {
        return scheme() . '://' . $t['custom_domain'] . $path;
    }
    if (cfg('tenant_url_mode') === 'subdomain') {
        return scheme() . '://' . $t['slug'] . '.' . cfg('base_domain') . $path;
    }
    return scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? cfg('base_domain')) . base_path() . '/s/' . $t['slug'] . $path;
}

/** Lowercase letters, numbers and single dashes, as typed in "Choose your own address". */
function clean_slug(string $s): string
{
    $s = preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($s)));
    return substr(trim(preg_replace('/-+/', '-', $s), '-'), 0, 30);
}

/** A free address made from the business name ("South Jordan Kumon" → southjordankumon). */
function suggest_slug(string $org, string $state = ''): string
{
    $words = preg_split('/[^a-z0-9]+/', strtolower($org), -1, PREG_SPLIT_NO_EMPTY);
    $words = array_values(array_filter($words, fn($w) => !in_array($w, ['llc', 'inc', 'ltd', 'co', 'corp', 'the', 'and', 'of', 'pllc', 'lp'], true))) ?: ['my', 'space'];
    $base = substr(implode('', $words), 0, 24);
    if (strlen($base) < 3) {
        $base = str_pad($base, 3, '0');
    }
    $try = [$base];
    if ($state !== '' && isset(US_STATES[strtoupper($state)])) {
        $try[] = $base . strtolower($state);
    }
    for ($i = 2; $i <= 99; $i++) {
        $try[] = $base . $i;
    }
    foreach ($try as $s) {
        if (!slug_problem($s)) {
            return $s;
        }
    }
    return $base . bin2hex(random_bytes(2));
}

function slug_problem(string $slug): ?string
{
    if (!preg_match('/^[a-z0-9]([a-z0-9-]{1,28})[a-z0-9]$/', $slug)) {
        return 'Use 3–30 lowercase letters, numbers or dashes.';
    }
    if (in_array($slug, RESERVED_SLUGS, true)) {
        return 'That address is reserved. Please choose another.';
    }
    if (tenant_by_slug($slug)) {
        return 'That address is already taken.';
    }
    return null;
}

/**
 * Create a new subscriber portal: tenant row, owner login, default materials and
 * kiosk tiles, and (optionally) demo people so they can try it immediately.
 * $d['status'] / $d['trial_days'] override the trial; $d['db'] (host, port, name,
 * user, pass) puts the portal's data in its own, empty database.
 */
function provision_tenant(array $d): array
{
    $dbc = $d['db'] ?? null;
    $data = [
        'name'          => $d['org_name'],
        'slug'          => $d['slug'],
        'plan'          => $d['plan'],
        'status'        => $d['status'] ?? 'trial',
        'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+' . (int) ($d['trial_days'] ?? cfg('trial_days', 7)) . ' days')),
        'timezone'      => $d['timezone'],
        'welcome_title' => 'Welcome to ' . $d['org_name'],
        'welcome_text'  => 'Tap a button below to sign in or out.',
        'report_emails' => $d['email'],
        'industry'      => $d['industry'],
        'state'         => $d['state'] ?: null,
        'city'          => $d['city'] ?: null,
    ];
    if ($dbc) {
        $data += ['db_host' => $dbc['host'] ?: 'localhost', 'db_port' => (int) ($dbc['port'] ?: 3306), 'db_name' => $dbc['name'],
                  'db_user' => $dbc['user'], 'db_pass' => encrypt_pii($dbc['pass'])];
    }
    $tenantId = insert('tenants', $data);
    $t = row('SELECT * FROM tenants WHERE id = ?', [$tenantId]);
    try {
        if ($dbc) {
            tenant_db_install($t);
        }
        with_tenant_db($t, function () use ($tenantId, $d) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                seed_tiles($tenantId, $d['industry']);
                insert('users', [
                    'tenant_id'     => $tenantId,
                    'name'          => $d['admin_name'],
                    'email'         => strtolower($d['email']),
                    'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
                    'role'          => 'owner',
                ]);
                foreach (industry($d['industry'])['items'] as $i => $m) {
                    insert('materials', ['tenant_id' => $tenantId, 'name' => $m, 'sort_order' => $i]);
                }
                if (!empty($d['demo'])) {
                    seed_demo_people($tenantId, $d['industry']);
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
        });
    } catch (Throwable $ex) {
        q('DELETE FROM tenants WHERE id = ?', [$tenantId]); // nothing half-made is left behind
        throw $ex;
    }
    return $t;
}

/** Checks a dedicated database before a portal is put in it. Returns the problem, or null when it can be used. */
function tenant_db_problem(array $c): ?string
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $c['name']) || $c['user'] === '') {
        return 'Enter the database name (letters, numbers and _ only) and its user.';
    }
    if (val('SELECT id FROM tenants WHERE db_name = ? AND COALESCE(db_host, \'localhost\') = ?', [$c['name'], $c['host'] ?: 'localhost'])
        || ($c['name'] === (cfg('db')['name'] ?? '') && ($c['host'] ?: 'localhost') === (cfg('db')['host'] ?? 'localhost'))) {
        return 'That database is already in use. Each portal needs its own, new database.';
    }
    try {
        $pdo = pdo_connect($c);
    } catch (Throwable $e) {
        return 'Could not connect to that database: ' . $e->getMessage();
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn() > 0) {
        return 'That database is not empty. Create a new, empty database for this portal.';
    }
    return null;
}

/** The owner login of a portal, read from the portal's own database. */
function tenant_owner(array $t): ?array
{
    return with_tenant_db($t, fn() => row("SELECT * FROM users WHERE tenant_id = ? AND role = 'owner' ORDER BY id LIMIT 1", [$t['id']]));
}

function seed_demo_people(int $tenantId, string $industry = 'school'): void
{
    $contacts = in_array($industry, ['school', 'tutoring'], true);
    $groups = $contacts ? null : ['Morning shift', 'Day shift', 'Evening shift', 'Day shift', 'Morning shift', 'Evening shift'];
    $students = [
        ['S001', 'Ava', 'Johnson', 'Grade 2', [['Michael Johnson', 'Father', '555-0101'], ['Sarah Johnson', 'Mother', '555-0102']]],
        ['S002', 'Liam', 'Patel', 'Grade 3', [['Raj Patel', 'Father', '555-0111'], ['Priya Patel', 'Mother', '555-0112']]],
        ['S003', 'Mia', 'Garcia', 'Grade 1', [['Carlos Garcia', 'Father', '555-0121'], ['Elena Garcia', 'Mother', '555-0122'], ['Rosa Garcia', 'Grandmother', '555-0123']]],
        ['S004', 'Noah', 'Kim', 'Grade 4', [['Daniel Kim', 'Father', '555-0131'], ['Grace Kim', 'Mother', '555-0132']]],
        ['S005', 'Emma', 'Williams', 'Grade 2', [['Laura Williams', 'Mother', '555-0141']]],
        ['S006', 'Ethan', 'Brown', 'Grade 5', [['James Brown', 'Father', '555-0151'], ['Olivia Brown', 'Mother', '555-0152']]],
    ];
    foreach ($students as [$code, $fn, $ln, $grade, $gs]) {
        $sid = insert('students', ['tenant_id' => $tenantId, 'student_code' => $code, 'first_name' => $fn, 'last_name' => $ln,
            'grade' => $groups ? $groups[(int) substr($code, 1) - 1] : $grade]);
        foreach ($contacts ? $gs : [] as [$gname, $rel, $phone]) {
            insert('guardians', ['tenant_id' => $tenantId, 'student_id' => $sid, 'name' => $gname, 'relationship' => $rel, 'phone' => encrypt_pii($phone)]);
        }
    }
    foreach ([['T001', 'Hannah', 'Lee'], ['T002', 'Robert', 'Miller'], ['T003', 'Aisha', 'Khan']] as [$code, $fn, $ln]) {
        insert('teachers', ['tenant_id' => $tenantId, 'employee_code' => $code, 'first_name' => $fn, 'last_name' => $ln]);
    }
}

// ---------- Deleting a portal ----------
/** Days a deleted portal's data is kept so it can be restored. */
const DELETE_GRACE_DAYS = 30;

/** Close a portal now; its data stays for DELETE_GRACE_DAYS and it can be restored until then. */
function tenant_soft_delete(array $t): void
{
    update('tenants', ['deleted_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$t['id']]);
}

function tenant_restore(array $t): void
{
    update('tenants', ['deleted_at' => null], 'id = ?', [$t['id']]);
}

/**
 * Removes a portal for good: its people, attendance, logins, logs and logo. A portal with its own
 * database is first saved to backups/deleted/, then all its tables are dropped (the empty
 * database itself is removed in hPanel). Payments are kept for your accounts.
 * @return string what was done
 */
function tenant_purge(array $t): string
{
    $note = '';
    if (tenant_db_config($t)) {
        $dir = ROOT_DIR . '/backups/deleted';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
            file_put_contents(ROOT_DIR . '/backups/.htaccess', "Require all denied\n");
        }
        $file = $dir . '/' . $t['slug'] . '_' . $t['db_name'] . '_' . gmdate('Y-m-d_Hi') . '.sql.gz';
        dump_database(tenant_pdo($t), $file);
        with_tenant_db($t, fn() => without_routing(function () {
            db()->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach (db()->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM) as [$table]) {
                db()->exec("DROP TABLE IF EXISTS `$table`");
            }
            db()->exec('SET FOREIGN_KEY_CHECKS=1');
        }));
        $note = 'own database ' . $t['db_name'] . ' emptied (copy saved as backups/deleted/' . basename($file) . '); remove the empty database in hPanel';
    } else {
        q('DELETE FROM audit_log WHERE tenant_id = ?', [$t['id']]);
        $note = 'data removed from the shared database';
    }
    if (!empty($t['logo_path']) && is_file(PUBLIC_DIR . '/' . $t['logo_path'])) {
        @unlink(PUBLIC_DIR . '/' . $t['logo_path']);
    }
    q('DELETE FROM tenants WHERE id = ?', [$t['id']]); // people, attendance, users, tiles… follow (ON DELETE CASCADE)
    return $note;
}

/** Cron: remove portals deleted more than DELETE_GRACE_DAYS ago. */
function purge_expired_tenants(): array
{
    $log = [];
    $GLOBALS['audit_system'] = true;
    foreach (rows('SELECT * FROM tenants WHERE deleted_at IS NOT NULL AND deleted_at < ?', [gmdate('Y-m-d H:i:s', time() - DELETE_GRACE_DAYS * 86400)]) as $t) {
        try {
            $note = tenant_purge($t);
            audit('Portal removed permanently', $t['name'] . ' (' . $t['slug'] . ')', true, DELETE_GRACE_DAYS . ' days after deletion; ' . $note);
            $log[] = $t['slug'] . ': removed permanently';
        } catch (Throwable $e) {
            audit('Portal removed permanently', $t['name'] . ' (' . $t['slug'] . ')', false, $e->getMessage());
            $log[] = $t['slug'] . ': removal FAILED ' . $e->getMessage();
        }
    }
    return $log;
}
