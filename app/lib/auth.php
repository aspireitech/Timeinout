<?php
// Session-based login. A session user is tied to a tenant id, so a login on
// one portal never works on another (important in /s/<slug> mode where all
// portals share one hostname and one cookie).

function start_session(): void
{
    session_name('tio_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => is_https(),
    ]);
    $dir = session_save_path();
    if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
        $own = APP_DIR . '/sessions';
        if (!is_dir($own)) {
            @mkdir($own, 0700, true);
        }
        if (is_writable($own)) {
            session_save_path($own);
        }
    }
    session_start();
}

const IDLE_ADMIN = 30 * 60;      // admins are signed out after 30 minutes without activity
const IDLE_STAFF = 12 * 3600;     // kiosk tablets stay signed in through the school day
const OTP_TTL = 10 * 60;          // one-time codes expire after 10 minutes
const OTP_MAX_TRIES = 5;

function current_user(): ?array
{
    $u = $_SESSION['user'] ?? null;
    if (!$u || !tenant() || (int) $u['tenant_id'] !== tid()) {
        return null;
    }
    $limit = in_array($u['role'], ['owner', 'admin'], true) ? IDLE_ADMIN : IDLE_STAFF;
    if (time() - (int) ($_SESSION['last_seen'] ?? 0) > $limit) {
        unset($_SESSION['user']);
        $_SESSION['timed_out'] = true;
        return null;
    }
    $_SESSION['last_seen'] = time();
    return $u;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && in_array($u['role'], ['owner', 'admin'], true);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect(url('/login'));
    }
    return $u;
}

/** Admin portal pages: owners and admins only. Staff logins only open the kiosk. */
function require_admin(): array
{
    $u = require_login();
    if (!is_admin()) {
        redirect(url('/'));
    }
    return $u;
}

function admin_otp_enabled(): bool
{
    return setting('admin_otp', '1') === '1';
}

/**
 * Step 1 of signing in. Returns null when signed in, 'otp' when an email code
 * is needed, or an error message.
 */
function attempt_login(string $email, string $password): ?string
{
    $scope = 't' . tid();
    $email = strtolower(trim($email));
    $who = ['email' => $email, 'tenant_id' => tid(), 'name' => null];
    if (login_blocked($scope, $email)) {
        audit('Sign-in blocked', $email, false, 'Too many failed attempts in 15 minutes', $who);
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }
    $u = row('SELECT * FROM users WHERE tenant_id = ? AND email = ?', [tid(), $email]);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        login_attempt($scope, $email, false);
        audit('Sign-in', $email, false, $u ? 'Wrong password' : 'Unknown email', $who);
        return 'That email and password do not match.';
    }
    login_attempt($scope, $email, true);
    if (in_array($u['role'], ['owner', 'admin'], true) && admin_otp_enabled()) {
        return otp_start('t' . tid(), user_ref($u)) ? 'otp' : 'We could not email your sign-in code. Please contact your service provider.';
    }
    complete_login($u);
    audit('Sign-in', $email, true, ucfirst($u['role']) . ' (kiosk)');
    return null;
}

function user_ref(array $u): array
{
    return ['id' => (int) $u['id'], 'tenant_id' => (int) $u['tenant_id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']];
}

function complete_login(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = user_ref($u);
    $_SESSION['last_seen'] = time();
    unset($_SESSION['otp']);
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $u['id']]); // UTC
}

function logout(): void
{
    if ($u = current_user()) {
        audit('Sign-out', $u['email'], true);
    }
    unset($_SESSION['user']);
    session_regenerate_id(true);
}

// ---------- One-time email codes (admins and, optionally, the provider) ----------
/** Creates a 6-digit code, emails it and remembers its hash in the session. */
function otp_start(string $scope, array $who): bool
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp'][$scope] = [
        'who' => $who, 'hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires' => time() + OTP_TTL, 'tries' => 0, 'sent' => time(),
    ];
    $place = tenant()['name'] ?? cfg('app_name') . ' provider console';
    $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px;color:#1f2340">'
        . '<h2 style="margin:0 0 8px">Your sign-in code</h2>'
        . '<p>Use this code to finish signing in to <b>' . e($place) . '</b>:</p>'
        . '<p style="font-size:32px;font-weight:bold;letter-spacing:8px;background:#f0f1f8;border-radius:12px;padding:14px;text-align:center">' . $code . '</p>'
        . '<p style="color:#6b7090;font-size:13px">It expires in 10 minutes. If you did not try to sign in, change your password and tell your administrator.</p></div>';
    // The demo school's admin mailbox does not exist, so the code is shown on screen instead.
    $demo = (tenant()['slug'] ?? '') === DEMO_SLUG;
    $ok = $demo || send_mail([$who['email']], 'Your sign-in code: ' . $code, $html);
    if ($demo) {
        $_SESSION['otp'][$scope]['demo_code'] = $code;
    }
    audit('Sign-in code sent', $who['email'], $ok, $ok ? ($demo ? 'Demo school: code shown on screen' : 'Emailed to ' . $who['email']) : 'Email could not be sent: ' . ($GLOBALS['mail_error'] ?? 'unknown error'), $who);
    if (!$ok) {
        unset($_SESSION['otp'][$scope]);
    }
    return $ok;
}

