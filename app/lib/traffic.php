<?php
// Website traffic for the sales dashboard, counted on our own server (works even when ad blockers stop Google Analytics):
//   * one row per page view of the public website's main pages (site_visits); no cookie is used for counting:
//     a visitor is a daily-changing hash of their network address and browser, so people cannot be followed across days;
//   * where the visit came from: ?src= / ?utm_source=, else the referring site (google, facebook, chatgpt…), else "direct";
//   * the first source a person arrived from is kept in a small first-party cookie (cio_src, 90 days), so a sign-up or
//     demo request days later is still credited to it (lead_source()). Outreach link clicks also leave cio_p (the
//     prospect's token), so the prospect is marked converted even if they sign up with another email address.
// Bots and link scanners are left out. Rows older than 400 days are deleted by the hourly job.

const TRAFFIC_PAGES = '#^/(signup|demo|hello|whats-new|status|for/[a-z-]+|linkedin|facebook|email|web|friend)?$#';

function traffic_is_bot(): bool
{
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|scan|preview|monitor|uptime|lighthouse|headless|python|curl|wget|java\/|go-http|php|axios|node-fetch|facebookexternalhit|embedly|whatsapp|telegram|discord|skype/', $ua);
}

/** A source tag: letters, numbers, dashes. */
function traffic_tag(?string $s): ?string
{
    $s = strtolower(substr(trim((string) $s), 0, 40));
    return preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $s) ? $s : null;
}

/** Where a visit came from, from the referring site. */
function traffic_referrer_source(string $host): ?string
{
    $self = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if (strtolower($host) === $self) {
        return null; // our own pages
    }
    $host = preg_replace('/^(www|m|l|lm|mobile)\./', '', strtolower($host));
    if ($host === '' || $host === strtolower((string) cfg('base_domain')) || str_ends_with($host, '.' . strtolower((string) cfg('base_domain')))) {
        return null; // our own pages
    }
    $map = ['google' => '/(^|\.)google\./', 'bing' => '/(^|\.)bing\.com$/', 'yahoo' => '/(^|\.)yahoo\./', 'duckduckgo' => '/duckduckgo\.com$/',
            'facebook' => '/(^|\.)(facebook\.com|fb\.com|fb\.me)$/', 'instagram' => '/instagram\.com$/', 'linkedin' => '/(linkedin\.com|lnkd\.in)$/',
            'x' => '/^(t\.co|x\.com|twitter\.com)$/', 'chatgpt' => '/(chatgpt\.com|openai\.com)$/', 'perplexity' => '/perplexity\.ai$/',
            'claude' => '/claude\.ai$/', 'youtube' => '/(youtube\.com|youtu\.be)$/', 'reddit' => '/reddit\.com$/', 'email' => '/(mail\.|outlook\.)/'];
    foreach ($map as $name => $re) {
        if (preg_match($re, $host)) {
            return $name;
        }
    }
    return traffic_tag($host);
}

/** First-touch source cookie (90 days), only set once. */
function traffic_remember_source(string $source): void
{
    if (headers_sent() || isset($_COOKIE['cio_src']) || $source === 'direct') {
        return;
    }
    setcookie('cio_src', $source, ['expires' => time() + 90 * 86400, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE['cio_src'] = $source;
}

/** Records a page view of the public website (GET, main pages, people only). */
function traffic_record(string $path): void
{
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !preg_match(TRAFFIC_PAGES, $path) || !empty($_SESSION['super']) || traffic_is_bot()) {
            return;
        }
        if (preg_match('#^/for/([a-z-]+)$#', $path, $m) && !isset(CAMPAIGNS[$m[1]])) {
            return; // not a real page
        }
        $ref = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
        $fromRef = $ref !== '' ? traffic_referrer_source($ref) : null;
        $share = SHARE_LINKS[ltrim($path, '/')] ?? null;
        $tagged = traffic_tag(input('src')) ?? traffic_tag(input('utm_source')) ?? $share;
        $source = $tagged ?? $fromRef ?? ($ref !== '' ? 'internal' : 'direct');
        if ($source !== 'internal') {
            traffic_remember_source($source);
        } else {
            $source = traffic_tag($_COOKIE['cio_src'] ?? null) ?? 'direct'; // browsing on: keep where they came from
        }
        $salt = (string) setting('traffic_salt', '');
        if ($salt === '') {
            $salt = bin2hex(random_bytes(16));
            setting_set('traffic_salt', $salt);
        }
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $vid = substr(hash('sha256', gmdate('Y-m-d') . $salt . client_ip() . $ua), 0, 16);
        $todayStart = gmdate('Y-m-d 00:00:00');
        $landing = val('SELECT 1 FROM site_visits WHERE vid = ? AND at >= ? LIMIT 1', [$vid, $todayStart]) ? 0 : 1;
        insert('site_visits', ['at' => gmdate('Y-m-d H:i:s'), 'vid' => $vid, 'path' => mb_substr($path === '' ? '/' : $path, 0, 120), 'source' => $source,
            'ref_host' => $ref !== '' ? mb_substr(strtolower($ref), 0, 80) : null, 'device' => preg_match('/mobi|android|iphone|ipad/i', $ua) ? 'mobile' : 'desktop',
            'landing' => $landing]);
    } catch (Throwable $e) {
        // counting must never break the website
    }
}

