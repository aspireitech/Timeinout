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
        if ((int) ($t['subdomain_ok'] ?? 1) === 1) {
            return scheme() . '://' . $t['slug'] . '.' . cfg('base_domain') . $path;
        }
        // name.<base_domain> is not set up on the host yet: the /s/name address always works
        return scheme() . '://' . cfg('base_domain') . base_path() . '/s/' . $t['slug'] . $path;
    }
    return scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? cfg('base_domain')) . base_path() . '/s/' . $t['slug'] . $path;
}

/** true when https://<slug>.<base_domain> really reaches this app (subdomain and its SSL certificate are set up on the host). */
function subdomain_live(string $slug): bool
{
    if (cfg('tenant_url_mode') !== 'subdomain' || !function_exists('curl_init')) {
        return true;
    }
    $ch = curl_init('https://' . $slug . '.' . cfg('base_domain') . base_path() . '/__ping');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $code === 200 && trim((string) $body) === 'clockinout:' . $slug;
}

/** Checks a portal's subdomain and stores the answer, so links use the subdomain only once it works. */
function subdomain_check(array $t): array
{
    $t['subdomain_ok'] = subdomain_live($t['slug']) ? 1 : 0;
    update('tenants', ['subdomain_ok' => $t['subdomain_ok']], 'id = ?', [$t['id']]);
    return $t;
}

/**
 * Cron: switch portals on /s/name links to their subdomain as soon as it answers, and catch recent
 * portals whose subdomain does not work (e.g. created before this check existed).
 */
