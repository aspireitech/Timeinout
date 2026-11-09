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
const PROSPECT_KINDS = [
    'clinic' => 'Clinic / medical', 'dental' => 'Dental office', 'daycare' => 'Daycare / preschool', 'tutoring' => 'Tutoring / learning center',
    'school' => 'Private school', 'office' => 'Office / professional services', 'gym' => 'Gym / studio', 'construction' => 'Construction / trades / field crew',
    'retail' => 'Retail store', 'restaurant' => 'Restaurant / café', 'other' => 'Other small business',
];
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

/** Main time zone of each US state (where most people live), to send at the recipient's local business hours. */
const STATE_TZ = [
    'AL' => 'America/Chicago', 'AK' => 'America/Anchorage', 'AZ' => 'America/Phoenix', 'AR' => 'America/Chicago', 'CA' => 'America/Los_Angeles',
    'CO' => 'America/Denver', 'CT' => 'America/New_York', 'DE' => 'America/New_York', 'DC' => 'America/New_York', 'FL' => 'America/New_York',
    'GA' => 'America/New_York', 'HI' => 'Pacific/Honolulu', 'ID' => 'America/Boise', 'IL' => 'America/Chicago', 'IN' => 'America/Indiana/Indianapolis',
    'IA' => 'America/Chicago', 'KS' => 'America/Chicago', 'KY' => 'America/New_York', 'LA' => 'America/Chicago', 'ME' => 'America/New_York',
    'MD' => 'America/New_York', 'MA' => 'America/New_York', 'MI' => 'America/Detroit', 'MN' => 'America/Chicago', 'MS' => 'America/Chicago',
    'MO' => 'America/Chicago', 'MT' => 'America/Denver', 'NE' => 'America/Chicago', 'NV' => 'America/Los_Angeles', 'NH' => 'America/New_York',
    'NJ' => 'America/New_York', 'NM' => 'America/Denver', 'NY' => 'America/New_York', 'NC' => 'America/New_York', 'ND' => 'America/Chicago',
    'OH' => 'America/New_York', 'OK' => 'America/Chicago', 'OR' => 'America/Los_Angeles', 'PA' => 'America/New_York', 'RI' => 'America/New_York',
    'SC' => 'America/New_York', 'SD' => 'America/Chicago', 'TN' => 'America/Chicago', 'TX' => 'America/Chicago', 'UT' => 'America/Denver',
    'VT' => 'America/New_York', 'VA' => 'America/New_York', 'WA' => 'America/Los_Angeles', 'WV' => 'America/New_York', 'WI' => 'America/Chicago',
    'WY' => 'America/Denver', 'PR' => 'America/Puerto_Rico',
];

/** The time zone of a US state, or the outreach time zone when the state is unknown. */
function state_tz(?string $state): DateTimeZone
{
    $tz = STATE_TZ[strtoupper((string) $state)] ?? null;
    return $tz ? new DateTimeZone($tz) : outreach_tz();
}

/**
 * Is it a good moment to land in this prospect's inbox? Their local time:
 * Tue–Thu 9:00–12:00 and 13:00–15:00, Mon 10:00–12:00 and 13:00–15:00 (after the Monday-morning pile), Fri 9:00–12:00. Never weekends or lunch.
 */
