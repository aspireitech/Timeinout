<?php
// The main product site (abc.com): landing page, sign-up + automatic
// provisioning, and the platform owner's /super console.

function platform_home(): void
{
    view('platform/home', ['plans' => cfg('plans', [])], 'platform');
}

function platform_check_slug(): void
{
    $slug = slugify((string) input('slug'));
    json_out(['slug' => $slug, 'error' => slug_problem($slug)]);
}

function platform_signup(): void
{
    $plans = cfg('plans', []);
    $old = ['org_name' => '', 'slug' => '', 'admin_name' => '', 'email' => '', 'plan' => (string) input('plan', array_key_first($plans)), 'timezone' => 'America/New_York', 'demo' => '1'];
    $errors = [];

    if (is_post()) {
        $d = [
            'org_name'   => mb_substr((string) input('org_name'), 0, 120),
            'slug'       => slugify((string) input('slug')),
            'admin_name' => mb_substr((string) input('admin_name'), 0, 120),
            'email'      => strtolower((string) input('email')),
            'password'   => (string) ($_POST['password'] ?? ''),
            'plan'       => (string) input('plan'),
            'timezone'   => (string) input('timezone'),
            'demo'       => input('demo') === '1',
        ];
        if ($d['org_name'] === '') {
            $errors['org_name'] = 'Please enter your school or company name.';
        }
        if ($p = slug_problem($d['slug'])) {
            $errors['slug'] = $p;
        }
        if ($d['admin_name'] === '') {
            $errors['admin_name'] = 'Please enter your name.';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email.';
        }
        if (strlen($d['password']) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        }
        if (!isset($plans[$d['plan']])) {
            $d['plan'] = (string) array_key_first($plans);
        }
        if (!in_array($d['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $d['timezone'] = 'UTC';
        }

        if (!$errors) {
            $t = provision_tenant($d);
            view('platform/welcome', ['t' => $t, 'email' => $d['email']], 'platform');
        }
        $old = array_merge($old, $d, ['demo' => $d['demo'] ? '1' : '']);
    }
    view('platform/signup', ['plans' => $plans, 'old' => $old, 'errors' => $errors], 'platform');
}

// ---------- Platform owner console ----------
function super_login(): void
{
    $error = null;
    if (is_post()) {
        if (super_check((string) input('email'), (string) ($_POST['password'] ?? ''))) {
            redirect(base_path() . '/super');
        }
        $error = 'Wrong email or password.';
    }
    view('platform/super_login', ['error' => $error], 'platform');
}

function super_logout(): void
{
    unset($_SESSION['super']);
    redirect(base_path() . '/');
}

function super_tenants(): void
{
    require_super();
    $tenants = rows(
        'SELECT t.*,
            (SELECT COUNT(*) FROM students s WHERE s.tenant_id = t.id) AS students,
            (SELECT COUNT(*) FROM teachers x WHERE x.tenant_id = t.id) AS teachers,
            (SELECT COUNT(*) FROM attendance a WHERE a.tenant_id = t.id AND a.event_date >= CURDATE() - INTERVAL 30 DAY) AS events30,
            (SELECT email FROM users u WHERE u.tenant_id = t.id AND u.role = "owner" ORDER BY u.id LIMIT 1) AS owner_email
         FROM tenants t ORDER BY t.created_at DESC'
    );
    view('platform/super_tenants', ['tenants' => $tenants], 'platform');
}

function super_update_tenant(int $id): void
{
    require_super();
    $status = (string) input('status');
    $plan = (string) input('plan');
    if (in_array($status, ['trial', 'active', 'suspended', 'cancelled'], true) && isset(cfg('plans', [])[$plan])) {
        update('tenants', ['status' => $status, 'plan' => $plan], 'id = ?', [$id]);
        flash('success', 'Subscriber updated.');
    }
    redirect(base_path() . '/super');
}

// For hosts without cron jobs: call https://abc.com/cron?key=YOUR_KEY hourly
// from any free "web cron" service.
function web_cron(): void
{
    $key = (string) cfg('cron_key', '');
    if ($key === '' || $key === 'replace-with-a-long-random-string' || !hash_equals($key, (string) input('key'))) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain');
    echo implode("\n", run_scheduled_reports()) ?: 'Nothing due.';
}
