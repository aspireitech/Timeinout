<?php
// The main product site (abc.com): landing page, sign-up + automatic
// provisioning, and the platform owner's /super console.

function platform_home(): void
{
    view('platform/home', ['plans' => plans(), 'demo' => demo_tenant()], 'home');
}

/** "Sign in" on the main site: find a business's own space by its address. */
function platform_find_login(): void
{
    $error = null;
    if (is_post()) {
        $slug = preg_replace('#^https?://|/.*$|\..*$#', '', strtolower(trim((string) input('space'))));
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        $t = $slug !== '' ? tenant_by_slug($slug) : null;
        if ($t) {
            redirect(tenant_url($t, '/login'));
        }
        $error = 'No space found with that address. Check the spelling, or ask your administrator for the link.';
    }
    view('platform/find_login', ['error' => $error], 'platform');
}

function platform_check_slug(): void
{
    [$slug, $err] = build_slug((string) input('state'), (string) input('city'), (string) input('name'));
    json_out(['slug' => $slug, 'url' => $slug ? preview_url($slug) : '', 'error' => $err ?? ($slug ? slug_problem($slug) : null)]);
}

function preview_url(string $slug): string
{
    return cfg('tenant_url_mode') === 'subdomain' ? $slug . '.' . cfg('base_domain') : cfg('base_domain') . '/s/' . $slug;
}

