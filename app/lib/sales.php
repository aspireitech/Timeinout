<?php
// Sales dashboard (/super/sales): the outreach funnel over any date range, by day, week or month.
//   found → emailed → delivered → (opened) → clicked → replied → demo → signed up → paying
// Every step is a dated row in prospect_events, written where it happens (finder, hourly send, inbox check,
// tracked links, unsubscribe, demo and sign-up forms, manual status changes in /super → Prospects).

/** Event types that count once per prospect. */
const SALES_ONCE = ['found', 'replied', 'demo', 'signed_up', 'unsubscribed', 'bounced'];

/** Records one funnel event. Never breaks the caller. */
function prospect_event(int $prospectId, string $type, ?int $step = null, ?string $detail = null): void
{
    try {
        if (in_array($type, SALES_ONCE, true) && val('SELECT 1 FROM prospect_events WHERE prospect_id = ? AND type = ? LIMIT 1', [$prospectId, $type])) {
            return;
        }
        $row = ['prospect_id' => $prospectId, 'type' => $type, 'step' => $step, 'at' => gmdate('Y-m-d H:i:s')];
        if ($detail !== null) {
            $row['detail'] = mb_substr($detail, 0, 20);
        }
        insert('prospect_events', $row);
    } catch (Throwable $e) {
        // the events table may not exist yet during an update
    }
}

/** A tracked link in an outreach email: /r?t=<token>&s=<step>&to=<signup|demo|home>. */
function prospect_link(array $p, int $step, string $to): string
{
    return scheme() . '://' . cfg('base_domain') . base_path() . '/r?t=' . rawurlencode((string) $p['token']) . '&s=' . ($step + 1) . '&to=' . $to;
}

/** Where a tracked link goes (fixed list: never an open redirect). */
function prospect_link_target(string $to, ?array $p): string
{
    $site = base_path();
    $industry = $p ? (prospect_pitch((string) $p['kind'])['industry'] ?? '') : '';
    return match ($to) {
        'signup' => $site . '/signup?src=outreach' . ($industry !== '' ? '&industry=' . rawurlencode($industry) : ''),
        'demo'   => $site . '/demo?src=outreach',
        default  => $site . (($c = sales_campaign_for($p['kind'] ?? '')) ? '/for/' . $c : '/') . '?src=outreach', // the page for their kind of business
    };
}

/** The landing page (/for/<key>) for a kind of prospect, or null. */
function sales_campaign_for(string $kind): ?string
{
    $map = ['school' => 'schools', 'daycare' => 'schools', 'tutoring' => 'tutoring', 'office' => 'offices', 'clinic' => 'healthcare',
            'dental' => 'dental', 'gym' => 'gyms', 'construction' => 'construction', 'retail' => 'retail', 'restaurant' => 'retail'];
    $c = $map[$kind] ?? null;
    return $c !== null && isset(CAMPAIGNS[$c]) ? $c : null;
}

/** Link scanners in company mail systems open every link right after delivery; those are not people. */
function sales_is_bot(?string $sentAt): bool
{
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '' || preg_match('/bot|crawl|spider|scan|preview|proofpoint|mimecast|barracuda|safelinks|python|curl|wget|java\/|go-http|headless/', $ua)) {
        return true;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        return true;
    }
    return $sentAt !== null && time() - (strtotime($sentAt . ' UTC') ?: 0) < 90; // clicked within 90 s of sending
}

// ---------- Date range ----------

