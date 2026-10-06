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
    session_start();
}

function current_user(): ?array
{
    $u = $_SESSION['user'] ?? null;
    if (!$u || !tenant() || (int) $u['tenant_id'] !== tid()) {
        return null;
    }
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

function require_admin(): array
{
    $u = require_login();
    if (!is_admin()) {
        flash('error', 'Only administrators can open that page.');
        redirect(url('/admin'));
    }
    return $u;
}

function too_many_attempts(string $bucket): bool
{
    $a = $_SESSION['attempts'][$bucket] ?? ['n' => 0, 't' => time()];
    if (time() - $a['t'] > 900) {
        return false;
    }
    return $a['n'] >= 8;
}

function note_attempt(string $bucket, bool $ok): void
{
    if ($ok) {
        unset($_SESSION['attempts'][$bucket]);
        return;
    }
    $a = $_SESSION['attempts'][$bucket] ?? ['n' => 0, 't' => time()];
    if (time() - $a['t'] > 900) {
        $a = ['n' => 0, 't' => time()];
    }
    $a['n']++;
    $_SESSION['attempts'][$bucket] = $a;
}

function attempt_login(string $email, string $password): bool
{
    $bucket = 't' . tid();
    if (too_many_attempts($bucket)) {
        return false;
    }
    $u = row('SELECT * FROM users WHERE tenant_id = ? AND email = ?', [tid(), strtolower($email)]);
    $ok = $u && password_verify($password, $u['password_hash']);
    note_attempt($bucket, $ok);
    if (!$ok) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int) $u['id'], 'tenant_id' => (int) $u['tenant_id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']];
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
    return true;
}

function logout(): void
{
    unset($_SESSION['user']);
    session_regenerate_id(true);
}

// ---------- Platform owner ----------
function super_check(string $email, string $password): bool
{
    $sa = cfg('super_admin', []);
    if (too_many_attempts('super')) {
        return false;
    }
    $stored = (string) ($sa['password'] ?? '');
    $passOk = str_starts_with($stored, '$2y$') ? password_verify($password, $stored) : ($stored !== '' && hash_equals($stored, $password));
    $ok = $passOk && strcasecmp($email, (string) ($sa['email'] ?? '')) === 0;
    note_attempt('super', $ok);
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['super'] = true;
    }
    return $ok;
}

function require_super(): void
{
    if (empty($_SESSION['super'])) {
        redirect(base_path() . '/super/login');
    }
}