function platform_signup(): void
{
    $plans = plans();
    $old = ['org_name' => '', 'industry' => (string) input('industry', 'office'), 'state' => '', 'city' => '', 'short' => '', 'admin_name' => '',
        'email' => '', 'plan' => (string) input('plan', array_key_first($plans)), 'timezone' => '', 'demo' => '1'];
    $errors = [];

    if (is_post()) {
        $d = [
            'org_name'   => mb_substr(trim((string) input('org_name')), 0, 120),
            'industry'   => array_key_exists((string) input('industry'), industries()) ? (string) input('industry') : 'office',
            'state'      => strtoupper((string) input('state')),
            'city'       => mb_substr(trim((string) input('city')), 0, 40),
            'short'      => (string) input('short'),
            'admin_name' => mb_substr(trim((string) input('admin_name')), 0, 120),
            'email'      => strtolower(trim((string) input('email'))),
            'password'   => (string) ($_POST['password'] ?? ''),
            'plan'       => (string) input('plan'),
            'timezone'   => (string) input('timezone'),
            'demo'       => input('demo') === '1',
        ];
        if ($d['org_name'] === '') {
            $errors['org_name'] = 'Please enter your business name.';
        }
        if (!isset(US_STATES[$d['state']]) && $d['state'] !== 'XX') {
            $errors['state'] = 'Please choose your state.';
        }
        [$d['slug'], $slugErr] = build_slug($d['state'] === 'XX' ? '' : $d['state'], $d['city'], $d['short']);
        if ($p = $slugErr ?? slug_problem($d['slug'])) {
            $errors['short'] = $p;
        }
        if ($d['admin_name'] === '') {
            $errors['admin_name'] = 'Please enter your name.';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email.';
        }
        if (strlen($d['password']) < 10 || !preg_match('/[A-Za-z]/', $d['password']) || !preg_match('/\d/', $d['password'])) {
            $errors['password'] = 'Use at least 10 characters, with letters and a number.';
        }
        if (!isset($plans[$d['plan']])) {
            $d['plan'] = (string) array_key_first($plans);
        }
        if (!valid_tz($d['timezone'])) {
            $d['timezone'] = ''; // Automatic
        }
        if ($d['state'] === 'XX') {
            $d['state'] = '';
        }
        if (!$errors) {
            $t = provision_tenant($d);
            audit('New sign-up', $t['name'] . ' (' . $t['slug'] . ')', true, industry($d['industry'])['name'] . ', ' . $d['plan'] . ' plan, owner ' . $d['email'],
                ['email' => $d['email'], 'name' => $d['admin_name'], 'tenant_id' => (int) $t['id']]);
            view('platform/welcome', ['t' => $t, 'email' => $d['email']], 'platform');
        }
        $old = array_merge($old, $d, ['demo' => $d['demo'] ? '1' : '', 'state' => $d['state'] ?: (input('state') === 'XX' ? 'XX' : '')]);
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
    view('platform/super_login', ['error' => $error, 'google' => google_ready()], 'platform');
}

function super_verify(): void
{
    $o = otp_pending('super');
    if (!$o) {
        redirect(base_path() . '/super/login');
    }
    $error = $info = null;
    if (!empty($o['mail_failed'])) {
        $info = 'The sign-in code email could not be sent. Use one of your recovery codes below.';
    }
    if (is_post()) {
        if (input('recovery') !== '') {
            $who = $o['who'];
            if (login_blocked('super-recovery', $who['email'])) {
                $error = 'Too many wrong recovery codes. Please wait 15 minutes.';
            } elseif (super_recovery_use((string) input('recovery'))) {
                login_attempt('super-recovery', $who['email'], true);
                audit('Provider sign-in with recovery code', $who['email'], true, super_recovery_left() . ' recovery codes left', $who);
                super_complete($who['email']);
                send_mail([(string) (cfg('super_admin')['email'] ?? '')], cfg('app_name') . ': a recovery code was used',
                    '<p>A recovery code was used to sign in to the provider console on ' . gmdate('M j, Y H:i') . ' UTC from ' . e(client_ip()) . '. '
                    . super_recovery_left() . ' codes are left. If this was not you, change the provider password and create new recovery codes now.</p>');
                redirect(base_path() . '/super?li=1');
            } else {
                login_attempt('super-recovery', $who['email'], false);
                audit('Provider sign-in with recovery code', $who['email'], false, 'Wrong or used code', $who);
                $error = 'That recovery code is not valid (each code works once).';
            }
        } elseif (input('resend') === '1') {
            $info = otp_resend('super');
        } elseif (!empty($o['mail_failed'])) {
            $error = 'No code was emailed. Use a recovery code.';
        } elseif (($error = otp_check('super', (string) input('code'))) === null) {
            super_complete($o['who']['email']);
            redirect(base_path() . '/super?li=1');
        } elseif (!otp_pending('super')) {
            redirect(base_path() . '/super/login');
        }
    }
    view('auth/verify', ['error' => $error, 'info' => $info, 'email' => $o['who']['email'], 'demoCode' => null, 'recovery' => true,
        'action' => base_path() . '/super/verify', 'back' => base_path() . '/super/login'], 'platform');
}

/** Provider password (stored hashed in the database) and recovery codes. */
function super_security(): void
{
    require_super();
    $codes = null;
    if (is_post()) {
        if (input('action') === 'password') {
            $new = (string) ($_POST['new_password'] ?? '');
            if (!super_password_ok((string) ($_POST['current_password'] ?? ''))) {
                audit_note('Provider password', 'Not changed: current password wrong');
                flash('error', 'Your current password is not correct.');
            } elseif ($new !== (string) ($_POST['confirm_password'] ?? '')) {
                flash('error', 'The two new passwords do not match.');
            } elseif ($p = super_password_problem($new)) {
                flash('error', $p);
            } else {
                super_password_set($new);
                audit_note('Provider password', 'Changed; stored hashed in the database');
                flash('success', 'Password saved (hashed in the database). You can now remove the password from app/config.php.');
            }
        } elseif (input('action') === 'recovery') {
            $codes = super_recovery_generate();
            audit_note('Recovery codes', '10 new codes created; old codes no longer work');
        }
    }
    view('platform/super_security', ['codes' => $codes, 'left' => super_recovery_left(), 'dbPassword' => (string) setting('super_password_hash', '') !== '',
        'configPassword' => trim((string) (cfg('super_admin')['password'] ?? '')) !== ''], 'platform');
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
            (SELECT email FROM users u WHERE u.tenant_id = t.id AND u.role = "owner" ORDER BY u.id LIMIT 1) AS owner_email,
            (SELECT COALESCE(SUM(amount_cents),0) FROM payments p WHERE p.tenant_id = t.id AND p.status = "paid") AS paid_total
         FROM tenants t ORDER BY t.created_at DESC'
    );
    // Portals with their own database: read their numbers from there
    foreach ($tenants as &$t) {
        if (!tenant_db_config($t)) {
            continue;
        }
        try {
            with_tenant_db($t, function () use (&$t) {
                $t['students'] = val('SELECT COUNT(*) FROM students WHERE tenant_id = ?', [$t['id']]);
                $t['teachers'] = val('SELECT COUNT(*) FROM teachers WHERE tenant_id = ?', [$t['id']]);
                $t['events30'] = val('SELECT COUNT(*) FROM attendance WHERE tenant_id = ? AND event_date >= CURDATE() - INTERVAL 30 DAY', [$t['id']]);
                $t['owner_email'] = val("SELECT email FROM users WHERE tenant_id = ? AND role = 'owner' ORDER BY id LIMIT 1", [$t['id']]);
            });
        } catch (Throwable $e) {
            $t['db_error'] = $e->getMessage();
        }
    }
    unset($t);
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
            $sender = input('sender') === 'reports' ? 'reports' : 'contact';
            $from = mail_config($sender)['from_email'] ?? $sender;
            $ok = send_mail([$to], cfg('app_name') . ' test email from ' . $from, '<p>Your email settings work. This message came from <b>' . e($from) . '</b>.</p>', [], $sender);
            audit('Sent test email', $to, $ok, ($ok ? 'Delivered to the mail server' : $GLOBALS['mail_error']) . ' (from ' . $from . ')');
            flash($ok ? 'success' : 'error', $ok ? "Test email sent from $from to $to. Check the inbox (and spam folder)." : "Test from $from failed: " . $GLOBALS['mail_error']);
            redirect(base_path() . '/super/settings');
        }
        $before = ['driver' => $m['driver'] ?? '', 'host' => $m['host'] ?? '', 'port' => $m['port'] ?? '', 'secure' => $m['secure'] ?? '', 'user' => $m['user'] ?? '',
            'from_email' => $m['from_email'] ?? '', 'from_name' => $m['from_name'] ?? '', 'admin_otp' => setting('admin_otp', '1')];
        $new = [
            'driver'     => in_array(input('driver'), ['mail', 'smtp', 'graph'], true) ? (string) input('driver') : 'smtp',
            'host'       => mb_substr((string) input('host'), 0, 190),
            'port'       => (string) max(1, min(65535, (int) input('port', '587'))),
            'secure'     => in_array(input('secure'), ['tls', 'ssl', ''], true) ? (string) input('secure') : 'tls',
            'user'       => mb_substr((string) input('user'), 0, 190),
            'from_email' => filter_var(input('from_email'), FILTER_VALIDATE_EMAIL) ? (string) input('from_email') : ($m['from_email'] ?? ''),
            'from_name'  => mb_substr((string) input('from_name'), 0, 120),
            'admin_otp'  => input('admin_otp') === '1' ? '1' : '0',
        ];
        foreach (['driver', 'host', 'port', 'secure', 'user', 'from_email', 'from_name'] as $k) {
            setting_set('mail_' . $k, $new[$k]);
        }
        $passChanged = ($_POST['pass'] ?? '') !== '';
        if ($passChanged) { // the SMTP password is stored encrypted
            setting_set('mail_pass', encrypt_pii((string) $_POST['pass']));
        }
        // Payments: keys are stored encrypted; leave a key field empty to keep the saved one
        foreach (['stripe_secret', 'stripe_webhook_secret', 'wave_token'] as $k) {
            if (trim((string) ($_POST[$k] ?? '')) !== '') {
                setting_set($k, encrypt_pii(trim((string) $_POST[$k])));
                $passChanged = true;
            }
        }
        setting_set('wave_business_id', mb_substr(trim((string) input('wave_business_id')), 0, 190));
        foreach (array_keys(plans()) as $pk) {
            setting_set('stripe_price_' . $pk, mb_substr(trim((string) input('stripe_price_' . $pk)), 0, 120));
            setting_set('wave_product_' . $pk, mb_substr(trim((string) input('wave_product_' . $pk)), 0, 190));
        }
        setting_set('admin_otp', $new['admin_otp']);
        audit_note('Email, security & payment settings', audit_diff($before, $new) . ($passChanged ? '; a password or API key was changed' : ''));
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
    echo implode("\n", array_merge(run_scheduled_reports(), wave_sync(), purge_expired_tenants())) ?: 'Nothing due.';
}