function otp_pending(string $scope): ?array
{
    return $_SESSION['otp'][$scope] ?? null;
}

/** @return string|null null when the code is right, otherwise the reason */
function otp_check(string $scope, string $code): ?string
{
    $o = otp_pending($scope);
    if (!$o) {
        return 'Your sign-in has expired. Please sign in again.';
    }
    $who = $o['who'];
    if (time() > $o['expires']) {
        unset($_SESSION['otp'][$scope]);
        audit('Sign-in code entered', $who['email'], false, 'Code expired', $who);
        return 'That code has expired. Please sign in again.';
    }
    if (!password_verify(preg_replace('/\D/', '', $code), $o['hash'])) {
        $_SESSION['otp'][$scope]['tries']++;
        $left = OTP_MAX_TRIES - $_SESSION['otp'][$scope]['tries'];
        audit('Sign-in code entered', $who['email'], false, 'Wrong code' . ($left <= 0 ? '; sign-in cancelled' : ''), $who);
        if ($left <= 0) {
            unset($_SESSION['otp'][$scope]);
            login_attempt($scope === 'super' ? 'super' : $scope, $who['email'], false);
            return 'Too many wrong codes. Please sign in again.';
        }
        return "That code is not right. $left " . ($left === 1 ? 'try' : 'tries') . ' left.';
    }
    audit('Sign-in code entered', $who['email'], true, 'Code correct, signed in', $who);
    return null;
}

function otp_resend(string $scope): string
{
    $o = otp_pending($scope);
    if (!$o) {
        return 'Your sign-in has expired. Please sign in again.';
    }
    if (time() - $o['sent'] < 60) {
        return 'Please wait a minute before asking for a new code.';
    }
    return otp_start($scope, $o['who']) ? 'A new code is on its way.' : 'We could not email your code.';
}

// ---------- Platform owner (service provider) ----------
/** @return string|null null when signed in, 'otp' when an email code is needed, or the reason it failed */
function super_check(string $email, string $password): ?string
{
    $sa = cfg('super_admin', []);
    $email = trim($email);
    $password = trim($password);
    $who = ['email' => $email, 'name' => 'Service provider', 'provider' => true];
    if (login_blocked('super', $email)) {
        audit('Provider sign-in blocked', $email, false, 'Too many failed attempts', $who);
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }
    $stored = trim((string) ($sa['password'] ?? ''));
    $passOk = str_starts_with($stored, '$2y$') ? password_verify($password, $stored) : ($stored !== '' && hash_equals($stored, $password));
    $ok = $passOk && strcasecmp($email, trim((string) ($sa['email'] ?? ''))) === 0;
    login_attempt('super', $email, $ok);
    if (!$ok) {
        audit('Provider sign-in', $email, false, 'Wrong email or password', $who);
        return 'Wrong email or password. They must match super_admin in app/config.php.';
    }
    if (setting('super_otp', '0') === '1') {
        return otp_start('super', $who) ? 'otp' : 'We could not email your sign-in code. Check the email settings.';
    }
    super_complete($email);
    return null;
}

function super_complete(string $email): void
{
    session_regenerate_id(true);
    $_SESSION['super'] = true;
    $_SESSION['super_seen'] = time();
    unset($_SESSION['otp']['super']);
    audit('Provider sign-in', $email, true, null, ['email' => $email, 'name' => 'Service provider', 'provider' => true]);
}

function require_super(): void
{
    if (!empty($_SESSION['super']) && time() - (int) ($_SESSION['super_seen'] ?? 0) > IDLE_ADMIN) {
        unset($_SESSION['super']);
        redirect(base_path() . '/super/login?timeout=1');
    }
    if (empty($_SESSION['super'])) {
        // Just logged in but the session is already gone: the browser isn't keeping the cookie
        redirect(base_path() . '/super/login' . (isset($_GET['li']) ? '?nosession=1' : ''));
    }
    $_SESSION['super_seen'] = time();
}
