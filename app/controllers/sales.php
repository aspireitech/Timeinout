<?php
// Provider console → Sales dashboard, and the tracked links / optional open pixel in outreach emails.

function super_sales(): void
{
    require_super();
    [$from, $to, $group, $preset] = sales_range();
    $d = sales_dashboard($from, $to, $group);
    view('platform/super_sales', compact('from', 'to', 'group', 'preset', 'd'), 'platform');
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
            prospect_event((int) $p['id'], 'clicked', $step);
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