// ---------- Payments ----------
/** Stripe calls this after checkouts, renewals, failed cards and cancellations. */
function webhook_stripe(): void
{
    $payload = (string) file_get_contents('php://input');
    $secret = secret('stripe_webhook_secret');
    $GLOBALS['audit_system'] = true;
    if ($secret === '' || !stripe_signature_ok($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret)) {
        audit('Stripe webhook rejected', null, false, 'Signature missing or wrong. Check the webhook signing secret.');
        json_out(['error' => 'bad signature'], 400);
    }
    $event = json_decode($payload, true) ?: [];
    try {
        $msg = stripe_handle_event($event);
        audit('Stripe: ' . ($event['type'] ?? 'event'), $msg, true, 'Event ' . ($event['id'] ?? ''));
        json_out(['ok' => true]);
    } catch (Throwable $e) {
        audit('Stripe: ' . ($event['type'] ?? 'event'), null, false, $e->getMessage());
        json_out(['error' => 'failed'], 500); // Stripe retries later
    }
}

function super_payments(): void
{
    require_super();
    if (is_post()) { // record a cheque, bank transfer or cash payment
        $t = row('SELECT * FROM tenants WHERE id = ?', [(int) input('tenant_id')]);
        $amount = (int) round((float) input('amount') * 100);
        if (!$t || $amount <= 0) {
            flash('error', 'Choose a subscriber and an amount.');
        } else {
            $months = max(1, min(24, (int) input('months', '1')));
            $from = $t['current_period_end'] && strtotime($t['current_period_end']) > time() ? $t['current_period_end'] : gmdate('Y-m-d');
            $end = gmdate('Y-m-d', strtotime("$from +$months month"));
            record_payment(['tenant_id' => $t['id'], 'provider' => 'manual', 'external_id' => 'manual-' . bin2hex(random_bytes(6)), 'amount_cents' => $amount,
                'status' => 'paid', 'description' => mb_substr(trim((string) input('note')) ?: 'Manual payment', 0, 255),
                'period_start' => substr($from, 0, 10), 'period_end' => $end, 'paid_at' => gmdate('Y-m-d H:i:s')]);
            update('tenants', ['status' => 'active', 'subscription_status' => 'active', 'current_period_end' => $end . ' 23:59:59',
                'billing_provider' => $t['billing_provider'] ?: 'manual'], 'id = ?', [$t['id']]);
            audit('Recorded manual payment', $t['name'], true, money($amount) . " for $months month(s), paid through $end");
            flash('success', 'Payment recorded and ' . $t['name'] . ' is active through ' . date('M j, Y', strtotime($end)) . '.');
        }
        redirect(base_path() . '/super/payments');
    }
    $tenant = (int) input('tenant');
    $where = '1=1';
    $p = [];
    if ($tenant) {
        $where .= ' AND p.tenant_id = ?';
        $p[] = $tenant;
    }
    if (in_array(input('provider'), ['stripe', 'wave', 'manual'], true)) {
        $where .= ' AND p.provider = ?';
        $p[] = input('provider');
    }
    $payments = rows("SELECT p.*, t.name AS school FROM payments p LEFT JOIN tenants t ON t.id = p.tenant_id WHERE $where ORDER BY p.created_at DESC LIMIT 500", $p);
    if (input('export') === 'csv') {
        $out = [['Date (UTC)', 'Subscriber', 'Method', 'Description', 'Amount', 'Currency', 'Status', 'Period start', 'Period end', 'Invoice']];
        foreach ($payments as $x) {
            $out[] = [$x['paid_at'] ?: $x['created_at'], $x['school'], $x['provider'], $x['description'], number_format($x['amount_cents'] / 100, 2, '.', ''), $x['currency'], $x['status'], $x['period_start'], $x['period_end'], $x['invoice_url']];
        }
        send_download('payments.csv', 'text/csv; charset=utf-8', csv_string($out));
    }
    $plans = plans();
    $stats = [
        'month' => (int) val("SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE status = 'paid' AND paid_at >= ?", [gmdate('Y-m-01 00:00:00')]),
        'd30'   => (int) val("SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE status = 'paid' AND paid_at >= ?", [gmdate('Y-m-d H:i:s', strtotime('-30 days'))]),
        'all'   => (int) val("SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE status = 'paid'"),
        'open'  => (int) val("SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE status = 'open'"),
        'paying' => 0, 'mrr' => 0.0,
    ];
    foreach (rows("SELECT plan FROM tenants WHERE status = 'active' AND billing_provider IN ('stripe','wave','manual') AND slug <> ?", [DEMO_SLUG]) as $x) {
        $stats['paying']++;
        $stats['mrr'] += $plans[$x['plan']]['amount'] ?? 0;
    }
    view('platform/super_payments', ['payments' => $payments, 'stats' => $stats, 'tenants' => rows('SELECT id, name FROM tenants ORDER BY name'), 'tenant' => $tenant], 'platform');
}