function subdomain_recheck(): array
{
    if (cfg('tenant_url_mode') !== 'subdomain') {
        return [];
    }
    $log = [];
    $list = rows("SELECT * FROM tenants WHERE deleted_at IS NULL AND custom_domain IS NULL AND slug <> ?
        AND (subdomain_ok = 0 OR created_at > NOW() - INTERVAL 30 DAY) ORDER BY id LIMIT 40", [DEMO_SLUG]);
    foreach ($list as $t) {
        $ok = subdomain_live($t['slug']) ? 1 : 0;
        if ($ok !== (int) $t['subdomain_ok']) {
            update('tenants', ['subdomain_ok' => $ok], 'id = ?', [$t['id']]);
            $log[] = $t['slug'] . ($ok ? ': subdomain is live, links now use it' : ': subdomain not reachable, links use /s/' . $t['slug']);
            if ($ok) {
                subdomain_live_email($t);
            }
        }
    }
    return $log;
}

/** While name.<domain> is not live: the own address, with the 24-hour note in red (email and welcome page). */
function subdomain_note_html(array $t): string
{
    if (cfg('tenant_url_mode') !== 'subdomain' || (int) ($t['subdomain_ok'] ?? 1) === 1 || !empty($t['custom_domain'])) {
        return '';
    }
    return '<div style="margin-top:8px;font-weight:normal">Your own address: <b>' . e($t['slug'] . '.' . cfg('base_domain')) . '</b><br>'
        . '<span style="color:#C62828;font-weight:bold">will be live within 24 hours</span>. Until then, use the link above: it works now.</div>';
}

/** Tells the provider which subdomain to create on the host for a new portal (it was not live at sign-up). */
function subdomain_todo_email(array $t): void
{
    $to = (string) (cfg('contact_email') ?: (cfg('super_admin')['email'] ?? ''));
    if ($to === '' || (int) ($t['subdomain_ok'] ?? 1) === 1 || cfg('tenant_url_mode') !== 'subdomain') {
        return;
    }
    $host = $t['slug'] . '.' . cfg('base_domain');
    send_mail([$to], 'To do: create subdomain ' . $host, '<p>New portal <b>' . e($t['name']) . '</b> was promised <b>' . e($host) . '</b> within 24 hours.</p>'
        . '<ol><li>hPanel → Websites → ' . e(cfg('base_domain')) . ' → Domains → Subdomains</li><li>Subdomain <b>' . e($t['slug']) . '</b>, tick Custom folder and Use public_html directory → Create</li>'
        . '<li>Security → SSL: make sure ' . e($host) . ' gets a certificate</li></ol>'
        . '<p>Within an hour of it working, links switch to it and the customer is emailed that it is live. Until then they use ' . e(tenant_url($t, '/')) . '.</p>', [], 'contact', alert_copies());
}

/** Tells the owner their own address now works (sent once, by the hourly check). */
function subdomain_live_email(array $t): void
{
    $email = owner_email($t);
    if (!$email) {
        return;
    }
    $url = tenant_url(array_merge($t, ['subdomain_ok' => 1]), '/');
    send_mail([$email], 'Your address ' . $t['slug'] . '.' . cfg('base_domain') . ' is live', '<div style="font-family:Arial,sans-serif;max-width:560px;color:#161B36">'
        . '<h2 style="margin:0 0 8px">Your own address is live</h2><p><b>' . e($t['name']) . '</b> now opens at <a href="' . e($url) . '" style="color:#5B4CE0">' . e($url) . '</a>.</p>'
        . '<p>Update the bookmark on your kiosk tablet. Your old link keeps working too.</p></div>');
}

/** Welcome email to a new portal's owner: address, sign-in, kiosk, trial end and first steps (never the password). */
function welcome_email(array $t, string $email, string $name): bool
{
    $app = cfg('app_name');
    $login = tenant_url($t, '/login?email=' . urlencode($email));
    $kiosk = tenant_url($t, '/');
    $ends = !empty($t['trial_ends_at']) ? date('F j, Y', strtotime((string) $t['trial_ends_at'])) : null;
    $plan = plans()[$t['plan']]['name'] ?? ucfirst((string) $t['plan']);
    $row = fn($k, $v) => '<tr><td style="padding:8px 12px;color:#6b7090;white-space:nowrap">' . $k . '</td><td style="padding:8px 12px;font-weight:600">' . $v . '</td></tr>';
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#161B36">'
        . '<h2 style="margin:0 0 6px">Welcome to ' . e($app) . ', ' . e($name) . '!</h2>'
        . '<p>Your space <b>' . e($t['name']) . '</b> is ready. Save this email: it has everything you need to get started.</p>'
        . '<table style="border-collapse:collapse;width:100%;background:#F4F5FA;border-radius:12px;margin:14px 0">'
        . $row('Your address', '<a href="' . e($kiosk) . '" style="color:#5B4CE0">' . e($kiosk) . '</a>' . subdomain_note_html($t))
        . $row('Admin sign-in', '<a href="' . e($login) . '" style="color:#5B4CE0">' . e(tenant_url($t, '/login')) . '</a>')
        . $row('Your login', e($email) . ' and the password you chose')
        . $row('Plan', e($plan) . ($ends ? ', free trial until ' . e($ends) : ''))
        . '</table>'
        . '<p style="margin:18px 0 6px"><b>Get started in three steps</b></p>'
        . '<ol style="margin:0 0 14px;padding-left:20px;line-height:1.6">'
        . '<li>Sign in and add your people: <b>Import</b> uploads your Excel or CSV list.</li>'
        . '<li>Open your address on the tablet at your front door: that is your kiosk.</li>'
        . '<li>In <b>Reports</b>, add who should get the daily report at 7:00.</li></ol>'
        . '<p><a href="' . e($login) . '" style="display:inline-block;background:#5B4CE0;color:#fff;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:bold">Sign in to my space</a></p>'
        . '<p style="color:#6b7090;font-size:13px">Each sign-in is confirmed with a code we email you. Choose a plan any time in Admin → Billing. Questions? Just reply to this email.</p></div>';
    return send_mail([$email], 'Welcome to ' . $app . ': ' . $t['name'] . ' is ready', $html);
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