/** Hourly: keep 400 days. */
function traffic_purge(): array
{
    q('DELETE FROM site_visits WHERE at < ?', [gmdate('Y-m-d H:i:s', time() - 400 * 86400)]);
    return [];
}

/** When a sign-up or demo request comes from someone who clicked an outreach email (cio_p cookie), credit that prospect. */
function prospect_converted_cookie(string $status): void
{
    $token = (string) ($_COOKIE['cio_p'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return;
    }
    try {
        $p = row('SELECT * FROM prospects WHERE token = ?', [$token]);
        if ($p && !in_array($p['status'], ['signed_up', $status], true)) {
            update('prospects', ['status' => $status, 'next_at' => null], 'id = ?', [$p['id']]);
            prospect_event((int) $p['id'], $status);
        }
    } catch (Throwable $e) {
        // never break the sign-up
    }
}

/** Website numbers for a UTC range: visits, page views, by source with demo requests and sign-ups, top landing pages. */
function traffic_report(string $start, string $end): array
{
    $tot = row('SELECT COUNT(*) AS views, COUNT(DISTINCT vid) AS visits, COALESCE(SUM(device = \'mobile\'), 0) AS mobile FROM site_visits WHERE at >= ? AND at < ?', [$start, $end]);
    $bySource = [];
    foreach (rows('SELECT source, COUNT(DISTINCT vid) AS visits FROM site_visits WHERE at >= ? AND at < ? GROUP BY source', [$start, $end]) as $r) {
        $bySource[$r['source']] = ['visits' => (int) $r['visits'], 'demos' => 0, 'signups' => 0];
    }
    foreach (rows("SELECT COALESCE(source, 'direct') AS s, COUNT(*) AS n FROM leads WHERE created_at >= ? AND created_at < ? GROUP BY s", [$start, $end]) as $r) {
        $bySource[$r['s']]['demos'] = (int) $r['n'];
    }
    foreach (rows("SELECT COALESCE(source, 'direct') AS s, COUNT(*) AS n FROM tenants WHERE deleted_at IS NULL AND slug <> ? AND created_at >= ? AND created_at < ? GROUP BY s", [DEMO_SLUG, $start, $end]) as $r) {
        $bySource[$r['s']]['signups'] = (int) $r['n'];
    }
    foreach ($bySource as &$v) {
        $v += ['visits' => 0, 'demos' => 0, 'signups' => 0];
    }
    unset($v);
    uasort($bySource, fn($a, $b) => [$b['visits'], $b['signups']] <=> [$a['visits'], $a['signups']]);
    $pages = rows('SELECT path, COUNT(*) AS n FROM site_visits WHERE at >= ? AND at < ? AND landing = 1 GROUP BY path ORDER BY n DESC LIMIT 10', [$start, $end]);
    $demos = array_sum(array_column($bySource, 'demos'));
    $signups = array_sum(array_column($bySource, 'signups'));
    return ['views' => (int) $tot['views'], 'visits' => (int) $tot['visits'], 'mobile' => (int) $tot['mobile'], 'by_source' => $bySource, 'pages' => $pages,
            'demos' => $demos, 'signups' => $signups, 'conv' => $tot['visits'] ? ($demos + $signups) / (int) $tot['visits'] : null];
}