// ---------- Sign in with Google (provider console) ----------
function super_google_start(): void
{
    if (!google_ready()) {
        flash('error', 'Sign in with Google is not set up yet. Use your email and password.');
        redirect(base_path() . '/super/login');
    }
    redirect(google_auth_url());
}

function super_google_callback(): void
{
    try {
        $r = google_callback($_GET);
    } catch (Throwable $e) {
        audit('Provider sign-in (Google)', null, false, $e->getMessage());
        $r = 'Google sign-in did not work (' . $e->getMessage() . ').';
    }
    if ($r === 'otp') {
        redirect(base_path() . '/super/verify');
    }
    if ($r === 'done') {
        redirect(base_path() . '/super?li=1');
    }
    flash('error', $r . ' You can sign in with your email and password instead.');
    redirect(base_path() . '/super/login');
}

// ---------- Create a portal for a customer (provider console) ----------
function super_portal_new(): void
{
    require_super();
    $plans = plans();
    $old = ['org_name' => '', 'slug' => '', 'industry' => 'school', 'state' => '', 'city' => '', 'admin_name' => '', 'email' => '',
        'plan' => (string) array_key_first($plans), 'status' => 'active', 'trial_days' => (string) cfg('trial_days', 7), 'timezone' => '',
        'db_mode' => 'dedicated', 'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'demo' => ''];
    $errors = [];
    if (is_post()) {
        $d = [
            'org_name'   => mb_substr(trim((string) input('org_name')), 0, 120),
            'slug'       => strtolower(trim((string) input('slug'))),
            'industry'   => array_key_exists((string) input('industry'), industries()) ? (string) input('industry') : 'office',
            'state'      => isset(US_STATES[strtoupper((string) input('state'))]) ? strtoupper((string) input('state')) : '',
            'city'       => mb_substr(trim((string) input('city')), 0, 40),
            'admin_name' => mb_substr(trim((string) input('admin_name')), 0, 120),
            'email'      => strtolower(trim((string) input('email'))),
            'plan'       => isset($plans[(string) input('plan')]) ? (string) input('plan') : (string) array_key_first($plans),
            'status'     => input('status') === 'trial' ? 'trial' : 'active',
            'trial_days' => max(1, min(365, (int) input('trial_days', '7'))),
            'timezone'   => valid_tz((string) input('timezone')) ? (string) input('timezone') : '',
            'demo'       => input('demo') === '1',
            'password'   => temp_password(),
        ];
        $mode = input('db_mode') === 'shared' ? 'shared' : 'dedicated';
        if ($d['org_name'] === '') {
            $errors['org_name'] = 'Enter the business name.';
        }
        if ($p = slug_problem($d['slug'])) {
            $errors['slug'] = $p;
        }
        if ($d['admin_name'] === '') {
            $errors['admin_name'] = 'Enter the owner\'s name.';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email.';
        }
        if ($mode === 'dedicated') {
            $d['db'] = ['host' => trim((string) input('db_host')) ?: 'localhost', 'port' => (int) input('db_port', '3306') ?: 3306,
                        'name' => trim((string) input('db_name')), 'user' => trim((string) input('db_user')), 'pass' => (string) ($_POST['db_pass'] ?? '')];
            if (!$errors && ($p = tenant_db_problem($d['db']))) {
                $errors['db'] = $p;
            }
        }
        if (!$errors) {
            try {
                $t = provision_tenant($d);
                audit('Portal created by provider', $t['name'] . ' (' . $t['slug'] . ')', true,
                    industry($d['industry'])['name'] . ', ' . $d['plan'] . ' plan, ' . $d['status'] . ', owner ' . $d['email'] . ', ' . ($mode === 'dedicated' ? 'own database ' . $d['db']['name'] : 'shared database'));
                $_SESSION['new_portal'] = ['id' => (int) $t['id'], 'email' => $d['email'], 'password' => $d['password']];
                redirect(base_path() . '/super/portals/created');
            } catch (Throwable $e) {
                audit('Portal created by provider', $d['slug'], false, $e->getMessage());
                $errors['db'] = 'The portal could not be created: ' . $e->getMessage();
            }
        }
        $old = array_merge($old, $d, $d['db'] ?? [], ['db_mode' => $mode, 'demo' => $d['demo'] ? '1' : '', 'db_host' => $d['db']['host'] ?? 'localhost',
            'db_port' => (string) ($d['db']['port'] ?? '3306'), 'db_name' => $d['db']['name'] ?? '', 'db_user' => $d['db']['user'] ?? '']);
    }
    view('platform/super_portal_new', ['plans' => $plans, 'old' => $old, 'errors' => $errors, 'dbPrefix' => portal_db_prefix()], 'platform');
}

/**
 * Suggested start of a customer database name: the hosting account prefix of the main database
 * (Hostinger: "u123456789_") + "cio_". Override with 'portal_db_prefix' in app/config.php.
 */
function portal_db_prefix(): string
{
    if (is_string(cfg('portal_db_prefix'))) {
        return cfg('portal_db_prefix');
    }
    return (preg_match('/^(u\d+_)/', (string) (cfg('db')['name'] ?? ''), $m) ? $m[1] : '') . 'cio_';
}

/** Shown once after a portal is made: the owner's first password is not stored anywhere readable. */
function super_portal_created(): void
{
    require_super();
    $n = $_SESSION['new_portal'] ?? null;
    unset($_SESSION['new_portal']);
    $t = $n ? row('SELECT * FROM tenants WHERE id = ?', [$n['id']]) : null;
    if (!$t) {
        redirect(base_path() . '/super');
    }
    view('platform/super_portal_created', ['t' => $t, 'email' => $n['email'], 'password' => $n['password'], 'reset' => !empty($n['reset'])], 'platform');
}

/** Gives a portal's owner a new temporary password (shown once) and signs out nobody else. */
function super_owner_password(int $id): void
{
    require_super();
    $t = row('SELECT * FROM tenants WHERE id = ?', [$id]);
    $owner = $t ? tenant_owner($t) : null;
    if (!$owner) {
        flash('error', 'That portal has no owner login.');
        redirect(base_path() . '/super');
    }
    $pass = temp_password();
    with_tenant_db($t, fn() => update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$owner['id']]));
    audit_note($t['name'] . ' (' . $t['slug'] . ')', 'New temporary password for owner ' . $owner['email']);
    $_SESSION['new_portal'] = ['id' => (int) $t['id'], 'email' => $owner['email'], 'password' => $pass, 'reset' => true];
    redirect(base_path() . '/super/portals/created');
}

