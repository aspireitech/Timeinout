<?php
// Outreach to prospects (organizations that have not signed up yet), run by the hourly cron.
//   Email 1 → follow-up after 4 days → last note after 5 more days, then the prospect is "done".
//   Stops at once when the prospect signs up, books a demo, replies (marked in /super), unsubscribes or bounces.
// Sends only on weekdays in business hours (outreach_tz), at most prospects_daily emails a day, spread over the day.
// Nothing is sent until it is turned on in /super → Prospects and a business address is saved (CAN-SPAM).

const PROSPECT_STATUSES = [
    'new' => 'Queued', 'active' => 'Emailing', 'done' => 'No answer', 'replied' => 'Replied', 'demo' => 'Booked demo',
    'signed_up' => 'Signed up', 'unsubscribed' => 'Unsubscribed', 'bounced' => 'Bounced', 'removed' => 'Removed',
];
/** Statuses that stop the email sequence. */
const PROSPECT_STOPPED = ['done', 'replied', 'demo', 'signed_up', 'unsubscribed', 'bounced', 'removed'];
const PROSPECT_KINDS = ['tutoring' => 'Tutoring / learning center', 'daycare' => 'Daycare / preschool', 'school' => 'Private school', 'other' => 'Other'];
/** Days to wait after email 1 and after the follow-up. */
const PROSPECT_GAPS = [1 => 4, 2 => 5];
/** Personal mailbox domains: several contacts on these are not the same organization. */
const FREEMAIL = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'aol.com', 'icloud.com', 'live.com', 'msn.com', 'comcast.net', 'me.com'];
/** Franchise mailbox domains: each center is its own business (e.g. draper@mathnasium.com), so they count like personal mailboxes. */
const SHARED_DOMAINS = ['ikumon.com', 'kumon.com', 'mathnasium.com', 'sylvanlearning.com', 'huntingtonhelps.com', 'clubztutoring.com', 'tutordoctor.com',
    'thetutoringcenter.com', 'eyelevel.com', 'goddardschools.com', 'primroseschools.com', 'kiddieacademy.com', 'thelearningexperience.com',
    'brightfutureskids.com', 'codeninjas.com', 'bestinclass.com', 'jeiamerica.com', 'c2educate.com', 'kidsrkids.com'];

/** true when different addresses on this domain are different organizations. */
function prospect_shared_domain(string $dom): bool
{
    return in_array($dom, FREEMAIL, true) || in_array($dom, SHARED_DOMAINS, true);
}

function prospect_domain(string $email): string
{
    return strtolower(substr(strrchr($email, '@') ?: '', 1));
}

/** Why an address cannot be added, or null. */
function prospect_problem(array $p): ?string
{
    $email = $p['email'];
    if ($p['org'] === '') {
        return 'organization missing';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'invalid email';
    }
    $dom = prospect_domain($email);
    if (preg_match('/(^|\.)(k12\.[a-z]{2}\.us|gov|mil)$/', $dom) || str_contains($dom, 'k12') || $dom === strtolower((string) cfg('base_domain'))) {
        return 'public school district or government address (they block outside email)';
    }
    if (preg_match('/^(no-?reply|donotreply|postmaster|abuse|webmaster|privacy|careers|jobs|hr|billing|accounts|payroll)@/i', $email)) {
        return 'not a person or front office address';
    }
    if (val('SELECT 1 FROM prospects WHERE email = ?', [$email])) {
        return 'already in the list';
    }
    if (!prospect_shared_domain($dom) && val('SELECT 1 FROM prospects WHERE email LIKE ?', ['%@' . $dom])) {
        return 'organization already in the list';
    }
    if (prospect_customer_state($email)) {
        return 'already a customer or lead';
    }
    return null;
}

/** 'signed_up' or 'demo' when this address (or its organization) already came to us, else null. */
function prospect_customer_state(string $email): ?string
{
    $dom = prospect_domain($email);
    $orgWide = !prospect_shared_domain($dom);
    if (val('SELECT 1 FROM tenants WHERE report_emails LIKE ?', ['%' . ($orgWide ? '@' . $dom : $email) . '%'])) {
        return 'signed_up';
    }
    if (val('SELECT 1 FROM leads WHERE email = ?' . ($orgWide ? ' OR email LIKE ?' : ''), $orgWide ? [$email, '%@' . $dom] : [$email])) {
        return 'demo';
    }
    return null;
}

