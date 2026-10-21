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
        . '<p style="color:#6b7090;font-size:13px">It expires in 10 minutes. Never share this code: ' . e(cfg('app_name')) . ' staff will never ask for it.</p>'
        . '<div style="margin-top:18px;border:1px solid #f3c7c9;background:#fff5f5;border-radius:14px;padding:14px 16px;font-size:13px;color:#7a2a2e">'
        . '<b>Did not request this code?</b> Someone may know your password. Do not use the code, change your password, and '
        . '<a href="' . e(suspicious_link($who, $place)) . '" style="color:#c0262d;font-weight:bold">report it to us</a>'
        . ' (or email ' . e(contact_email()) . ').</div></div>';
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
    return super_second_factor($who);
}

/**
 * Every provider sign-in (password or Google) is finished with an emailed code.
 * Only 'super_otp' => false in app/config.php (someone with server access) turns it off,
 * for example while the email settings are being repaired.
 */
function super_second_factor(array $who): ?string
{
    if (cfg('super_otp', true) !== false) {
        return otp_start('super', $who) ? 'otp' : 'We could not email your sign-in code. Check the email settings (or set super_otp to false in app/config.php until mail works).';
    }
    super_complete($who['email']);
    return null;
}

/** Emails allowed to open /super: the super_admin email plus any listed in google_oauth.allowed_emails. */
function super_emails(): array
{
    $list = array_merge([(string) (cfg('super_admin')['email'] ?? '')], (array) (cfg('google_oauth')['allowed_emails'] ?? []));
    return array_values(array_filter(array_map(fn($e) => strtolower(trim((string) $e)), $list)));
}

// ---------- Sign in with Google (OpenID Connect, authorization code + PKCE) ----------
function google_ready(): bool
{
    $g = cfg('google_oauth', []);
    return !empty($g['client_id']) && !empty($g['client_secret']);
}

function google_redirect_uri(): string
{
    return scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? cfg('base_domain')) . base_path() . '/super/google/callback';
}

/** The Google page to send the browser to. */
function google_auth_url(): string
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $_SESSION['google'] = ['state' => bin2hex(random_bytes(16)), 'verifier' => $verifier, 'at' => time()];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'             => cfg('google_oauth')['client_id'],
        'redirect_uri'          => google_redirect_uri(),
        'response_type'         => 'code',
        'scope'                 => 'openid email profile',
        'state'                 => $_SESSION['google']['state'],
        'code_challenge'        => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
        'prompt'                => 'select_account',
        'login_hint'            => cfg('super_admin')['email'] ?? '',
    ]);
}

function google_http(string $url, ?array $post = null, ?string $bearer = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $bearer ? ['Authorization: Bearer ' . $bearer] : [],
    ]);
    if ($post !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Could not reach Google: ' . $err);
    }
    $data = json_decode((string) $body, true) ?: [];
    if ($code >= 400) {
        throw new RuntimeException('Google said: ' . ($data['error_description'] ?? $data['error'] ?? "HTTP $code"));
    }
    return $data;
}

/**
 * Handles Google's redirect back. Returns 'otp' when the sign-in code was emailed, 'done' when
 * signed in, or the reason Google sign-in failed (the password form always stays available).
 */
function google_callback(array $query): string
{
    $g = $_SESSION['google'] ?? null;
    unset($_SESSION['google']);
    if (!empty($query['error'])) {
        return 'Google sign-in was cancelled (' . $query['error'] . ').';
    }
    if (!$g || time() - $g['at'] > 600 || !hash_equals($g['state'], (string) ($query['state'] ?? ''))) {
        return 'Google sign-in expired or did not come from this page. Please try again.';
    }
    $tok = google_http('https://oauth2.googleapis.com/token', [
        'client_id' => cfg('google_oauth')['client_id'], 'client_secret' => cfg('google_oauth')['client_secret'],
        'code' => (string) ($query['code'] ?? ''), 'code_verifier' => $g['verifier'],
        'grant_type' => 'authorization_code', 'redirect_uri' => google_redirect_uri(),
    ]);
    $info = google_http('https://openidconnect.googleapis.com/v1/userinfo', null, (string) ($tok['access_token'] ?? ''));
    $email = strtolower(trim((string) ($info['email'] ?? '')));
    $who = ['email' => $email, 'name' => 'Service provider', 'provider' => true];
    if ($email === '' || empty($info['email_verified'])) {
        return 'Google did not return a verified email address.';
    }
    if (login_blocked('super', $email)) {
        audit('Provider sign-in blocked', $email, false, 'Too many failed attempts', $who);
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }
    $ok = in_array($email, super_emails(), true);
    login_attempt('super', $email, $ok);
    if (!$ok) {
        audit('Provider sign-in (Google)', $email, false, 'This Google account is not allowed', $who);
        return 'The Google account ' . $email . ' is not allowed to open the provider console.';
    }
    audit('Provider sign-in (Google)', $email, true, 'Google identity confirmed; email code next', $who);
    return super_second_factor($who) ?? 'done';
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

// ---------- "I did not request this code" reports ----------
/** Signed link in every sign-in code email; opening it lets the person report the attempt. */
function suspicious_link(array $who, string $place): string
{
    $payload = rtrim(strtr(base64_encode(json_encode([
        'e' => $who['email'], 'p' => $place, 'ip' => client_ip(), 't' => time(),
        'ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160),
    ])), '+/', '-_'), '=');
    $sig = substr(hash_hmac('sha256', $payload, app_key()), 0, 32);
    return scheme() . '://' . cfg('base_domain') . '/report-activity?t=' . $payload . '.' . $sig;
}

/** Decodes a report link; null when it was altered or is older than 30 days. */
function suspicious_token(string $token): ?array
{
    [$payload, $sig] = array_pad(explode('.', $token, 2), 2, '');
    if ($payload === '' || !hash_equals(substr(hash_hmac('sha256', $payload, app_key()), 0, 32), $sig)) {
        return null;
    }
    $d = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
    return is_array($d) && time() - (int) ($d['t'] ?? 0) < 30 * 86400 ? $d : null;
}
