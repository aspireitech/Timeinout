<?php
// Subscriber portal entry: the check-in kiosk and the login page.

function kiosk_home(): void
{
    $t = tenant();
    if (!$t['kiosk_public'] && !current_user()) {
        redirect(url('/login'));
    }
    $materials = rows('SELECT id, name FROM materials WHERE tenant_id = ? AND active = 1 ORDER BY sort_order, name', [tid()]);
    view('kiosk/index', ['materials' => $materials, 'today' => tenant_today()], 'kiosk');
}

function login_page(): void
{
    if (current_user()) {
        redirect(url(is_admin() ? '/admin' : '/'));
    }
    $error = null;
    $email = (string) input('email');
    if (is_post()) {
        if (attempt_login($email, (string) ($_POST['password'] ?? ''))) {
            redirect(url(is_admin() ? '/admin' : '/'));
        }
        $error = too_many_attempts('t' . tid())
            ? 'Too many attempts. Please wait 15 minutes and try again.'
            : 'That email and password do not match.';
    }
    view('auth/login', ['error' => $error, 'email' => $email, 'welcome' => input('welcome') === '1'], 'auth');
}

function logout_action(): void
{
    logout();
    redirect(url('/login'));
}