/** Cleans one incoming prospect (from the API or the paste box). */
function prospect_clean(array $r): array
{
    $s = fn($k, $n) => mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($r[$k] ?? ''))), 0, $n);
    $kind = strtolower($s('kind', 30));
    $state = strtoupper($s('state', 4));
    $site = $s('website', 255);
    return [
        'org'        => $s('org', 160),
        'first_name' => ucfirst($s('first_name', 60)) ?: null,
        'email'      => strtolower($s('email', 190)),
        'phone'      => $s('phone', 40) ?: null,
        'website'    => preg_match('#^https?://#i', $site) ? $site : ($site !== '' ? 'https://' . $site : null),
        'city'       => $s('city', 60) ?: null,
        'state'      => preg_match('/^[A-Z]{2}$/', $state) ? $state : null,
        'kind'       => isset(PROSPECT_KINDS[$kind]) ? $kind : 'other',
        'found_at'   => $s('source', 255) ?: null,
    ];
}

/** Adds prospects. Returns ['added' => n, 'skipped' => [[email, reason], ...]]. */
function prospects_add(array $list, string $by): array
{
    $added = 0;
    $skipped = [];
    foreach (array_slice($list, 0, 200) as $r) {
        if (!is_array($r)) {
            continue;
        }
        $p = prospect_clean($r);
        if ($why = prospect_problem($p)) {
            $skipped[] = [$p['email'] ?: ($p['org'] ?: '?'), $why];
            continue;
        }
        insert('prospects', $p + ['status' => 'new', 'step' => 0, 'token' => bin2hex(random_bytes(16)), 'added_by' => $by, 'created_at' => gmdate('Y-m-d H:i:s')]);
        $added++;
    }
    return ['added' => $added, 'skipped' => $skipped];
}

/** Called when someone signs up or books a demo: stop emailing them (and their organization). */
function prospect_converted(string $email, string $status): void
{
    try {
        $email = strtolower(trim($email));
        $dom = prospect_domain($email);
        $shared = prospect_shared_domain($dom);
        q('UPDATE prospects SET status = ?, next_at = NULL WHERE ' . ($shared ? 'email = ?' : 'email LIKE ?') . " AND status NOT IN ('signed_up','unsubscribed')",
            [$status, $shared ? $email : '%@' . $dom]);
    } catch (Throwable $e) {
        // the prospects table may not exist yet during an update
    }
}

function outreach_tz(): DateTimeZone
{
    $tz = (string) setting('outreach_tz', 'America/Denver');
    return new DateTimeZone(valid_tz($tz) ? $tz : 'America/Denver');
}

/** What is still missing before outreach can send, or null. */
function outreach_problem(): ?string
{
    if (trim((string) setting('outreach_name', '')) === '') {
        return 'Add your name for the signature.';
    }
    if (trim((string) setting('outreach_address', '')) === '') {
        return 'Add your business postal address (required by US law in sales emails).';
    }
    return null;
}