function temp_password(): string
{
    $words = ['Maple', 'River', 'Cedar', 'Coral', 'Amber', 'Delta', 'Falcon', 'Harbor', 'Juniper', 'Meadow', 'Orbit', 'Summit'];
    return $words[random_int(0, count($words) - 1)] . '-' . random_int(1000, 9999) . '-' . $words[random_int(0, count($words) - 1)];
}

// ---------- Demo requests (leads) ----------
const EMPLOYEE_RANGES = ['1-25' => '1–25', '26-100' => '26–100', '101-500' => '101–500', '500+' => '500+'];

function platform_demo(): void
{
    $old = ['name' => '', 'email' => '', 'company' => '', 'phone' => '', 'industry' => '', 'employees' => '', 'message' => ''];
    $errors = [];
    $sent = false;
    if (is_post()) {
        $d = [
            'name'      => mb_substr(trim((string) input('name')), 0, 120),
            'email'     => strtolower(mb_substr(trim((string) input('email')), 0, 190)),
            'company'   => mb_substr(trim((string) input('company')), 0, 160),
            'phone'     => mb_substr(preg_replace('/[^0-9+().\- ]/', '', (string) input('phone')), 0, 40),
            'industry'  => (string) input('industry'),
            'employees' => (string) input('employees'),
            'message'   => mb_substr(trim((string) input('message')), 0, 3000),
        ];
        if ($d['name'] === '') {
            $errors['name'] = 'Please enter your name.';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email.';
        }
        if ($d['company'] === '') {
            $errors['company'] = 'Please enter your organization.';
        }
        if (strlen(preg_replace('/\D/', '', $d['phone'])) < 7) {
            $errors['phone'] = 'Please enter a phone number.';
        }
        if (!array_key_exists($d['industry'], industries())) {
            $errors['industry'] = 'Please choose your industry.';
        }
        if (!isset(EMPLOYEE_RANGES[$d['employees']])) {
            $errors['employees'] = 'Please choose how many people.';
        }
        $bot = (string) input('website') !== ''; // hidden field: only bots fill it in
        if (!$errors && (int) val('SELECT COUNT(*) FROM leads WHERE ip = ? AND created_at > ?', [client_ip(), gmdate('Y-m-d H:i:s', time() - 3600)]) >= 5) {
            $errors['form'] = 'Too many requests from your network. Please try again later or email us.';
        }
        if (!$errors) {
            if (!$bot) {
                $id = insert('leads', $d + ['status' => 'new', 'ip' => client_ip(), 'created_at' => gmdate('Y-m-d H:i:s')]);
                $ok = lead_notify($d);
                update('leads', ['emailed' => $ok ? 1 : 0], 'id = ?', [$id]);
                audit('Demo request', $d['company'] . ' — ' . $d['name'], true, $ok ? 'Emailed to ' . lead_inbox() : 'Saved; email failed: ' . ($GLOBALS['mail_error'] ?? ''),
                    ['email' => $d['email'], 'name' => $d['name']]);
            }
            $sent = true;
        }
        $old = $d;
    }
    view('platform/demo', ['old' => $old, 'errors' => $errors, 'sent' => $sent, 'demo' => $sent ? demo_tenant() : null], 'platform');
}

