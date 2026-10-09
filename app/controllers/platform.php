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
        $slug = preg_replace('/[^a-z0-9]/', '', $slug);
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
            (SELECT email FROM users u WHERE u.tenant_id = t.id AND u.role = "owner" ORDER BY u.id LIMIT 1) AS owner_email,
            (SELECT COALESCE(SUM(amount_cents),0) FROM payments p WHERE p.tenant_id = t.id AND p.status = "paid") AS paid_total
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
        setting_set('super_otp', $new['super_otp']);
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
    echo implode("\n", array_merge(run_scheduled_reports(), wave_sync())) ?: 'Nothing due.';
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
