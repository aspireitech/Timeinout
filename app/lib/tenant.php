<?php
// Multi-tenancy: one codebase + one database. Each subscriber ("tenant") is a
// row in `tenants`; every other table carries tenant_id. A request is mapped
// to a tenant by, in order:
//   1. path      https://abc.com/s/xyz/...
//   2. subdomain https://xyz.abc.com/...
//   3. custom    https://checkin.xyzschool.org/... (tenants.custom_domain)
// Anything else (abc.com, www.abc.com, localhost) is the marketing/platform site.

const RESERVED_SLUGS = ['www', 'admin', 'api', 'app', 'mail', 'ftp', 'cpanel', 'webmail', 'super', 's', 'static', 'assets', 'support', 'help', 'billing', 'demo', 'demo-admin'];

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

/** Today's date in the tenant's own timezone. */
function tenant_now(): DateTime
{
    $tz = tenant()['timezone'] ?? 'UTC';
    try {
        return new DateTime('now', new DateTimeZone($tz));
    } catch (Exception $e) {
        return new DateTime('now', new DateTimeZone('UTC'));
    }
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
 * Create a new subscriber portal: tenant row, owner login, default
 * materials, and (optionally) demo people so they can try it immediately.
 */
function provision_tenant(array $d): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $tenantId = insert('tenants', [
            'name'          => $d['org_name'],
            'slug'          => $d['slug'],
            'plan'          => $d['plan'],
            'status'        => 'trial',
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+' . (int) cfg('trial_days', 30) . ' days')),
            'timezone'      => $d['timezone'],
            'welcome_title' => 'Welcome to ' . $d['org_name'],
            'welcome_text'  => 'Tap below to sign in, sign out or pick up materials.',
            'report_emails' => $d['email'],
        ]);
        insert('users', [
            'tenant_id'     => $tenantId,
            'name'          => $d['admin_name'],
            'email'         => strtolower($d['email']),
            'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
            'role'          => 'owner',
        ]);
        foreach (['Homework folder', 'Books', 'Uniform', 'Lunch box', 'Art project'] as $i => $m) {
            insert('materials', ['tenant_id' => $tenantId, 'name' => $m, 'sort_order' => $i]);
        }
        if (!empty($d['demo'])) {
            seed_demo_people($tenantId);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return row('SELECT * FROM tenants WHERE id = ?', [$tenantId]);
}

function seed_demo_people(int $tenantId): void
{
    $students = [
        ['S001', 'Ava', 'Johnson', 'Grade 2', [['Michael Johnson', 'Father', '555-0101'], ['Sarah Johnson', 'Mother', '555-0102']]],
        ['S002', 'Liam', 'Patel', 'Grade 3', [['Raj Patel', 'Father', '555-0111'], ['Priya Patel', 'Mother', '555-0112']]],
        ['S003', 'Mia', 'Garcia', 'Grade 1', [['Carlos Garcia', 'Father', '555-0121'], ['Elena Garcia', 'Mother', '555-0122'], ['Rosa Garcia', 'Grandmother', '555-0123']]],
        ['S004', 'Noah', 'Kim', 'Grade 4', [['Daniel Kim', 'Father', '555-0131'], ['Grace Kim', 'Mother', '555-0132']]],
        ['S005', 'Emma', 'Williams', 'Grade 2', [['Laura Williams', 'Mother', '555-0141']]],
        ['S006', 'Ethan', 'Brown', 'Grade 5', [['James Brown', 'Father', '555-0151'], ['Olivia Brown', 'Mother', '555-0152']]],
    ];
    foreach ($students as [$code, $fn, $ln, $grade, $gs]) {
        $sid = insert('students', ['tenant_id' => $tenantId, 'student_code' => $code, 'first_name' => $fn, 'last_name' => $ln, 'grade' => $grade]);
        foreach ($gs as [$gname, $rel, $phone]) {
            insert('guardians', ['tenant_id' => $tenantId, 'student_id' => $sid, 'name' => $gname, 'relationship' => $rel, 'phone' => $phone]);
        }
    }
    foreach ([['T001', 'Hannah', 'Lee'], ['T002', 'Robert', 'Miller'], ['T003', 'Aisha', 'Khan']] as [$code, $fn, $ln]) {
        insert('teachers', ['tenant_id' => $tenantId, 'employee_code' => $code, 'first_name' => $fn, 'last_name' => $ln]);
    }
}