function lead_inbox(): string
{
    return (string) (cfg('lead_email') ?: 'contact@' . cfg('base_domain'));
}

/** Sends the lead to the sales inbox as a formatted HTML email. */
function lead_notify(array $d): bool
{
    $rows = [
        'Name' => $d['name'], 'Email' => '<a href="mailto:' . e($d['email']) . '">' . e($d['email']) . '</a>', 'Organization' => e($d['company']),
        'Phone' => '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $d['phone'])) . '">' . e($d['phone']) . '</a>',
        'Industry' => e(industry($d['industry'])['name']), 'People' => e(EMPLOYEE_RANGES[$d['employees']] ?? $d['employees']),
    ];
    $rows['Name'] = e($rows['Name']);
    $tr = '';
    foreach ($rows as $k => $v) {
        $tr .= '<tr><td style="padding:10px 14px;color:#6b7090;font-size:13px;width:130px;border-bottom:1px solid #eceef6">' . $k . '</td>'
             . '<td style="padding:10px 14px;font-size:15px;font-weight:600;color:#161b36;border-bottom:1px solid #eceef6">' . $v . '</td></tr>';
    }
    $msg = $d['message'] !== '' ? '<div style="margin-top:18px;background:#f4f2ff;border-radius:18px;padding:16px 18px;color:#161b36;font-size:15px;line-height:1.5">'
        . '<div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#5B4CE0;margin-bottom:6px">Message</div>'
        . nl2br(e($d['message'])) . '</div>' : '';
    $html = '<div style="background:#f3f4fa;padding:28px 12px;font-family:Arial,Helvetica,sans-serif">'
        . '<div style="max-width:560px;margin:auto;background:#fff;border-radius:22px;overflow:hidden;box-shadow:0 8px 30px rgba(22,27,54,.08)">'
        . '<div style="background:#5B4CE0;color:#fff;padding:22px 24px"><div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;opacity:.8">New lead</div>'
        . '<div style="font-size:22px;font-weight:700;margin-top:4px">' . e($d['company']) . '</div>'
        . '<div style="display:inline-block;margin-top:10px;background:rgba(255,255,255,.18);border-radius:999px;padding:5px 12px;font-size:13px">'
        . e(industry($d['industry'])['name']) . ' · ' . e(EMPLOYEE_RANGES[$d['employees']] ?? '') . ' people</div></div>'
        . '<div style="padding:18px 22px"><table style="width:100%;border-collapse:collapse">' . $tr . '</table>' . $msg
        . '<p style="margin:20px 0 4px;color:#6b7090;font-size:12px">Requested ' . gmdate('M j, Y H:i') . ' UTC · see all leads in the provider console.</p></div></div></div>';
    return send_mail([lead_inbox()], 'Leads: ' . $d['company'] . ' — ' . $d['name'] . ' (' . (EMPLOYEE_RANGES[$d['employees']] ?? '') . ')', $html);
}

