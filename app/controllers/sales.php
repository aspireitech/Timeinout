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
    $to = in_array(input('to'), ['signup', 'demo', 'home'], true) ? (string) input('to') : 'home';
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
