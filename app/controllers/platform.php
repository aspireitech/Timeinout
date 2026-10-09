<?php
// The main product site (abc.com): landing page, sign-up + automatic
// provisioning, and the platform owner's /super console.

function platform_home(): void
{
    view('platform/home', ['plans' => cfg('plans', []), 'demo' => demo_tenant()], 'platform');
}

function platform_check_slug(): void
{
    $slug = slugify((string) input('slug'));
    json_out(['slug' => $slug, 'error' => slug_problem($slug)]);
}

function platform_signup(): void
{
    $plans = cfg('plans', []);
    $old = ['org_name' => '', 'slug' => '', 'admin_name' => '', 'email' => '', 'plan' => (string) input('plan', array_key_first($plans)), 'timezone' => '', 'demo' => '1'];
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
        if (!valid_tz($d['timezone'])) {
            $d['timezone'] = ''; // Automatic
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
    if (input('nosession') === '1') {
        $error = 'Login worked, but your browser did not keep the session. Open the site with https:// '
               . '(or set force_https to false in app/config.php if the site has no SSL) and try again.';
    }
    if (input('timeout') === '1') {
        $error = 'You were signed out after 30 minutes without activity.';
    }
    if (is_post()) {
        $error = super_check((string) input('email'), (string) ($_POST['password'] ?? ''));
        if ($error === null) {
            redirect(base_path() . '/super?li=1');
        }
        if ($error === 'otp') {
            redirect(base_path() . '/super/verify');
        }
    }
    view('platform/super_login', ['error' => $error], 'platform');
}

function super_verify(): void
{
    $o = otp_pending('super');
    if (!$o) {
        redirect(base_path() . '/super/login');
    }
    $error = $info = null;
    if (is_post()) {
        if (input('resend') === '1') {
            $info = otp_resend('super');
        } elseif (($error = otp_check('super', (string) input('code'))) === null) {
            super_complete($o['who']['email']);
            redirect(base_path() . '/super?li=1');
        } elseif (!otp_pending('super')) {
            redirect(base_path() . '/super/login');
        }
    }
    view('auth/verify', ['error' => $error, 'info' => $info, 'email' => $o['who']['email'], 'demoCode' => null,
        'action' => base_path() . '/super/verify', 'back' => base_path() . '/super/login'], 'platform');
}

function super_logout(): void
{
    if (!empty($_SESSION['super'])) {
        audit('Provider sign-out', cfg('super_admin')['email'] ?? null, true);
    }
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
    $t = row('SELECT * FROM tenants WHERE id = ?', [$id]);
    audit_note($t ? $t['name'] . ' (' . $t['slug'] . ')' : "#$id", $t ? audit_diff($t, ['status' => $status, 'plan' => $plan]) : null);
    if (in_array($status, ['trial', 'active', 'suspended', 'cancelled'], true) && isset(cfg('plans', [])[$plan])) {
        update('tenants', ['status' => $status, 'plan' => $plan], 'id = ?', [$id]);
        flash('success', 'Subscriber updated.');
    }
    redirect(base_path() . '/super');
}

function super_seed_demo(): void
{
    require_super();
    $r = seed_demo_tenant();
    audit_note('Bright Future Academy (demo)', "{$r['students']} students, {$r['teachers']} teachers, {$r['events']} check-ins");
    flash('success', "Demo portal ready: {$r['students']} students, {$r['teachers']} teachers, {$r['events']} check-ins. Log in as admin@demo.com / Demo@1234.");
    redirect(base_path() . '/super');
}

// ---------- Provider settings: email (SMTP) and security switches ----------
function super_settings(): void
{
    require_super();
    $m = mail_config();
    if (is_post()) {
        if (input('action') === 'test') {
            $to = trim((string) input('test_to')) ?: (cfg('super_admin')['email'] ?? '');
            $ok = send_mail([$to], cfg('app_name') . ' test email', '<p>Your email settings work. Reports and sign-in codes can be delivered.</p>');
            audit('Sent test email', $to, $ok, $ok ? 'Delivered to the mail server' : $GLOBALS['mail_error']);
            flash($ok ? 'success' : 'error', $ok ? "Test email sent to $to. Check the inbox (and spam folder)." : 'Test failed: ' . $GLOBALS['mail_error']);
            redirect(base_path() . '/super/settings');
        }
        $before = ['driver' => $m['driver'] ?? '', 'host' => $m['host'] ?? '', 'port' => $m['port'] ?? '', 'secure' => $m['secure'] ?? '', 'user' => $m['user'] ?? '',
            'from_email' => $m['from_email'] ?? '', 'from_name' => $m['from_name'] ?? '', 'admin_otp' => setting('admin_otp', '1'), 'super_otp' => setting('super_otp', '0')];
        $new = [
            'driver'     => input('driver') === 'mail' ? 'mail' : 'smtp',
            'host'       => mb_substr((string) input('host'), 0, 190),
            'port'       => (string) max(1, min(65535, (int) input('port', '587'))),
            'secure'     => in_array(input('secure'), ['tls', 'ssl', ''], true) ? (string) input('secure') : 'tls',
            'user'       => mb_substr((string) input('user'), 0, 190),
            'from_email' => filter_var(input('from_email'), FILTER_VALIDATE_EMAIL) ? (string) input('from_email') : ($m['from_email'] ?? ''),
            'from_name'  => mb_substr((string) input('from_name'), 0, 120),
            'admin_otp'  => input('admin_otp') === '1' ? '1' : '0',
            'super_otp'  => input('super_otp') === '1' ? '1' : '0',
        ];
        foreach (['driver', 'host', 'port', 'secure', 'user', 'from_email', 'from_name'] as $k) {
            setting_set('mail_' . $k, $new[$k]);
        }
        $passChanged = ($_POST['pass'] ?? '') !== '';
        if ($passChanged) { // the SMTP password is stored encrypted
            setting_set('mail_pass', encrypt_pii((string) $_POST['pass']));
        }
        setting_set('admin_otp', $new['admin_otp']);
        setting_set('super_otp', $new['super_otp']);
        audit_note('Email & security settings', audit_diff($before, $new) . ($passChanged ? '; SMTP password changed' : ''));
        flash('success', 'Settings saved. Use "Send test email" to check them.');
        redirect(base_path() . '/super/settings');
    }
    view('platform/super_settings', ['m' => $m, 'hasPass' => (bool) (setting('mail_pass') || ($m['pass'] ?? ''))], 'platform');
}

function super_audit(): void
{
    require_super();
    [$f, $where, $p] = audit_filters(null);
    $tenant = (int) input('tenant');
    if ($tenant) {
        $where .= ' AND a.tenant_id = ?';
        $p[] = $tenant;
    }
    $where = preg_replace('/\b(created_at|result|action|target|details|user_email|user_name)\b/', 'a.$1', $where);
    $sql = "SELECT a.*, t.name AS school FROM audit_log a LEFT JOIN tenants t ON t.id = a.tenant_id WHERE $where ORDER BY a.id DESC";
    if (input('export') === 'csv') {
        send_download('audit-all-' . $f['from'] . '.csv', 'text/csv; charset=utf-8', csv_string(audit_csv_rows(rows("$sql LIMIT 50000", $p), true)));
    }
    $page = max(1, (int) input('page', '1'));
    $total = (int) val("SELECT COUNT(*) FROM audit_log a WHERE $where", $p);
    $logs = rows("$sql LIMIT 100 OFFSET " . (($page - 1) * 100), $p);
    $tenants = rows('SELECT id, name FROM tenants ORDER BY name');
    view('platform/super_audit', compact('f', 'logs', 'total', 'page', 'tenants', 'tenant'), 'platform');
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
    if (input('task') === 'demo') { // https://yourdomain/cron?key=YOUR_CRON_KEY&task=demo
        $r = seed_demo_tenant();
        echo "Demo portal ready: {$r['students']} students, {$r['teachers']} teachers, {$r['events']} check-ins.\n";
        foreach (DEMO_LOGINS as [, , $email, $pass]) {
            echo "$email / $pass\n";
        }
        return;
    }
    echo implode("\n", run_scheduled_reports()) ?: 'Nothing due.';
}