/** [from Y-m-d, to Y-m-d, group day|week|month, preset] from the query string, in the outreach time zone. */
function sales_range(): array
{
    $tz = outreach_tz();
    $today = new DateTimeImmutable('today', $tz);
    $preset = (string) input('range', '30d');
    $valid = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && DateTimeImmutable::createFromFormat('!Y-m-d', $d, $tz);
    [$from, $to] = match ($preset) {
        '7d'         => [$today->modify('-6 days'), $today],
        '90d'        => [$today->modify('-89 days'), $today],
        'month'      => [$today->modify('first day of this month'), $today],
        'last_month' => [$today->modify('first day of last month'), $today->modify('last day of last month')],
        'year'       => [$today->modify('first day of january this year'), $today],
        'custom'     => [$valid(input('from')) ? new DateTimeImmutable((string) input('from'), $tz) : $today->modify('-29 days'),
                         $valid(input('to')) ? new DateTimeImmutable((string) input('to'), $tz) : $today],
        default      => [$today->modify('-29 days'), $today],
    };
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    if ($from < $to->modify('-3 years')) {
        $from = $to->modify('-3 years');
    }
    $days = (int) $from->diff($to)->days + 1;
    $group = (string) input('group', '');
    if (!in_array($group, ['day', 'week', 'month'], true)) {
        $group = $days <= 31 ? 'day' : ($days <= 120 ? 'week' : 'month');
    }
    return [$from->format('Y-m-d'), $to->format('Y-m-d'), $group, isset(['7d' => 1, '30d' => 1, '90d' => 1, 'month' => 1, 'last_month' => 1, 'year' => 1, 'custom' => 1][$preset]) ? $preset : '30d'];
}

