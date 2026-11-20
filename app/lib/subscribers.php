<?php
// Website email sign-ups ("Get the free checklist" slide-in on the public website):
//   * a visitor leaves an email and their type of place; we email the matching free checklist at once (welcome);
//   * 3 days later one follow-up with the free trial and the founding offer (only if they have not signed up);
//   * occasional product news after that; every email has an unsubscribe link (/subscribe/stop?t=TOKEN);
//   * the list is on /super → Sales dashboard (count, list, CSV). Single opt-in, consent wording next to the button.

const CHECKLISTS = [
    'care' => [
        'title' => 'Safe drop-off and pickup checklist',
        'for'   => 'Childcare centers, schools, after-school programs and tutoring centers',
        'intro' => '12 checks that keep every child accounted for, from the morning drop-off to the last pickup. Print it and tick it off with your team.',
        'items' => [
            'Every child is signed in with the time and the adult who dropped them off.',
            'Each child has a list of adults allowed to pick up, with a phone number, kept up to date.',
            'Anyone not on the list is checked (photo ID and a call to the parent) before the child leaves.',
            'Every sign-out records the time and who picked the child up.',
            'Staff can see at any moment who is here right now, without walking the rooms.',
            'An emergency roll call list (everyone on site now) can be printed or opened on a phone in under a minute.',
            'Late pickups are noticed at closing time, not the next morning.',
            'Missed sign-outs are followed up the next day so records stay correct.',
            'Staff clock in and out on the same system, so ratios and hours are on record.',
            'Visitors (inspectors, therapists, contractors) sign in with the reason for their visit.',
            'Attendance reports for any date range are ready for parents, billing and licensing visits.',
            'Records are kept securely, with only the people who need them able to see them.',
        ],
    ],
    'work' => [
        'title' => 'Staff hours and front-desk checklist',
        'for'   => 'Offices, clinics, dental practices, gyms, shops, restaurants and job sites',
        'intro' => '12 checks for accurate hours, fewer payroll mistakes and a front desk that knows who is in the building.',
        'items' => [
            'Every staff member clocks in and out with the exact time, not a rounded guess on paper.',
            'Hours are totalled automatically per person and per week.',
            'Missed clock-outs are caught the next morning, not at the end of the pay period.',
            'Payroll gets a clean export (CSV) instead of retyped timesheets.',
            'Managers can see who is on site right now from any device.',
            'Visitors and contractors sign in with their name, company and who they are visiting.',
            'An emergency roll call list (everyone in the building now) opens in under a minute.',
            'Clocking in takes seconds: tap a name or scan a badge at a tablet by the door.',
            'Badges can be cancelled at once when lost or when someone leaves.',
            'Daily or weekly attendance reports arrive by email without anyone asking.',
            'Changes to records are logged, so nobody can quietly edit hours.',
            'The system works on any tablet or phone, with nothing to install.',
        ],
    ],
];

function subscriber_kind(string $k): string
{
    return isset(CHECKLISTS[$k]) ? $k : 'work';
}

function checklist_url(string $kind): string
{
    return scheme() . '://' . cfg('base_domain') . base_path() . '/checklist/' . subscriber_kind($kind);
}

function subscriber_stop_url(array $s): string
{
    return scheme() . '://' . cfg('base_domain') . base_path() . '/subscribe/stop?t=' . $s['token'];
}

/** The email body frame with the unsubscribe and postal-address footer. */
function subscriber_email(array $s, string $title, string $body, string $button, string $url): string
{
    $addr = trim((string) setting('outreach_address', ''));
    return nurture_html($title, $body, $button, $url)
        . '<p style="font-family:Arial,sans-serif;max-width:560px;margin:10px auto 0;font-size:12px;color:#8A8FA8">You asked for this on '
        . e(cfg('base_domain')) . '. <a href="' . e(subscriber_stop_url($s)) . '" style="color:#8A8FA8">Unsubscribe</a> any time.'
        . ($addr !== '' ? '<br>' . e(cfg('app_name')) . ' · ' . e($addr) : '') . '</p>';
}