function super_leads(): void
{
    require_super();
    $status = (string) input('status');
    $where = isset(LEAD_STATUSES[$status]) ? 'WHERE status = ?' : '';
    $leads = rows("SELECT * FROM leads $where ORDER BY created_at DESC LIMIT 1000", $where ? [$status] : []);
    if (input('export') === 'csv') {
        $out = [['Date (UTC)', 'Name', 'Email', 'Organization', 'Phone', 'Industry', 'People', 'Status', 'Message', 'Notes']];
        foreach ($leads as $l) {
            $out[] = [$l['created_at'], $l['name'], $l['email'], $l['company'], $l['phone'], industry((string) $l['industry'])['name'], $l['employees'], $l['status'], $l['message'], $l['notes']];
        }
        send_download('leads.csv', 'text/csv; charset=utf-8', csv_string($out));
    }
    $counts = array_column(rows('SELECT status, COUNT(*) AS n FROM leads GROUP BY status'), 'n', 'status');
    view('platform/super_leads', compact('leads', 'status', 'counts'), 'platform');
}

const LEAD_STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'];

function super_lead_update(int $id): void
{
    require_super();
    $l = row('SELECT * FROM leads WHERE id = ?', [$id]);
    if ($l) {
        $new = ['status' => isset(LEAD_STATUSES[(string) input('status')]) ? (string) input('status') : $l['status'],
                'notes' => mb_substr(trim((string) input('notes')), 0, 3000) ?: null];
        audit_note($l['company'] . ' — ' . $l['name'], audit_diff($l, $new));
        update('leads', $new, 'id = ?', [$id]);
        flash('success', 'Lead updated.');
    }
    redirect(base_path() . '/super/leads' . (input('back') !== '' ? '?status=' . urlencode((string) input('back')) : ''));
}

// ---------- Suspicious sign-in reports (link in every sign-in code email) ----------
function platform_report_activity(): void
{
    $token = (string) input('t');
    $d = suspicious_token($token);
    $done = false;
    // Opening the link only shows a button: email scanners that open links do not file reports.
    if ($d && is_post()) {
        $key = 'reported_' . substr(hash('sha256', $token), 0, 16);
        if (empty($_SESSION[$key])) {
            $rows = ['Account' => $d['e'], 'Portal' => $d['p'], 'Sign-in attempt from IP' => $d['ip'],
                'Browser of the attempt' => $d['ua'] ?: '—', 'Code sent at' => gmdate('M j, Y H:i', (int) $d['t']) . ' UTC',
                'Reported at' => gmdate('M j, Y H:i') . ' UTC', 'Reported from IP' => client_ip()];
            $tr = '';
            foreach ($rows as $k => $v) {
                $tr .= '<tr><td style="padding:8px 12px;color:#6b7090;font-size:13px;border-bottom:1px solid #eee">' . e($k) . '</td><td style="padding:8px 12px;font-weight:600;border-bottom:1px solid #eee">' . e($v) . '</td></tr>';
            }
            $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:20px;color:#1f2340">'
                . '<div style="background:#c0262d;color:#fff;border-radius:14px;padding:16px 18px"><b style="font-size:18px">Suspicious activity reported</b><br>'
                . 'Someone received a sign-in code they say they did not request.</div>'
                . '<table style="width:100%;border-collapse:collapse;margin-top:14px">' . $tr . '</table>'
                . '<p style="font-size:13px;color:#6b7090">Suggested: contact the account owner, reset their password (provider console → Subscribers → Reset owner password), and check the audit log for this IP.</p></div>';
            $ok = send_mail([contact_email()], 'Suspicious activity reported — ' . $d['e'], $html);
            audit('Suspicious activity reported', $d['e'], $ok, 'Portal ' . $d['p'] . ', attempt from ' . $d['ip'] . ($ok ? '' : '; email failed: ' . ($GLOBALS['mail_error'] ?? '')),
                ['email' => $d['e'], 'name' => 'Account holder']);
            $_SESSION[$key] = true;
        }
        $done = true;
    }
    view('platform/report_activity', ['d' => $d, 'token' => $token, 'done' => $done], 'platform');
}

// ---------- Delete / restore a portal ----------
/**
 * Two-step delete: the list asks "are you sure?", then this page needs the portal's address typed
 * exactly, the provider password and a tick, and the button asks once more.
 * First delete closes the portal and keeps its data for DELETE_GRACE_DAYS (restorable);
 * deleting an already-deleted portal removes it permanently.
 */