/** UTC bounds [start, end) of a local date range. */
function sales_utc_bounds(string $from, string $to): array
{
    $tz = outreach_tz();
    $utc = new DateTimeZone('UTC');
    return [(new DateTime($from, $tz))->setTimezone($utc)->format('Y-m-d H:i:s'),
            (new DateTime($to, $tz))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')];
}

/** The period bucket (key, label) of a UTC time. */
function sales_bucket(string $utc, string $group): array
{
    $d = (new DateTime($utc, new DateTimeZone('UTC')))->setTimezone(outreach_tz());
    return match ($group) {
        'month' => [$d->format('Y-m'), $d->format('M Y')],
        'week'  => [($w = (clone $d)->modify('monday this week'))->format('Y-m-d'), 'Week of ' . $w->format('M j')],
        default => [$d->format('Y-m-d'), $d->format('M j')],
    };
}

/** Every bucket in the range, in order: key => label. */
function sales_buckets(string $from, string $to, string $group): array
{
    $tz = outreach_tz();
    $d = new DateTime($from, $tz);
    $end = new DateTime($to, $tz);
    if ($group === 'week') {
        $d->modify('monday this week');
    } elseif ($group === 'month') {
        $d->modify('first day of this month');
    }
    $out = [];
    while ($d <= $end && count($out) < 400) {
        $out[$group === 'month' ? $d->format('Y-m') : $d->format('Y-m-d')] = match ($group) {
            'month' => $d->format('M Y'), 'week' => 'Week of ' . $d->format('M j'), default => $d->format('M j'),
        };
        $d->modify($group === 'month' ? '+1 month' : ($group === 'week' ? '+1 week' : '+1 day'));
    }
    return $out;
}

// ---------- Numbers ----------

/** Funnel numbers for a UTC range. Rates are out of the prospects emailed in the range. */
function sales_totals(string $start, string $end): array
{
    $ev = rows('SELECT type, COUNT(*) AS n, COUNT(DISTINCT prospect_id) AS people FROM prospect_events WHERE at >= ? AND at < ? GROUP BY type', [$start, $end]);
    $n = $people = [];
    foreach ($ev as $r) {
        $n[$r['type']] = (int) $r['n'];
        $people[$r['type']] = (int) $r['people'];
    }
    $first = (int) val("SELECT COUNT(*) FROM prospect_events WHERE type = 'sent' AND step = 1 AND at >= ? AND at < ?", [$start, $end]);
    $interested = (int) val("SELECT COUNT(DISTINCT prospect_id) FROM prospect_events WHERE type IN ('replied','demo','signed_up') AND at >= ? AND at < ?", [$start, $end]);
    $emailed = $people['sent'] ?? 0;
    $rate = fn($x) => $emailed ? $x / $emailed : null;
    return [
        'found' => $n['found'] ?? 0, 'emails' => $n['sent'] ?? 0, 'emailed' => $emailed, 'first' => $first,
        'bounced' => $people['bounced'] ?? 0, 'delivered' => max(0, $emailed - ($people['bounced'] ?? 0)),
        'opened' => $people['opened'] ?? 0, 'clicked' => $people['clicked'] ?? 0, 'replied' => $people['replied'] ?? 0,
        'demo' => $people['demo'] ?? 0, 'signed_up' => $people['signed_up'] ?? 0, 'unsubscribed' => $people['unsubscribed'] ?? 0,
        'interested' => $interested,
        'delivery_rate' => $rate(max(0, $emailed - ($people['bounced'] ?? 0))), 'open_rate' => $rate($people['opened'] ?? 0),
        'click_rate' => $rate($people['clicked'] ?? 0), 'reply_rate' => $rate($people['replied'] ?? 0),
        'interest_rate' => $rate($interested), 'signup_rate' => $rate($people['signed_up'] ?? 0),
    ];
}

/** Everything the dashboard shows. */
function sales_dashboard(string $from, string $to, string $group): array
{
    [$start, $end] = sales_utc_bounds($from, $to);
    $days = (int) (new DateTime($from))->diff(new DateTime($to))->days + 1;
    [$pStart, $pEnd] = sales_utc_bounds((new DateTime($from))->modify("-$days days")->format('Y-m-d'), (new DateTime($from))->modify('-1 day')->format('Y-m-d'));

    // Over time: one series per measure, each its own small chart (different scales never share an axis)
    $buckets = sales_buckets($from, $to, $group);
    $series = [];
    foreach (['found', 'sent', 'clicked', 'replied', 'won'] as $k) {
        $series[$k] = array_fill_keys(array_keys($buckets), 0);
    }
    $seen = [];
    foreach (rows("SELECT prospect_id, type, at FROM prospect_events WHERE at >= ? AND at < ? AND type IN ('found','sent','clicked','replied','demo','signed_up') ORDER BY at", [$start, $end]) as $r) {
        [$key] = sales_bucket($r['at'], $group);
        $k = in_array($r['type'], ['demo', 'signed_up'], true) ? 'won' : $r['type'];
        if (!isset($series[$k][$key])) {
            continue;
        }
        if ($k === 'clicked' || $k === 'won') { // people, not clicks
            if (isset($seen[$k][$key][$r['prospect_id']])) {
                continue;
            }
            $seen[$k][$key][$r['prospect_id']] = true;
        }
        $series[$k][$key]++;
    }

    // By kind of business and by state: who answers?
    $by = function (string $col) use ($start, $end): array {
        $out = [];
        foreach (rows("SELECT p.$col AS g, e.type, COUNT(DISTINCT e.prospect_id) AS n FROM prospect_events e JOIN prospects p ON p.id = e.prospect_id
                       WHERE e.at >= ? AND e.at < ? AND e.type IN ('sent','clicked','replied','demo','signed_up','bounced') GROUP BY p.$col, e.type", [$start, $end]) as $r) {
            $out[(string) $r['g']][$r['type']] = (int) $r['n'];
        }
        foreach (rows("SELECT p.$col AS g, COUNT(DISTINCT e.prospect_id) AS n FROM prospect_events e JOIN prospects p ON p.id = e.prospect_id
                       WHERE e.at >= ? AND e.at < ? AND e.type IN ('replied','demo','signed_up') GROUP BY p.$col", [$start, $end]) as $r) {
            $out[(string) $r['g']]['interested'] = (int) $r['n'];
        }
        $out = array_filter($out, fn($r) => ($r['sent'] ?? 0) > 0);
        uasort($out, fn($a, $b) => ($b['sent'] ?? 0) <=> ($a['sent'] ?? 0));
        return $out;
    };

    // Where every prospect stands today (not limited to the range)
    $st = array_map('intval', array_column(rows('SELECT status, COUNT(*) AS n FROM prospects GROUP BY status'), 'n', 'status'));
    $mix = [
        'Waiting to be emailed' => $st['new'] ?? 0,
        'In the email sequence' => $st['active'] ?? 0,
        'No answer yet' => $st['done'] ?? 0,
        'Interested (replied, demo, signed up)' => ($st['replied'] ?? 0) + ($st['demo'] ?? 0) + ($st['signed_up'] ?? 0),
        'Stopped (unsubscribed, bounced, removed)' => ($st['unsubscribed'] ?? 0) + ($st['bounced'] ?? 0) + ($st['removed'] ?? 0),
    ];

    // All sources, for context: demo requests and sign-ups from the website, and paying customers that came from outreach
    $leads = row("SELECT COUNT(*) AS all_n, COALESCE(SUM(source = 'outreach'), 0) AS outreach FROM leads WHERE created_at >= ? AND created_at < ?", [$start, $end]);
    $signups = row("SELECT COUNT(*) AS all_n, COALESCE(SUM(source = 'outreach'), 0) AS outreach FROM tenants WHERE deleted_at IS NULL AND slug <> ? AND created_at >= ? AND created_at < ?", [DEMO_SLUG, $start, $end]);
    $paying = (int) val("SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL AND status = 'active' AND (source = 'outreach'
        OR id IN (SELECT u.tenant_id FROM users u JOIN prospects p ON p.email = u.email WHERE u.role = 'owner'))");

    return [
        'now' => sales_totals($start, $end), 'before' => sales_totals($pStart, $pEnd), 'days' => $days,
        'buckets' => $buckets, 'series' => $series, 'kinds' => $by('kind'), 'states' => $by('state'), 'mix' => $mix,
        'leads' => $leads, 'signups' => $signups, 'paying' => $paying,
        'estimated' => (string) val("SELECT MIN(at) FROM prospect_events WHERE type = 'sent'") < (string) setting('sales_events_since', '9999'),
    ];
}

// ---------- Drawing (server-side SVG: no chart library, works under the strict content policy) ----------

/** A column chart for one series. $values: key => n, $labels: key => label. */
function sales_columns(array $values, array $labels, string $name, string $color = 'var(--s1)'): string
{
    $W = 560; $H = 170; $L = 34; $R = 8; $T = 10; $B = 26;
    $n = max(1, count($values));
    $max = max(1, ...array_values($values ?: [0]));
    $nice = (int) (10 ** floor(log10($max)));
    $top = (int) (ceil($max / $nice) * $nice);
    $top = $top < 4 ? 4 : $top;
    $plotW = $W - $L - $R; $plotH = $H - $T - $B;
    $slot = $plotW / $n;
    $bw = max(2, min(36, $slot - 2));
    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="viz" role="img" aria-label="' . e($name) . ' by period">';
    foreach ([0, 0.5, 1] as $f) { // recessive grid, 3 ticks
        $y = round($T + $plotH * (1 - $f), 1);
        $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $y . '" y2="' . $y . '" class="' . ($f ? 'grid' : 'base') . '"/>'
            . '<text x="' . ($L - 6) . '" y="' . ($y + 4) . '" class="tick" text-anchor="end">' . (int) round($top * $f) . '</text>';
    }
    $i = 0;
    $every = (int) ceil($n / 8); // at most ~8 x labels
    foreach ($values as $k => $v) {
        $x = $L + $slot * $i + ($slot - $bw) / 2;
        $h = $v > 0 ? max(2, $plotH * $v / $top) : 0;
        $y = $T + $plotH - $h;
        $r = min(4, $bw / 2, $h);
        if ($h > 0) { // rounded data end, square at the baseline
            $svg .= '<path fill="' . $color . '" d="M' . round($x, 1) . ',' . round($T + $plotH, 1) . 'V' . round($y + $r, 1) . 'q0,-' . $r . ' ' . $r . ',-' . $r
                . 'h' . round($bw - 2 * $r, 1) . 'q' . $r . ',0 ' . $r . ',' . $r . 'V' . round($T + $plotH, 1) . 'z"/>';
        }
        // hit target: the whole column slot, taller than the mark
        $svg .= '<rect class="hit" x="' . round($L + $slot * $i, 1) . '" y="' . $T . '" width="' . round($slot, 1) . '" height="' . $plotH . '" data-tip="' . e(($labels[$k] ?? $k) . ': ' . number_format($v) . ' ' . strtolower($name)) . '"/>';
        if ($i % $every === 0) {
            $svg .= '<text x="' . round($L + $slot * $i + $slot / 2, 1) . '" y="' . ($H - 8) . '" class="tick" text-anchor="middle">' . e(preg_replace('/^Week of /', '', $labels[$k] ?? $k)) . '</text>';
        }
        $i++;
    }
    return $svg . '</svg>';
}

/** "12.5%" or "–". */
function sales_pct(?float $r, int $dec = 1): string
{
    return $r === null ? '–' : number_format($r * 100, $dec) . '%';
}

/** Change against the previous period, as [text, css class]. */
function sales_delta(float|int|null $now, float|int|null $before, bool $rate = false): array
{
    if ($now === null || $before === null) {
        return ['', ''];
    }
    $d = $now - $before;
    if (abs($d) < ($rate ? 0.0005 : 0.5)) {
        return ['same as previous period', 'flat'];
    }
    $txt = ($d > 0 ? '▲ ' : '▼ ') . ($rate ? number_format(abs($d) * 100, 1) . ' pts' : number_format(abs($d))) . ' vs previous period';
    return [$txt, $d > 0 ? 'up' : 'down'];
}

/** A ring meter for one rate (e.g. reply rate): track + arc, the figure in the middle. */
function sales_ring(?float $rate, string $label, string $sub, string $color = 'var(--s1)'): string
{
    $r = 42; $c = 2 * M_PI * $r;
    $v = max(0, min(1, (float) $rate));
    $arc = $rate === null || $v <= 0 ? '' : '<circle cx="50" cy="50" r="' . $r . '" fill="none" stroke="' . $color . '" stroke-width="10" stroke-linecap="round"'
        . ' stroke-dasharray="' . round(max(0.5, $c * $v), 2) . ' ' . round($c, 2) . '" transform="rotate(-90 50 50)"/>';
    return '<div class="ring"><svg viewBox="0 0 100 100" role="img" aria-label="' . e($label . ' ' . sales_pct($rate)) . '">'
        . '<circle cx="50" cy="50" r="' . $r . '" fill="none" stroke="var(--track)" stroke-width="10"/>' . $arc
        . '<text x="50" y="55" text-anchor="middle" class="ring-v">' . e(sales_pct($rate, $rate !== null && $rate < 0.1 ? 1 : 0)) . '</text></svg>'
        . '<div class="ring-l">' . e($label) . '</div><div class="ring-s">' . e($sub) . '</div></div>';
}

// ---------- Hot list: people to call today ----------

/**
 * Prospects who clicked a link in an outreach email in the last $days days and have not replied, booked a demo
 * or signed up: they looked, so a short phone call now converts best. Newest and most interested first.
 */
function sales_hot_leads(int $days = 7, int $limit = 50): array
{
    $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
    $list = rows("SELECT p.*, MAX(e.at) AS last_click, COUNT(*) AS clicks,
                         SUM(e.detail = 'signup') AS signup_clicks, SUM(e.detail = 'demo') AS demo_clicks
                  FROM prospect_events e JOIN prospects p ON p.id = e.prospect_id
                  WHERE e.type = 'clicked' AND e.at >= ? AND p.status IN ('new','active','done')
                  GROUP BY p.id ORDER BY (SUM(e.detail = 'signup') > 0 OR SUM(e.detail = 'demo') > 0) DESC, MAX(e.at) DESC LIMIT " . (int) $limit, [$since]);
    foreach ($list as &$p) {
        $p['looked_at'] = $p['demo_clicks'] ? 'the demo booking page' : ($p['signup_clicks'] ? 'the free-trial sign-up page' : 'your website');
    }
    unset($p);
    return $list;
}

/**
 * Weekday morning email (from 8:00 in outreach_tz, once a day): the hot list and yesterday's numbers.
 * Sent only when there is someone to call or something happened.
 */
function run_hot_leads(bool $force = false): array
{
    $now = new DateTime('now', outreach_tz());
    if (setting('hotlist_on', '1') !== '1' || (!$force && ((int) $now->format('N') > 5 || (int) $now->format('G') < 8))) {
        return [];
    }
    $day = $now->format('Y-m-d');
    if (!$force && setting('hotlist_last', '') === $day) {
        return [];
    }
    setting_set('hotlist_last', $day);
    $hot = sales_hot_leads(7, 30);
    $y = (clone $now)->modify('-1 day')->format('Y-m-d');
    [$ys, $ye] = sales_utc_bounds($y, $y);
    $t = sales_totals($ys, $ye);
    $w = traffic_report($ys, $ye);
    if (!$hot && !$t['emails'] && !$w['visits'] && !$t['replied']) {
        return [];
    }
    $cell = 'style="padding:8px 10px;border-bottom:1px solid #E2E4EF;vertical-align:top"';
    $big = fn($l, $v) => '<td style="padding:10px 14px;background:#F4F5FA;border-radius:10px"><div style="color:#6b7090;font-size:12px">' . $l . '</div><div style="font-size:20px;font-weight:bold">' . $v . '</div></td>';
    $html = '<h2 style="margin:0 0 4px">Your sales morning: ' . e($now->format('l, M j')) . '</h2>'
        . '<p style="color:#6b7090;margin:0 0 12px">Yesterday</p><table style="border-spacing:8px"><tr>'
        . $big('Website visits', number_format($w['visits'])) . $big('Demo requests', number_format($w['demos'])) . $big('Sign-ups', number_format($w['signups']))
        . $big('Outreach emails', number_format($t['emails'])) . $big('Replies', number_format($t['replied'])) . '</tr></table>';
    if ($hot) {
        $html .= '<h3 style="margin:18px 0 6px">Call these ' . count($hot) . ' today</h3><p style="margin:0 0 8px">They clicked your email in the last 7 days but have not replied. A two-minute call now ("I saw you had a look, any questions?") wins more customers than another email.</p>'
            . '<table style="border-collapse:collapse;font-size:14px;width:100%"><tr><th align="left" ' . $cell . '>Who</th><th align="left" ' . $cell . '>Call / email</th><th align="left" ' . $cell . '>Looked at</th></tr>';
        foreach ($hot as $p) {
            $when = (new DateTime($p['last_click'], new DateTimeZone('UTC')))->setTimezone(outreach_tz())->format('D g:i A');
            $html .= '<tr><td ' . $cell . '><b>' . e($p['org']) . '</b><br><span style="color:#6b7090">' . e(PROSPECT_KINDS[$p['kind']] ?? $p['kind']) . ($p['city'] ? ' · ' . e($p['city']) . ', ' . e((string) $p['state']) : '') . '</span></td>'
                . '<td ' . $cell . '>' . ($p['phone'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $p['phone'])) . '">' . e($p['phone']) . '</a><br>' : '') . '<a href="mailto:' . e($p['email']) . '">' . e($p['email']) . '</a>'
                . ($p['first_name'] ? '<br>Ask for ' . e($p['first_name']) : '') . '</td>'
                . '<td ' . $cell . '>' . e($p['looked_at']) . '<br><span style="color:#6b7090">' . e($when) . ((int) $p['clicks'] > 1 ? ' · ' . (int) $p['clicks'] . ' clicks' : '') . '</span></td></tr>';
        }
        $html .= '</table>';
    }
    $html .= '<p style="margin-top:16px"><a href="https://' . e(cfg('base_domain') . base_path()) . '/super/sales">Open the sales dashboard</a> · after a call, set the prospect to Replied or Booked demo in Prospects.</p>';
    $ok = provider_alert(($hot ? count($hot) . ' hot lead' . (count($hot) === 1 ? '' : 's') . ' to call today' : 'Your sales morning') . ' · ' . $now->format('M j'), $html);
    return ['hot list: ' . count($hot) . ' to call, email ' . ($ok ? 'sent' : 'failed: ' . ($GLOBALS['mail_error'] ?? ''))];
}