/** Adds (or re-activates) a subscriber and sends the checklist. Returns the subscriber row. */
function subscribe(string $email, string $kind, ?string $source): array
{
    $kind = subscriber_kind($kind);
    $s = row('SELECT * FROM subscribers WHERE email = ?', [$email]);
    if ($s) {
        update('subscribers', ['kind' => $kind, 'unsubscribed_at' => null], 'id = ?', [$s['id']]);
        $s = row('SELECT * FROM subscribers WHERE id = ?', [$s['id']]);
    } else {
        $id = insert('subscribers', ['email' => $email, 'kind' => $kind, 'source' => $source, 'token' => bin2hex(random_bytes(16)),
            'ip' => client_ip(), 'created_at' => gmdate('Y-m-d H:i:s')]);
        $s = row('SELECT * FROM subscribers WHERE id = ?', [$id]);
    }
    if (!(int) $s['welcome_sent']) {
        $c = CHECKLISTS[$kind];
        $html = subscriber_email($s, 'Your free checklist', '<p>Hi,</p><p>Here is the <b>' . e($c['title']) . '</b> you asked for. '
            . e($c['intro']) . '</p><p>Open it, print it, and tick it off with your team. If you can tick fewer than 9 of the 12, ' . e(cfg('app_name'))
            . ' covers the rest: a tablet at the door, a live list of who is here, and reports in your inbox.</p>', 'Open the checklist', checklist_url($kind));
        if (send_mail([$email], 'Your free checklist: ' . $c['title'], $html)) {
            update('subscribers', ['welcome_sent' => 1], 'id = ?', [$s['id']]);
        }
    }
    return $s;
}

/** Hourly: one follow-up 3 days after signing up (free trial + founding offer), unless they already have a portal. */
function run_subscribers(): array
{
    $log = [];
    $due = rows('SELECT * FROM subscribers WHERE unsubscribed_at IS NULL AND welcome_sent = 1 AND followup_sent = 0 AND created_at < ? AND created_at > ? ORDER BY id LIMIT 30',
        [gmdate('Y-m-d H:i:s', time() - 3 * 86400), gmdate('Y-m-d H:i:s', time() - 10 * 86400)]); // retried for a week if sending fails
    foreach ($due as $s) {
        if (subscriber_signed_up((string) $s['email'])) {
            update('subscribers', ['followup_sent' => 1], 'id = ?', [$s['id']]); // already a customer: no sales email
            continue;
        }
        $offer = outreach_offer();
        $who = $s['kind'] === 'care' ? 'every drop-off and pickup' : 'every clock-in and clock-out';
        $body = '<p>Hi,</p><p>A few days ago you downloaded our checklist. The quickest way to tick most of it off: put a tablet by the door and let '
            . e(cfg('app_name')) . ' record ' . $who . ', with a live list of who is here and reports in your inbox at 7:00.</p>'
            . '<p>Try it free for 7 days. No credit card, and it takes about a minute to set up.</p>'
            . ($offer ? '<p style="background:#FFF6E5;border-radius:10px;padding:10px 12px"><b>' . e($offer) . '</b> Use code <b>FOUNDING50</b> when you subscribe.</p>' : '');
        $url = scheme() . '://' . cfg('base_domain') . base_path() . '/signup?src=checklist&industry=' . ($s['kind'] === 'care' ? 'school' : 'office');
        if (send_mail([(string) $s['email']], 'Tick off the whole checklist in a week', subscriber_email($s, 'Ready to try it?', $body, 'Start my free trial', $url))) {
            update('subscribers', ['followup_sent' => 1], 'id = ?', [$s['id']]);
            $log[] = 'subscriber follow-up sent';
        }
    }
    return $log ? [count($log) . ' subscriber follow-up(s) sent'] : [];
}

/** True when this subscriber has since signed up for a portal (then no sales emails). */
function subscriber_signed_up(string $email): bool
{
    return (bool) val('SELECT 1 FROM subscribers WHERE email = ? AND signed_up_at IS NOT NULL', [strtolower($email)]);
}

/** Called at sign-up: marks the subscriber as a customer. */
function subscriber_converted(string $email): void
{
    try {
        q('UPDATE subscribers SET signed_up_at = ? WHERE email = ? AND signed_up_at IS NULL', [gmdate('Y-m-d H:i:s'), strtolower(trim($email))]);
    } catch (Throwable $e) {
        // the table may not exist yet during an update
    }
}
