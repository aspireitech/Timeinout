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
    $email = strtolower(trim($email)); // an email address or a user ID
    $who = ['email' => $email, 'tenant_id' => tid(), 'name' => null];
    if (login_blocked($scope, $email)) {
        audit('Sign-in blocked', $email, false, 'Too many failed attempts in 15 minutes', $who);
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }
    $u = find_login_user($email);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        login_attempt($scope, $email, false);
        audit('Sign-in', $email, false, $u ? 'Wrong password' : 'Unknown email or user ID', $who);
        return 'That email or user ID and password do not match.';
    }
    login_attempt($scope, $email, true);
    if (in_array($u['role'], ['owner', 'admin'], true) && admin_otp_enabled() && !empty($u['email'])) {
        return otp_start('t' . tid(), user_ref($u)) ? 'otp' : 'We could not email your sign-in code. Please contact your service provider.';
    }
    complete_login($u);
    audit('Sign-in', login_name($u), true, ucfirst($u['role']) . (in_array($u['role'], ['owner', 'admin'], true) ? '' : ' (kiosk)'));
    return null;
}

/** Finds a login by email address or user ID in the current portal. */
function find_login_user(string $login): ?array
{
    $login = strtolower(trim($login));
    if ($login === '') {
        return null;
    }
    return row('SELECT * FROM users WHERE tenant_id = ? AND (email = ? OR username = ?) ORDER BY email = ? DESC LIMIT 1', [tid(), $login, $login, $login]);
}

/** What a person types to sign in: their user ID when they have one, otherwise their email. */
function login_name(array $u): string
{
    return (string) (($u['username'] ?? '') ?: ($u['email'] ?? ''));
}

function user_ref(array $u): array
{
    return ['id' => (int) $u['id'], 'tenant_id' => (int) $u['tenant_id'], 'name' => $u['name'], 'email' => ($u['email'] ?? '') ?: login_name($u),
            'username' => $u['username'] ?? null, 'role' => $u['role'], 'must_change' => !empty($u['must_change_password'])];
}

/** People who must choose a new password are sent to that page before anything else. */
function enforce_password_change(string $path): void
{
    $u = current_user();
    if ($u && !empty($u['must_change']) && !in_array($path, ['/account/password', '/logout'], true)) {
        redirect(url('/account/password'));
    }
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
    $passOk = super_password_ok($password);
    $ok = $passOk && strcasecmp($email, trim((string) ($sa['email'] ?? ''))) === 0;
    login_attempt('super', $email, $ok);
    if (!$ok) {
        audit('Provider sign-in', $email, false, 'Wrong email or password', $who);
        return 'Wrong email or password.';
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
        if (otp_start('super', $who)) {
            return 'otp';
        }
        if (super_recovery_left() > 0) { // email is down: let the provider use a recovery code instead
            $_SESSION['otp']['super'] = ['who' => $who, 'hash' => null, 'expires' => time() + OTP_TTL, 'tries' => 0, 'sent' => time(), 'mail_failed' => true];
            return 'otp';
        }
        return 'We could not email your sign-in code. Check the email settings, or use the server command in docs/GO_LIVE.md.';
    }
    super_complete($who['email']);
    return null;
}

/** Checks the provider password from app/config.php (plain text or a password_hash value). */
/**
 * Checks the provider password. Once a password is set in /super → Settings (stored hashed in the
 * database), the one in app/config.php is ignored and can be removed from that file.
 */
function super_password_ok(string $password): bool
{
    $password = trim($password);
    $hash = (string) setting('super_password_hash', '');
    if ($hash !== '') {
        return $password !== '' && password_verify($password, $hash);
    }
    $stored = trim((string) (cfg('super_admin')['password'] ?? ''));
    return str_starts_with($stored, '$2y$') ? password_verify($password, $stored) : ($stored !== '' && hash_equals($stored, $password));
}

function super_password_set(string $password): void
{
    setting_set('super_password_hash', password_hash($password, PASSWORD_DEFAULT));
}

function super_password_problem(string $password): ?string
{
    if (strlen($password) < 14) {
        return 'Use at least 14 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Use letters and at least one number.';
    }
    return null;
}