function prospect_send_window(?string $state, ?DateTime $at = null): bool
{
    $local = (clone ($at ?? new DateTime('now')))->setTimezone(state_tz($state));
    $day = (int) $local->format('N');
    $h = (int) $local->format('G');
    if ($day > 5 || $h === 12) {
        return false;
    }
    return match ($day) {
        1 => $h >= 10 && $h < 15,
        5 => $h >= 9 && $h < 12,
        default => $h >= 9 && $h < 15,
    };
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

/**
 * Sales message per kind of business: banner, subject, opening, benefits and the industry the sign-up form starts with.
 * Every claim here must match what the product really does.
 */
function prospect_pitch(string $kind): array
{
    $staff = [
        'Staff clock in and out in two taps on any tablet at the front desk, with nothing to install',
        'Hours are totalled per person, and the weekly hours report (PDF and CSV) arrives in your inbox Monday morning, ready for payroll',
        'See who is on site right now, from anywhere',
    ];
    $p = [
        'kids' => [
            'img' => 'og-default.jpg', 'industry' => 'school',
            'subject' => '%s: who picked up whom, and when?',
            'open' => 'Most centers I talk to still run the front door on a paper sheet. It\'s hard to read, easy to skip, and no help when a parent asks, <i>"Who picked up my child, and when?"</i>',
            'lead' => 'turns any tablet at your door into a check-in kiosk:',
            'points' => ['Families tap their name and choose who is with them: Mom, Dad or an approved relative', 'See who is in, who has left, and who picked them up, live',
                'Daily, weekly and monthly attendance reports arrive in your inbox, no more typing', 'Staff clock in on the same tablet, so their hours are tracked too'],
            'short' => 'a tablet at the door, families tap their name, and you always know who came, who left and who picked them up',
        ],
        'clinic' => [
            'img' => 'og-healthcare.jpg', 'industry' => 'clinic',
            'subject' => '%s: staff hours without the paper timesheet',
            'open' => 'Most practices I talk to still track front-desk and clinical staff hours on paper or from memory, and it turns into back-and-forth at payroll time.',
            'lead' => 'turns a tablet at your front desk into a time clock and sign-in desk:',
            'points' => array_merge($staff, ['Vendors and visitors sign in and out on the same tablet, so you have a dated record of who was in the building']),
            'short' => 'a tablet at the front desk, staff clock in with two taps, and their weekly hours land in your inbox ready for payroll',
        ],
        'office' => [
            'img' => 'og-offices.jpg', 'industry' => 'office',
            'subject' => '%s: who\'s in today, and for how long?',
            'open' => 'Timesheets filled in from memory are the norm in small offices, and nobody enjoys fixing them at payroll time.',
            'lead' => 'turns any tablet or spare phone into a simple time clock:',
            'points' => array_merge($staff, ['Visitors sign in at reception, and packages are logged with who collected them']),
            'short' => 'staff clock in with two taps on a tablet, and their weekly hours land in your inbox ready for payroll',
        ],
        'gym' => [
            'img' => 'og-gyms.jpg', 'industry' => 'gym',
            'subject' => '%s: member check-ins and trainer hours on one tablet',
            'open' => 'Most studios I talk to still use a clipboard or a spreadsheet at the front desk, so nobody really knows how many members come, or how many hours trainers worked.',
            'lead' => 'turns a tablet by your entrance into a check-in desk:',
            'points' => ['Members check in in seconds: type a few letters, tap their name, done', 'Guests sign in and out with a dated record',
                'Trainers and staff clock in on the same tablet, with hours totalled per person', 'Daily and monthly attendance reports, emailed and exportable as CSV'],
            'short' => 'members and trainers check in on one tablet, and attendance and trainer hours are emailed to you',
        ],
        'construction' => [
            'img' => 'og-construction.jpg', 'industry' => 'construction',
            'subject' => '%s: crew hours you can trust',
            'open' => 'Paper timesheets from the field are hard to verify, and in an emergency it\'s hard to say exactly who is on site.',
            'lead' => 'turns a tablet or phone at the site or shop into a time clock:',
            'points' => ['Crews clock in and out in two taps, with hours totalled per worker and week', 'A live headcount of who is on site, ready for a roll call',
                'Subcontractors sign in with their company and arrival time', 'Tools and equipment checked out are logged with who took them'],
            'short' => 'crews clock in on a tablet at the site, and their weekly hours land in your inbox ready for payroll',
        ],
    ];
    $map = ['tutoring' => 'kids', 'daycare' => 'kids', 'school' => 'kids', 'clinic' => 'clinic', 'dental' => 'clinic', 'office' => 'office',
            'gym' => 'gym', 'construction' => 'construction', 'retail' => 'office', 'restaurant' => 'office', 'other' => 'office'];
    $x = $p[$map[$kind] ?? 'office'];
    $industries = ['tutoring' => 'tutoring', 'retail' => 'retail', 'restaurant' => 'restaurant'];
    $x['industry'] = $industries[$kind] ?? $x['industry'];
    if ($kind === 'dental') {
        $x['open'] = 'Most dental offices I talk to still track front-desk, hygienist and assistant hours on paper or from memory, and it turns into back-and-forth at payroll time.';
    }
    if (in_array($kind, ['retail', 'restaurant'], true)) {
        $x['subject'] = '%s: shift hours without the punch card';
        $x['open'] = 'Punch cards and paper schedules make it hard to know who actually worked which shift, and payroll takes longer than it should.';
    }
    return $x;
}

/** The email for a prospect at a step (0 = first email, 1 = follow-up, 2 = last note): [subject, html]. */
function prospect_email(array $p, int $step): array
{
    $x = prospect_pitch((string) $p['kind']);
    $site = scheme() . '://' . cfg('base_domain') . base_path();
    $signup = $site . '/signup?src=outreach&industry=' . $x['industry'];
    $demo = $site . '/demo?src=outreach';
    $hi = 'Hi ' . e($p['first_name'] ?: $p['org'] . ' team') . ',';
    $org = $p['org'];
    $app = e(cfg('app_name'));
    $font = 'font-family:Arial,Helvetica,sans-serif;';
    $para = fn($html) => '<p style="margin:0 0 14px">' . $html . '</p>';
    $button = '<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:6px auto 4px"><tr><td align="center" bgcolor="#F2A93B" style="background-color:#F2A93B;border-radius:28px;padding:14px 32px">'
        . '<a href="' . e($signup) . '" style="color:#161B36;' . $font . 'font-size:17px;font-weight:bold;text-decoration:none">Start my free 7-day trial &rarr;</a></td></tr></table>'
        . '<p style="margin:0 0 18px;text-align:center;font-size:13px;color:#6B7090">No credit card. Your own account in about a minute.</p>';
    $phone = trim((string) setting('outreach_phone', ''));
    $sign = '<p style="margin:0">Best,<br><b>' . e((string) setting('outreach_name', '')) . '</b><br>' . $app
        . ' &middot; <a href="' . e($site . '/?src=outreach') . '" style="color:#5B4CE0">' . e(cfg('base_domain')) . '</a>' . ($phone !== '' ? ' &middot; ' . e($phone) : '') . '</p>';
    $foot = '<tr><td style="background:#F4F5FA;padding:14px 32px;font-size:12px;line-height:1.5;color:#6B7090">'
        . 'Not the right person, or not interested? Reply and let me know, or <a href="' . e($site . '/unsubscribe?t=' . $p['token']) . '" style="color:#6B7090">unsubscribe</a>, and I won\'t email again.<br>'
        . nl2br(e(trim((string) setting('outreach_address', '')))) . '</td></tr>';

    if ($step === 0) {
        $subject = sprintf($x['subject'], $org);
        $rows = '';
        foreach ($x['points'] as $t) {
            $rows .= '<tr><td valign="top" style="color:#0E8A66;font-weight:bold;padding:3px 10px 3px 0">&#10003;</td><td style="padding:3px 0">' . e($t) . '</td></tr>';
        }
        $body = '<tr><td><a href="' . e($signup) . '"><img src="' . e($site . '/assets/img/og/' . $x['img']) . '" width="600" alt="' . $app . ': time and attendance on any tablet" style="display:block;width:100%;max-width:600px;height:auto;border:0"></a></td></tr>'
            . '<tr><td style="padding:28px 32px 8px;font-size:16px;line-height:1.6">' . $para($hi) . $para($x['open'])
            . '<p style="margin:0 0 10px"><b>' . $app . ' ' . $x['lead'] . '</b></p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.5;margin:0 0 14px">' . $rows . '</table>'
            . '<p style="margin:0 0 6px">Getting started is simple: upload your Excel list of people, open the page on a tablet, and you\'re live. Plans start at <b>$' . number_format(plans()['starter']['amount'] ?? 39, 0) . '/month</b>.</p></td></tr>'
            . '<tr><td style="padding:4px 32px 0">' . $button . '</td></tr>'
            . '<tr><td style="padding:0 32px 26px;font-size:16px;line-height:1.6">'
            . $para('Prefer a quick walkthrough first? <a href="' . e($demo) . '" style="color:#5B4CE0;font-weight:bold">Book a live demo</a> and I\'ll show you with your own list.') . $sign . '</td></tr>';
    } elseif ($step === 1) {
        $subject = 'Quick follow-up for ' . $org;
        $body = '<tr><td style="padding:28px 32px 8px;font-size:16px;line-height:1.6">' . $para($hi)
            . $para('A quick follow-up on my note last week. ' . $app . ' can be running at ' . e($org) . ' this afternoon: ' . e($x['short']) . '.')
            . $para('You can try it free for 7 days with your own list, or reply with a good time and I\'ll show you in 10 minutes.') . '</td></tr>'
            . '<tr><td style="padding:0 32px">' . $button . '</td></tr>'
            . '<tr><td style="padding:0 32px 26px;font-size:16px;line-height:1.6">' . $sign . '</td></tr>';
    } else {
        $subject = 'Last note for ' . $org;
        $body = '<tr><td style="padding:28px 32px 26px;font-size:16px;line-height:1.6">' . $para($hi)
            . $para('I haven\'t heard back, so I\'ll assume the timing isn\'t right and won\'t follow up again.')
            . $para('If timesheets or sign-in sheets become a headache later, you can start a free trial anytime at <a href="' . e($signup) . '" style="color:#5B4CE0;font-weight:bold">' . e(cfg('base_domain')) . '</a>, or just reply to this email.')
            . $para('Thanks for your time, and have a great week.') . $sign . '</td></tr>';
    }
    $html = '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . e(ucfirst($x['short'])) . '. Try it free for 7 days.</div>'
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
    $cap = max(1, min(100, (int) setting('prospects_daily', '20')));
    $dayStart = (clone $now)->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $sentToday = (int) val('SELECT COUNT(*) FROM prospects WHERE last_sent_at >= ?', [$dayStart]);
    // Spread the day's emails: at most cap/5 per hourly run (4 when the cap is 20)
    $room = min($cap - $sentToday, $force ? $cap : (int) ceil($cap / 5));
    if ($room <= 0) {
        return [];
    }
    $utc = gmdate('Y-m-d H:i:s');
    $due = rows("SELECT * FROM prospects WHERE (status = 'active' AND next_at <= ?) OR status = 'new'
                 ORDER BY status = 'new', next_at, id LIMIT 500", [$utc]);
    // Only those whose local time is a good moment right now (see prospect_send_window)
    $due = array_slice(array_values(array_filter($due, fn($p) => $force || prospect_send_window($p['state']))), 0, $room);
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
