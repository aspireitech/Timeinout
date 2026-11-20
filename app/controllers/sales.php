<?php
// Provider console → Sales dashboard, and the tracked links / optional open pixel in outreach emails.

function super_sales(): void
{
    require_super();
    [$from, $to, $group, $preset] = sales_range();
    $d = sales_dashboard($from, $to, $group);
    [$start, $end] = sales_utc_bounds($from, $to);
    $web = traffic_report($start, $end);
    $visits = array_fill_keys(array_keys($d['buckets']), 0);
    foreach (rows('SELECT vid, MIN(at) AS at FROM site_visits WHERE at >= ? AND at < ? GROUP BY vid', [$start, $end]) as $r) {
        [$k] = sales_bucket($r['at'], $group);
        if (isset($visits[$k])) {
            $visits[$k]++;
        }
    }
    $hot = sales_hot_leads(7, 50);
    view('platform/super_sales', compact('from', 'to', 'group', 'preset', 'd', 'web', 'visits', 'hot'), 'platform');
}

/** /r?t=<token>&s=<step>&to=<signup|demo|home>: counts the click, then opens the page. */
function platform_track_click(): void
{
    $token = (string) input('t');
    $to = in_array(input('to'), ['signup', 'demo', 'home', 'hello'], true) ? (string) input('to') : 'home';
    $p = preg_match('/^[a-f0-9]{32}$/', $token) ? row('SELECT * FROM prospects WHERE token = ?', [$token]) : null;
    if ($p && empty($_SESSION['super'])) { // your own clicks in a preview do not count
        $step = max(1, min(3, (int) input('s')));
        $sentAt = val("SELECT MAX(at) FROM prospect_events WHERE prospect_id = ? AND type = 'sent' AND step = ?", [$p['id'], $step]);
        if (!sales_is_bot($sentAt ? (string) $sentAt : null)) {
            prospect_event((int) $p['id'], 'clicked', $step, $to);
            // Remember them: a sign-up or demo request later, even from another address, is credited to this prospect
            $opt = ['expires' => time() + 90 * 86400, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax'];
            setcookie('cio_p', $token, $opt);
            if (!isset($_COOKIE['cio_src'])) {
                setcookie('cio_src', 'outreach', $opt);
            }
        }
    }
    redirect(prospect_link_target($to, $p));
}

/** /o?t=<token>&s=<step>: 1×1 image for the optional open count. */
function platform_track_open(): void
{
    $token = (string) input('t');
    if (setting('outreach_track_opens', '0') === '1' && preg_match('/^[a-f0-9]{32}$/', $token) && empty($_SESSION['super'])
        && ($id = val('SELECT id FROM prospects WHERE token = ?', [$token]))) {
        $step = max(1, min(3, (int) input('s')));
        if (!val("SELECT 1 FROM prospect_events WHERE prospect_id = ? AND type = 'opened' AND step = ? LIMIT 1", [$id, $step])) {
            prospect_event((int) $id, 'opened', $step);
        }
    }
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, max-age=0');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

/** What each clickable number on the dashboard lists. */
const SALES_LISTS = [
    'found' => 'Prospects found', 'emails' => 'Emails sent', 'emailed' => 'Prospects emailed', 'delivered' => 'Delivered (emailed, not bounced)',
    'bounced' => 'Bounced', 'opened' => 'Opened (estimate)', 'clicked' => 'Clicked a link', 'replied' => 'Replied', 'interested' => 'Interested (replied, demo or sign-up)',
    'demo' => 'Booked a demo', 'signed_up' => 'Signed up', 'unsubscribed' => 'Unsubscribed',
    'visits' => 'Website visits', 'web_demos' => 'Demo requests (all sources)', 'web_signups' => 'Sign-ups (all sources)', 'paying' => 'Paying customers from outreach',
    'subscribers' => 'Checklist sign-ups (email list)',
];

/** /super/sales/list?m=<metric>&range…: the records behind a number on the dashboard, with CSV export. */
function super_sales_list(): void
{
    require_super();
    [$from, $to, $group, $preset] = sales_range();
    [$start, $end] = sales_utc_bounds($from, $to);
    $m = isset(SALES_LISTS[(string) input('m')]) ? (string) input('m') : 'emailed';
    $kind = isset(PROSPECT_KINDS[(string) input('kind')]) ? (string) input('kind') : '';
    $state = preg_match('/^[A-Z]{2}$/', (string) input('state')) ? (string) input('state') : (input('state') === '-' ? '-' : '');
    $source = traffic_tag(input('source')) ?? '';
    $filter = '';
    $fp = [];
    if ($kind !== '') {
        $filter .= ' AND p.kind = ?';
        $fp[] = $kind;
    }
    if ($state !== '') {
        $filter .= $state === '-' ? " AND (p.state IS NULL OR p.state = '')" : ' AND p.state = ?';
        if ($state !== '-') {
            $fp[] = $state;
        }
    }
    $cols = [];
    $rows = [];
    $pcols = ['when' => 'When', 'org' => 'Organization', 'type' => 'Type', 'where' => 'City / state', 'email' => 'Email', 'phone' => 'Phone', 'detail' => 'Detail', 'now' => 'Status now'];
    $prow = fn(array $r, string $detail = '') => ['when' => $r['at'], 'org' => $r['org'], 'type' => PROSPECT_KINDS[$r['kind']] ?? $r['kind'],
        'where' => trim(($r['city'] ?? '') . ($r['state'] ? ', ' . $r['state'] : ''), ', '), 'email' => $r['email'], 'phone' => (string) $r['phone'],
        'detail' => $detail, 'now' => PROSPECT_STATUSES[$r['status']] ?? $r['status'], '_id' => (int) $r['id']];
    $stepName = fn($s) => ['', 'Email 1', 'Follow-up', 'Last note'][(int) $s] ?? '';
    if (in_array($m, ['found', 'emails', 'bounced', 'opened', 'clicked', 'replied', 'demo', 'signed_up', 'unsubscribed'], true)) {
        $type = $m === 'emails' ? 'sent' : $m;
        $people = $m !== 'emails'; // one row per person, except "emails sent"
        $sql = $people
            ? "SELECT p.*, MAX(e.at) AS at, COUNT(*) AS n, GROUP_CONCAT(DISTINCT e.detail) AS details, MAX(e.step) AS step FROM prospect_events e JOIN prospects p ON p.id = e.prospect_id
               WHERE e.type = ? AND e.at >= ? AND e.at < ? $filter GROUP BY p.id ORDER BY at DESC LIMIT 2000"
            : "SELECT p.*, e.at, e.step FROM prospect_events e JOIN prospects p ON p.id = e.prospect_id WHERE e.type = ? AND e.at >= ? AND e.at < ? $filter ORDER BY e.at DESC LIMIT 2000";
        $notes = [];
        if (in_array($m, ['bounced', 'replied'], true)) {
            foreach (rows("SELECT prospect_id, outcome_note FROM prospect_sends WHERE outcome = ?", [$m]) as $s) {
                $notes[(int) $s['prospect_id']] = (string) $s['outcome_note'];
            }
        }
        foreach (rows($sql, array_merge([$type, $start, $end], $fp)) as $r) {
            $detail = match ($m) {
                'emails' => $stepName($r['step']),
                'clicked' => 'Looked at: ' . implode(', ', array_map(fn($x) => ['signup' => 'trial sign-up', 'demo' => 'demo page', 'home' => 'website'][$x] ?? $x, array_filter(explode(',', (string) $r['details'])))) . ((int) $r['n'] > 1 ? ' · ' . (int) $r['n'] . ' clicks' : ''),
                'opened' => 'Opened ' . $stepName($r['step']),
                'found' => 'Added by ' . $r['added_by'],
                default => $notes[(int) $r['id']] ?? '',
            };
            $rows[] = $prow($r, $m === 'clicked' && $detail === 'Looked at: ' ? 'Clicked' : $detail);
        }
        $cols = $pcols;
    } elseif (in_array($m, ['emailed', 'delivered', 'interested'], true)) {
        $types = $m === 'interested' ? "('replied','demo','signed_up')" : "('sent')";
        $list = rows("SELECT p.*, MAX(e.at) AS at, MAX(e.step) AS step FROM prospect_events e JOIN prospects p ON p.id = e.prospect_id
                      WHERE e.type IN $types AND e.at >= ? AND e.at < ? $filter GROUP BY p.id ORDER BY at DESC LIMIT 2000", array_merge([$start, $end], $fp));
        foreach ($list as $r) {
            if ($m === 'delivered' && $r['status'] === 'bounced') {
                continue;
            }
            $rows[] = $prow($r, $m === 'interested' ? '' : 'Last: ' . $stepName($r['step']));
        }
        $cols = $pcols;
    } elseif ($m === 'visits') {
        $sq = $source !== '' ? ' AND source = ?' : '';
        foreach (rows("SELECT vid, MIN(at) AS at, COUNT(*) AS views, SUBSTRING_INDEX(GROUP_CONCAT(path ORDER BY at SEPARATOR ' → '), ' → ', 8) AS pages, MAX(source) AS source,
                       MAX(ref_host) AS ref, MAX(device) AS device FROM site_visits WHERE at >= ? AND at < ? $sq GROUP BY vid ORDER BY at DESC LIMIT 2000",
                       array_merge([$start, $end], $source !== '' ? [$source] : [])) as $r) {
            $rows[] = ['when' => $r['at'], 'source' => $r['source'], 'ref' => (string) $r['ref'], 'pages' => $r['pages'], 'views' => (int) $r['views'], 'device' => $r['device']];
        }
        $cols = ['when' => 'When', 'source' => 'Source', 'ref' => 'Came from site', 'pages' => 'Pages viewed', 'views' => 'Page views', 'device' => 'Device'];
    } elseif ($m === 'subscribers') {
        $sq = $source !== '' ? " AND COALESCE(source, 'direct') = ?" : '';
        foreach (rows("SELECT * FROM subscribers WHERE created_at >= ? AND created_at < ? $sq ORDER BY created_at DESC LIMIT 2000", array_merge([$start, $end], $source !== '' ? [$source] : [])) as $r) {
            $rows[] = ['when' => $r['created_at'], 'email' => $r['email'], 'type' => CHECKLISTS[$r['kind']]['title'] ?? $r['kind'], 'source' => $r['source'] ?: 'direct',
                'now' => $r['unsubscribed_at'] ? 'Unsubscribed' : ($r['signed_up_at'] ? 'Signed up' : ((int) $r['followup_sent'] ? 'Follow-up sent' : ((int) $r['welcome_sent'] ? 'Checklist sent' : 'Waiting to send')))];
        }
        $cols = ['when' => 'When', 'email' => 'Email', 'type' => 'Checklist', 'source' => 'Source', 'now' => 'Status now'];
    } elseif ($m === 'web_demos') {
        $sq = $source !== '' ? " AND COALESCE(source, 'direct') = ?" : '';
        foreach (rows("SELECT * FROM leads WHERE created_at >= ? AND created_at < ? $sq ORDER BY created_at DESC LIMIT 2000", array_merge([$start, $end], $source !== '' ? [$source] : [])) as $r) {
            $rows[] = ['when' => $r['created_at'], 'name' => $r['name'], 'org' => (string) $r['company'], 'email' => $r['email'], 'phone' => (string) $r['phone'], 'source' => $r['source'] ?: 'direct', 'now' => $r['status']];
        }
        $cols = ['when' => 'When', 'name' => 'Name', 'org' => 'Organization', 'email' => 'Email', 'phone' => 'Phone', 'source' => 'Source', 'now' => 'Status'];
    } else { // web_signups, paying
        $where = $m === 'paying'
            ? "status = 'active' AND (source = 'outreach' OR id IN (SELECT u.tenant_id FROM users u JOIN prospects p ON p.email = u.email WHERE u.role = 'owner'))"
            : 'created_at >= ? AND created_at < ?' . ($source !== '' ? " AND COALESCE(source, 'direct') = ?" : '');
        $par = $m === 'paying' ? [] : array_merge([$start, $end], $source !== '' ? [$source] : []);
        foreach (rows("SELECT * FROM tenants WHERE deleted_at IS NULL AND slug <> ? AND $where ORDER BY created_at DESC LIMIT 2000", array_merge([DEMO_SLUG], $par)) as $r) {
            $rows[] = ['when' => $r['created_at'], 'org' => $r['name'], 'type' => industry($r['industry'])['name'] ?? $r['industry'], 'plan' => $r['plan'], 'source' => $r['source'] ?: 'direct', 'now' => billing_state($r)];
        }
        $cols = ['when' => 'Signed up', 'org' => 'Organization', 'type' => 'Industry', 'plan' => 'Plan', 'source' => 'Source', 'now' => 'Status now'];
    }
    $local = fn($utc) => (new DateTime((string) $utc, new DateTimeZone('UTC')))->setTimezone(outreach_tz())->format('M j, Y g:i A');
    foreach ($rows as &$r) {
        $r['when'] = $local($r['when']);
    }
    unset($r);
    $title = SALES_LISTS[$m] . ($kind !== '' ? ' · ' . PROSPECT_KINDS[$kind] : '') . ($state !== '' ? ' · ' . ($state === '-' ? 'no state' : $state) : '') . ($source !== '' ? ' · source: ' . $source : '');
    if (input('export') === 'csv') {
        $out = [array_values($cols)];
        foreach ($rows as $r) {
            $out[] = array_map(fn($k) => $r[$k] ?? '', array_keys($cols));
        }
        send_download('clockinout-' . $m . '-' . $from . '-to-' . $to . '.csv', 'text/csv; charset=utf-8', csv_string($out));
    }
    view('platform/super_sales_list', compact('from', 'to', 'group', 'preset', 'm', 'title', 'cols', 'rows', 'kind', 'state', 'source'), 'platform');
}

/**
 * /hello?t=<prospect token>: the call-back page linked from the plain outreach email. Two fields (name, phone,
 * best time), prefilled from what we know. Saves a lead (source outreach), marks the prospect "Booked demo"
 * and alerts you; also offers the free trial straight away.
 */
function platform_hello(): void
{
    $token = (string) input('t');
    $p = preg_match('/^[a-f0-9]{32}$/', $token) ? row('SELECT * FROM prospects WHERE token = ?', [$token]) : null;
    $times = ['morning' => 'Morning (9–12)', 'midday' => 'Around noon', 'afternoon' => 'Afternoon (1–5)', 'any' => 'Any time'];
    $old = ['name' => (string) ($p['first_name'] ?? ''), 'phone' => (string) ($p['phone'] ?? ''), 'email' => (string) ($p['email'] ?? ''), 'company' => (string) ($p['org'] ?? ''), 'time' => 'any'];
    $errors = [];
    $sent = false;
    if (is_post()) {
        $d = [
            'name'    => mb_substr(trim((string) input('name')), 0, 120),
            'phone'   => mb_substr(preg_replace('/[^0-9+().\- ]/', '', (string) input('phone')), 0, 40),
            'email'   => strtolower(mb_substr(trim((string) input('email')), 0, 190)),
            'company' => mb_substr(trim((string) input('company')), 0, 160),
            'time'    => isset($times[(string) input('time')]) ? (string) input('time') : 'any',
        ];
        if ($d['name'] === '') {
            $errors['name'] = 'Please enter your name.';
        }
        if (strlen(preg_replace('/\D/', '', $d['phone'])) < 7) {
            $errors['phone'] = 'Please enter a phone number.';
        }
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please check the email address.';
        }
        if ($d['company'] === '') {
            $errors['company'] = 'Please enter your organization.';
        }
        if (!$errors && (int) val('SELECT COUNT(*) FROM leads WHERE ip = ? AND created_at > ?', [client_ip(), gmdate('Y-m-d H:i:s', time() - 3600)]) >= 5) {
            $errors['form'] = 'Too many requests from your network. Please try again later or email us.';
        }
        if (!$errors && (string) input('website') === '') { // hidden field: only bots fill it in
            $industry = $p ? (prospect_pitch((string) $p['kind'])['industry'] ?? '') : '';
            $lead = ['name' => $d['name'], 'email' => $d['email'] ?: ($p['email'] ?? ''), 'company' => $d['company'], 'phone' => $d['phone'],
                'industry' => $industry, 'employees' => '', 'message' => 'Call-back request · best time: ' . $times[$d['time']] . ($p ? ' · from outreach email ' . strtoupper((string) $p['variant']) : '')];
            $id = insert('leads', $lead + ['status' => 'new', 'ip' => client_ip(), 'source' => 'outreach', 'created_at' => gmdate('Y-m-d H:i:s')]);
            $ok = lead_notify($lead + ['source' => 'outreach (call-back)']);
            update('leads', ['emailed' => $ok ? 1 : 0], 'id = ?', [$id]);
            if ($p && in_array($p['status'], ['new', 'active', 'done', 'replied'], true)) {
                update('prospects', ['status' => 'demo', 'next_at' => null], 'id = ?', [$p['id']]);
                prospect_event((int) $p['id'], 'demo');
            }
            if ($lead['email'] !== '') {
                prospect_converted($lead['email'], 'demo');
            }
            $GLOBALS['audit_system'] = true;
            audit('Call-back request', $d['company'] . ' — ' . $d['name'], true, 'best time ' . $times[$d['time']]);
            $sent = true;
        }
        $old = $d;
    }
    view('platform/hello', ['old' => $old, 'errors' => $errors, 'sent' => $sent, 'times' => $times, 'p' => $p, 'token' => $p ? $token : '',
        'metaTitle' => 'Quick call | ' . cfg('app_name'), 'metaDesc' => 'Tell us when to call: a 10-minute look at ClockInOut with your own people.'], 'platform');
}