function super_tenant_delete(int $id): void
{
    require_super();
    $t = row('SELECT * FROM tenants WHERE id = ?', [$id]);
    if (!$t) {
        redirect(base_path() . '/super');
    }
    $permanent = !empty($t['deleted_at']);
    $error = null;
    if (is_post()) {
        $who = ['email' => cfg('super_admin')['email'] ?? '', 'name' => 'Service provider', 'provider' => true];
        if (login_blocked('super-confirm', $who['email'])) {
            $error = 'Too many wrong passwords. Wait 15 minutes and try again.';
        } elseif (strtolower(trim((string) input('confirm_slug'))) !== $t['slug']) {
            $error = 'The address you typed does not match "' . $t['slug'] . '".';
        } elseif (!super_password_ok((string) ($_POST['password'] ?? ''))) {
            login_attempt('super-confirm', $who['email'], false);
            audit($permanent ? 'Portal removed permanently' : 'Portal deleted', $t['name'] . ' (' . $t['slug'] . ')', false, 'Wrong provider password');
            $error = 'That password is not right.';
        } elseif (input('understand') !== '1') {
            $error = 'Tick the box to confirm you understand.';
        } else {
            login_attempt('super-confirm', $who['email'], true);
            audit_skip();
            try {
                if ($permanent) {
                    $note = tenant_purge($t);
                    audit('Portal removed permanently', $t['name'] . ' (' . $t['slug'] . ')', true, $note);
                    flash('success', $t['name'] . ' was removed permanently. ' . ucfirst($note) . '.');
                } else {
                    tenant_soft_delete($t);
                    audit('Portal deleted', $t['name'] . ' (' . $t['slug'] . ')', true, 'Closed now; data kept ' . DELETE_GRACE_DAYS . ' days, restorable until then');
                    flash('success', $t['name'] . ' is deleted and closed. Its data is kept for ' . DELETE_GRACE_DAYS . ' days: you can restore it under "Deleted portals" until then.');
                }
            } catch (Throwable $e) {
                audit($permanent ? 'Portal removed permanently' : 'Portal deleted', $t['name'] . ' (' . $t['slug'] . ')', false, $e->getMessage());
                flash('error', 'Delete failed: ' . $e->getMessage());
            }
            redirect(base_path() . '/super');
        }
    }
    $stats = ['people' => '?', 'events' => '?', 'users' => '?'];
    try {
        $stats = with_tenant_db($t, fn() => [
            'people' => (int) val('SELECT COUNT(*) FROM students WHERE tenant_id = ?', [$t['id']]) + (int) val('SELECT COUNT(*) FROM teachers WHERE tenant_id = ?', [$t['id']]),
            'events' => (int) val('SELECT COUNT(*) FROM attendance WHERE tenant_id = ?', [$t['id']]),
            'users'  => (int) val('SELECT COUNT(*) FROM users WHERE tenant_id = ?', [$t['id']]),
        ]);
    } catch (Throwable $e) {
        // database not reachable: deleting still works for the record in the main database
    }
    view('platform/super_tenant_delete', compact('t', 'permanent', 'error', 'stats'), 'platform');
}

function super_tenant_restore(int $id): void
{
    require_super();
    $t = row('SELECT * FROM tenants WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
    if ($t) {
        tenant_restore($t);
        audit_note($t['name'] . ' (' . $t['slug'] . ')', 'Restored; the portal is open again');
        flash('success', $t['name'] . ' is restored and open again.');
    }
    redirect(base_path() . '/super');
}

// ---------- Public "What's new" and provider view of feature requests ----------
function platform_whats_new(): void
{
    view('platform/whats_new', [], 'platform');
}

function super_requests(): void
{
    require_super();
    $status = (string) input('status');
    $where = isset(FEATURE_STATUSES[$status]) ? 'WHERE status = ?' : '';
    $requests = rows("SELECT * FROM feature_requests $where ORDER BY created_at DESC LIMIT 500", $where ? [$status] : []);
    $counts = array_column(rows('SELECT status, COUNT(*) AS n FROM feature_requests GROUP BY status'), 'n', 'status');
    view('platform/super_requests', compact('requests', 'status', 'counts'), 'platform');
}

function super_request_update(int $id): void
{
    require_super();
    $r = row('SELECT * FROM feature_requests WHERE id = ?', [$id]);
    if ($r) {
        $new = ['status' => isset(FEATURE_STATUSES[(string) input('status')]) ? (string) input('status') : $r['status'],
                'reply' => mb_substr(trim((string) input('reply')), 0, 2000) ?: null, 'updated_at' => gmdate('Y-m-d H:i:s')];
        audit_note('#' . $id . ' ' . $r['title'] . ' (' . $r['portal'] . ')', audit_diff($r, $new, ['updated_at']));
        update('feature_requests', $new, 'id = ?', [$id]);
        flash('success', 'Request updated. The customer sees the new status in their portal.');
    }
    redirect(base_path() . '/super/requests' . (input('back') !== '' ? '?status=' . urlencode((string) input('back')) : ''));
}