/** The email for a prospect at a step (0 = first email, 1 = follow-up, 2 = last note): [subject, html]. */
function prospect_email(array $p, int $step): array
{
    $site = scheme() . '://' . cfg('base_domain') . base_path();
    $signup = $site . '/signup?src=outreach';
    $demo = $site . '/demo?src=outreach';
    $hi = 'Hi ' . e($p['first_name'] ?: $p['org'] . ' team') . ',';
    $org = $p['org'];
    $font = 'font-family:Arial,Helvetica,sans-serif;';
    $para = fn($html) => '<p style="margin:0 0 14px">' . $html . '</p>';
    $button = '<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:6px auto 4px"><tr><td align="center" bgcolor="#F2A93B" style="background-color:#F2A93B;border-radius:28px;padding:14px 32px">'
        . '<a href="' . e($signup) . '" style="color:#161B36;' . $font . 'font-size:17px;font-weight:bold;text-decoration:none">Start my free 7-day trial &rarr;</a></td></tr></table>'
        . '<p style="margin:0 0 18px;text-align:center;font-size:13px;color:#6B7090">No credit card. Your own portal in about a minute.</p>';
    $phone = trim((string) setting('outreach_phone', ''));
    $sign = '<p style="margin:0">Best,<br><b>' . e((string) setting('outreach_name', '')) . '</b><br>' . e(cfg('app_name'))
        . ' &middot; <a href="' . e($site . '/?src=outreach') . '" style="color:#5B4CE0">' . e(cfg('base_domain')) . '</a>' . ($phone !== '' ? ' &middot; ' . e($phone) : '') . '</p>';
    $foot = '<tr><td style="background:#F4F5FA;padding:14px 32px;font-size:12px;line-height:1.5;color:#6B7090">'
        . 'Not the right person, or not interested? Reply and let me know, or <a href="' . e($site . '/unsubscribe?t=' . $p['token']) . '" style="color:#6B7090">unsubscribe</a>, and I won\'t email again.<br>'
        . nl2br(e(trim((string) setting('outreach_address', '')))) . '</td></tr>';

    if ($step === 0) {
        $subject = $org . ': who picked up whom, and when?';
        $check = fn($t) => '<tr><td valign="top" style="color:#0E8A66;font-weight:bold;padding:3px 10px 3px 0">&#10003;</td><td style="padding:3px 0">' . $t . '</td></tr>';
        $body = '<tr><td><a href="' . e($signup) . '"><img src="' . e($site . '/assets/img/og/og-default.jpg') . '" width="600" alt="' . e(cfg('app_name')) . ': your front door, on the clock" style="display:block;width:100%;max-width:600px;height:auto;border:0"></a></td></tr>'
            . '<tr><td style="padding:28px 32px 8px;font-size:16px;line-height:1.6">' . $para($hi)
            . $para('Most centers I talk to still run the front door on a paper sheet. It\'s hard to read, easy to skip, and no help when a parent asks, <i>"Who picked up my child, and when?"</i>')
            . '<p style="margin:0 0 10px"><b>' . e(cfg('app_name')) . ' turns any tablet at your door into a check-in kiosk:</b></p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.5;margin:0 0 14px">'
            . $check('Families tap their name and choose who is with them: Mom, Dad or an approved relative')
            . $check('See who is in, who has left, and who picked them up, live')
            . $check('Daily, weekly and monthly attendance reports arrive in your inbox, no more typing')
            . $check('Track hand-offs like homework folders, worksheets and books') . '</table>'
            . '<p style="margin:0 0 6px">Getting started is simple: upload your Excel list, open the kiosk on a tablet, and you\'re live. Plans start at <b>$' . number_format(plans()['starter']['amount'] ?? 39, 0) . '/month</b>.</p></td></tr>'
            . '<tr><td style="padding:4px 32px 0">' . $button . '</td></tr>'
            . '<tr><td style="padding:0 32px 26px;font-size:16px;line-height:1.6">'
            . $para('Prefer a quick walkthrough first? <a href="' . e($demo) . '" style="color:#5B4CE0;font-weight:bold">Book a live demo</a> and I\'ll show you with your own list.') . $sign . '</td></tr>';
    } elseif ($step === 1) {
        $subject = 'Following up: sign-in and pickups at ' . $org;
        $body = '<tr><td style="padding:28px 32px 8px;font-size:16px;line-height:1.6">' . $para($hi)
            . $para('A quick follow-up on my note last week. If a paper sign-in sheet is still part of your day, ' . e(cfg('app_name')) . ' can replace it this afternoon: a tablet at the door, families tap their name, and you always know who came, who left and who picked them up.')
            . $para('You can try it free for 7 days with your own list, or reply with a good time and I\'ll show you in 10 minutes.') . '</td></tr>'
            . '<tr><td style="padding:0 32px">' . $button . '</td></tr>'
            . '<tr><td style="padding:0 32px 26px;font-size:16px;line-height:1.6">' . $sign . '</td></tr>';
    } else {
        $subject = 'Last note about sign-in at ' . $org;
        $body = '<tr><td style="padding:28px 32px 26px;font-size:16px;line-height:1.6">' . $para($hi)
            . $para('I haven\'t heard back, so I\'ll assume the timing isn\'t right and won\'t follow up again.')
            . $para('If paper sign-in sheets or pickup questions become a headache later, you can start a free trial anytime at <a href="' . e($signup) . '" style="color:#5B4CE0;font-weight:bold">' . e(cfg('base_domain')) . '</a>, or just reply to this email.')
            . $para('Thanks for your time, and have a great week.') . $sign . '</td></tr>';
    }
    $html = '<div style="display:none;max-height:0;overflow:hidden;opacity:0">Families tap their name, pick who is with them, done. Try it free for 7 days.</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F5FA"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border-radius:14px;overflow:hidden;' . $font . 'color:#161B36">'
        . $body . $foot . '</table></td></tr></table>';
    return [$subject, $html];
}

