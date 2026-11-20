<?php
// Subscriber portal entry: the check-in kiosk and the login page.

function kiosk_home(): void
{
    $t = tenant();
    if (!$t['kiosk_public'] && !current_user()) {
        redirect(url('/login'));
    }
    $materials = rows('SELECT id, name FROM materials WHERE tenant_id = ? AND active = 1 ORDER BY sort_order, name', [tid()]);
    view('kiosk/index', ['materials' => $materials, 'today' => tenant_today(), 'tiles' => kiosk_tiles()], 'kiosk');
}

function login_page(): void
{
    if (current_user()) {
        redirect(url(is_admin() ? '/admin' : '/'));
    }
    $error = null;
    $info = null;
    if (!empty($_SESSION['timed_out'])) {
        unset($_SESSION['timed_out']);
        $info = 'You were signed out after a period of no activity. Please sign in again.';
    }
    $email = (string) input('email');
    if (is_post()) {
        $result = attempt_login($email, (string) ($_POST['password'] ?? ''));
        if ($result === null) {
            redirect(url(!empty(current_user()['must_change']) ? '/account/password' : (is_admin() ? '/admin' : '/')));
        }
        if ($result === 'otp') {
            redirect(url('/login/verify'));
        }
        $error = $result;
    }
    view('auth/login', ['error' => $error, 'info' => $info, 'email' => $email, 'welcome' => input('welcome') === '1'], 'auth');
}

/** Step 2 for admins: the 6-digit code from their email. */
function login_verify(): void
{
    $scope = 't' . tid();
    $o = otp_pending($scope);
    if (!$o || (int) ($o['who']['tenant_id'] ?? 0) !== tid()) {
        redirect(url('/login'));
    }
    $error = null;
    $info = null;
    if (is_post()) {
        if (input('resend') === '1') {
            $info = otp_resend($scope);
        } else {
            $error = otp_check($scope, (string) input('code'));
            if ($error === null) {
                $u = row('SELECT * FROM users WHERE id = ? AND tenant_id = ?', [$o['who']['id'], tid()]);
                if ($u) {
                    complete_login($u);
                    audit('Sign-in', login_name($u), true, ucfirst($u['role']) . ' with email code');
                    redirect(url(!empty($u['must_change_password']) ? '/account/password' : '/admin'));
                }
                $error = 'Your account was not found. Please sign in again.';
            }
            if (!otp_pending($scope)) { // cancelled after too many tries or expired
                flash('error', $error);
                redirect(url('/login'));
            }
        }
    }
    view('auth/verify', [
        'error' => $error, 'info' => $info, 'email' => $o['who']['email'],
        'demoCode' => otp_pending($scope)['demo_code'] ?? null, 'action' => url('/login/verify'), 'back' => url('/login'),
    ], 'auth');
}

function logout_action(): void
{
    logout();
    redirect(url('/login'));
}

/** Choose a new password: required after a temporary one, optional otherwise. Also the user's notice email. */
function account_password(): void
{
    $me = require_login();
    $u = row('SELECT * FROM users WHERE id = ? AND tenant_id = ?', [$me['id'], tid()]);
    if (!$u) {
        logout();
        redirect(url('/login'));
    }
    $forced = !empty($u['must_change_password']);
    $error = null;
    if (is_post()) {
        audit_note(login_name($u));
        $new = (string) ($_POST['new_password'] ?? '');
        $email = strtolower(trim((string) input('email')));
        if (!$forced && !password_verify((string) ($_POST['current_password'] ?? ''), $u['password_hash'])) {
            $error = 'Your current password is not correct.';
        } elseif ($new !== (string) ($_POST['confirm_password'] ?? '')) {
            $error = 'The two new passwords do not match.';
        } elseif ($p = password_problem($new, $u['role'])) {
            $error = $p;
        } elseif (password_verify($new, $u['password_hash'])) {
            $error = 'Choose a password different from the one you were given.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'That email address does not look right.';
        } elseif ($email !== '' && val('SELECT id FROM users WHERE tenant_id = ? AND email = ? AND id <> ?', [tid(), $email, $u['id']])) {
            $error = 'Another login already uses that email.';
        } elseif ($email === '' && in_array($u['role'], ['owner', 'admin'], true)) {
            $error = 'Admins need an email address for sign-in codes.';
        } else {
            update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0, 'email' => $email ?: null], 'id = ?', [$u['id']]);
            $u = row('SELECT * FROM users WHERE id = ?', [$u['id']]);
            $_SESSION['user'] = user_ref($u);
            flash('success', 'Your new password is saved.');
            redirect(url(in_array($u['role'], ['owner', 'admin'], true) ? '/admin' : '/'));
        }
        audit_skip(); // nothing changed
    }
    view('auth/change_password', ['u' => $u, 'forced' => $forced, 'error' => $error], 'auth');
}