// ---------- Recovery codes: used instead of the emailed code when email is down ----------
/** Makes 10 new one-time codes (shown once), replacing any old ones. */
function super_recovery_generate(): array
{
    $codes = [];
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($n = 0; $n < 10; $n++) {
        $c = '';
        for ($i = 0; $i < 12; $i++) {
            $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $codes[] = substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8, 4);
    }
    setting_set('super_recovery', json_encode(array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $codes)));
    return $codes;
}

function super_recovery_left(): int
{
    return count(json_decode((string) setting('super_recovery', '[]'), true) ?: []);
}

/** Uses up a recovery code. True when it was valid. */
function super_recovery_use(string $code): bool
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    if (strlen($code) !== 12) {
        return false;
    }
    $code = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
    $hashes = json_decode((string) setting('super_recovery', '[]'), true) ?: [];
    foreach ($hashes as $i => $h) {
        if (password_verify($code, $h)) {
            unset($hashes[$i]);
            setting_set('super_recovery', json_encode(array_values($hashes)));
            return true;
        }
    }
    return false;
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
    if (cfg('super_google_code', false)) { // optional: also ask for the emailed code after Google
        audit('Provider sign-in (Google)', $email, true, 'Google identity confirmed; email code next', $who);
        return super_second_factor($who) ?? 'done';
    }
    super_complete($email, 'Google'); // 1st choice: Google, protected by the account's own 2-Step Verification
    return 'done';
}

function super_complete(string $email, ?string $how = null): void
{
    session_regenerate_id(true);
    $_SESSION['super'] = true;
    $_SESSION['super_seen'] = time();
    unset($_SESSION['otp']['super']);
    audit('Provider sign-in', $email, true, $how, ['email' => $email, 'name' => 'Service provider', 'provider' => true]);
}

/**
 * 2nd choice, when Google is not available: a 6-digit code emailed to the provider address.
 * @return string 'otp' when the code was sent, otherwise the reason
 */
function super_email_code_start(string $email): string
{
    $email = strtolower(trim($email));
    $who = ['email' => $email, 'name' => 'Service provider', 'provider' => true, 'via' => 'code'];
    if (login_blocked('super', $email)) {
        audit('Provider sign-in blocked', $email, false, 'Too many failed attempts', $who);
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }
    $ok = $email !== '' && in_array($email, super_emails(), true);
    login_attempt('super', $email, $ok);
    if (!$ok) {
        audit('Provider sign-in (email code)', $email, false, 'Not a provider email', $who);
        return 'That email cannot sign in to the provider console.';
    }
    return otp_start('super', $who) ? 'otp' : 'We could not email your code. If email is down too, use break-glass sign-in below.';
}

/**
 * 3rd choice, when Google and email are both down: the break-glass password plus one of the
 * one-time recovery codes. Each use is logged and emailed (when email works).
 */
function super_breakglass(string $email, string $password, string $code): ?string
{
    $email = strtolower(trim($email));
    $who = ['email' => $email, 'name' => 'Service provider', 'provider' => true];
    if (login_blocked('super-breakglass', $email)) {
        audit('Provider break-glass sign-in blocked', $email, false, 'Too many failed attempts', $who);
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }
    $ok = in_array($email, super_emails(), true) && super_password_ok($password) && super_recovery_use($code);
    login_attempt('super-breakglass', $email, $ok);
    if (!$ok) {
        audit('Provider break-glass sign-in', $email, false, 'Wrong email, password or recovery code', $who);
        return 'Wrong email, password or recovery code.';
    }
    super_complete($email, 'Break-glass: password + recovery code (' . super_recovery_left() . ' codes left)');
    send_mail([(string) (cfg('super_admin')['email'] ?? '')], cfg('app_name') . ': break-glass sign-in used',
        '<p>The break-glass password and a recovery code were used to sign in to the provider console on ' . gmdate('M j, Y H:i') . ' UTC from '
        . e(client_ip()) . '. ' . super_recovery_left() . ' recovery codes are left. If this was not you, change the break-glass password and create new recovery codes now.</p>');
    return null;
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