/** Hourly: send what is due. Returns log lines for the cron output. */
function run_prospects(bool $force = false): array
{
    if (setting('prospects_on', '0') !== '1' || outreach_problem()) {
        return [];
    }
    $now = new DateTime('now', outreach_tz());
    $hour = (int) $now->format('G');
    if (!$force && ((int) $now->format('N') > 5 || $hour < 8 || $hour >= 16)) {
        return []; // weekdays 8:00–16:00 in the outreach time zone
    }
    $cap = max(1, min(100, (int) setting('prospects_daily', '20')));
    $dayStart = (clone $now)->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $sentToday = (int) val('SELECT COUNT(*) FROM prospects WHERE last_sent_at >= ?', [$dayStart]);
    // Spread the day's emails over the 8 sending hours: at most cap/8 per hourly run (3 when the cap is 20)
    $room = min($cap - $sentToday, $force ? $cap : (int) ceil($cap / 8));
    if ($room <= 0) {
        return [];
    }
    $utc = gmdate('Y-m-d H:i:s');
    $due = rows("SELECT * FROM prospects WHERE (status = 'active' AND next_at <= ?) OR status = 'new'
                 ORDER BY status = 'new', next_at, id LIMIT " . (int) $room, [$utc]);
    $log = [];
    foreach ($due as $p) {
        if ($state = prospect_customer_state($p['email'])) {
            update('prospects', ['status' => $state, 'next_at' => null], 'id = ?', [$p['id']]);
            continue;
        }
        $step = (int) $p['step'];
        if ($step > 2) {
            update('prospects', ['status' => 'done', 'next_at' => null], 'id = ?', [$p['id']]);
            continue;
        }
        [$subject, $html] = prospect_email($p, $step);
        if (!send_mail([$p['email']], $subject, $html)) {
            $log[] = 'prospect ' . $p['email'] . ': email failed: ' . ($GLOBALS['mail_error'] ?? '');
            break; // mailbox problem: try again next hour
        }
        $next = $step + 1;
        update('prospects', [
            'step' => $next, 'last_sent_at' => $utc,
            'status' => $next > 2 ? 'done' : 'active',
            'next_at' => $next > 2 ? null : gmdate('Y-m-d H:i:s', strtotime('+' . PROSPECT_GAPS[$next] . ' days')),
        ], 'id = ?', [$p['id']]);
        $log[] = 'prospect ' . $p['org'] . ': email ' . $next . ' sent';
    }
    return $log;
}

/** One line for the Monday scorecard. */
function prospects_week_summary(): string
{
    try {
        $since = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
        $c = array_column(rows('SELECT status, COUNT(*) AS n FROM prospects GROUP BY status'), 'n', 'status');
        $added = (int) val('SELECT COUNT(*) FROM prospects WHERE created_at >= ?', [$since]);
        $emailed = (int) val('SELECT COUNT(*) FROM prospects WHERE last_sent_at >= ?', [$since]);
        return 'Added this week: ' . $added . ' · emailed this week: ' . $emailed . ' · replied: ' . (int) ($c['replied'] ?? 0)
            . ' · demos: ' . (int) ($c['demo'] ?? 0) . ' · signed up: ' . (int) ($c['signed_up'] ?? 0) . ' · unsubscribed: ' . (int) ($c['unsubscribed'] ?? 0)
            . ' · queued: ' . (int) ($c['new'] ?? 0) . (setting('prospects_on', '0') === '1' ? '' : ' · <b>sending is off</b>');
    } catch (Throwable $e) {
        return 'Not set up yet.';
    }
}
