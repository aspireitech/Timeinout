<?php
// Free checklist sign-ups from the public website (see app/lib/subscribers.php).

/** POST /subscribe: email + type of place. Answers JSON to the slide-in, or redirects to the checklist for a plain form post. */
function platform_subscribe(): void
{
    $email = strtolower(mb_substr(trim((string) input('email')), 0, 190));
    $kind = subscriber_kind((string) input('kind'));
    $ajax = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    $fail = function (string $msg) use ($ajax, $kind): void {
        if ($ajax) {
            json_out(['ok' => false, 'error' => $msg], 422);
        }
        flash('error', $msg);
        redirect(base_path() . '/checklist/' . $kind);
    };
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fail('Please check the email address.');
        return;
    }
    if ((int) val('SELECT COUNT(*) FROM subscribers WHERE ip = ? AND created_at > ?', [client_ip(), gmdate('Y-m-d H:i:s', time() - 3600)]) >= 5) {
        $fail('Too many sign-ups from your network. Please try again later.');
        return;
    }
    if ((string) input('website') === '') { // hidden field: only bots fill it in (they still see "thank you")
        subscribe($email, $kind, lead_source());
    }
    // Same answer whether the address was new or already on the list (no way to test who is subscribed).
    // The thank-you (and the Google Ads "lead" conversion) shows once, only after a real form post.
    $_SESSION['checklist_thanks'] = 1;
    $url = base_path() . '/checklist/' . $kind . '?thanks=1';
    if ($ajax) {
        json_out(['ok' => true, 'url' => $url]);
    }
    redirect($url);
}

function platform_checklist_care(): void
{
    platform_checklist('care');
}

function platform_checklist_work(): void
{
    platform_checklist('work');
}

function platform_checklist(string $kind): void
{
    $c = CHECKLISTS[$kind];
    $thanks = input('thanks') === '1' && !empty($_SESSION['checklist_thanks']);
    unset($_SESSION['checklist_thanks']);
    view('platform/checklist', ['kind' => $kind, 'c' => $c, 'thanks' => $thanks,
        'metaTitle' => 'Free ' . strtolower($c['title']) . ' | ' . cfg('app_name'), 'metaDesc' => $c['intro']], 'platform');
}

/** Unsubscribe from the checklist emails: GET shows a button, POST confirms. */
function platform_subscribe_stop(): void
{
    $token = (string) input('t');
    $s = preg_match('/^[a-f0-9]{32}$/', $token) ? row('SELECT * FROM subscribers WHERE token = ?', [$token]) : null;
    if ($s && is_post() && !$s['unsubscribed_at']) {
        update('subscribers', ['unsubscribed_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$s['id']]);
        $s['unsubscribed_at'] = gmdate('Y-m-d H:i:s');
    }
    view('platform/subscribe_stop', ['s' => $s, 'token' => $s ? $token : '', 'metaTitle' => 'Unsubscribe | ' . cfg('app_name')], 'platform');
}
