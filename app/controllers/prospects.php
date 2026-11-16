<?php
// Provider console → Prospects, the unsubscribe page, and the API the daily prospect finder posts to.

function super_prospects(): void
{
    require_super();
    $status = (string) input('status');
    $where = isset(PROSPECT_STATUSES[$status]) ? 'WHERE status = ?' : '';
    $list = rows("SELECT * FROM prospects $where ORDER BY created_at DESC, id DESC LIMIT 1000", $where ? [$status] : []);
    if (input('export') === 'csv') {
        $out = [['Added (UTC)', 'Organization', 'First name', 'Email', 'Phone', 'Website', 'City', 'State', 'Type', 'Status', 'Emails sent', 'Last sent (UTC)', 'Next email (UTC)', 'Found at', 'Notes']];
        foreach ($list as $p) {
            $out[] = [$p['created_at'], $p['org'], $p['first_name'], $p['email'], $p['phone'], $p['website'], $p['city'], $p['state'], PROSPECT_KINDS[$p['kind']] ?? $p['kind'],
                PROSPECT_STATUSES[$p['status']] ?? $p['status'], $p['step'], $p['last_sent_at'], $p['next_at'], $p['found_at'], $p['notes']];
        }
        send_download('prospects.csv', 'text/csv; charset=utf-8', csv_string($out));
    }
    $counts = array_column(rows('SELECT status, COUNT(*) AS n FROM prospects GROUP BY status'), 'n', 'status');
    $newKey = $_SESSION['prospect_key_once'] ?? null; // shown once, right after it is created
    unset($_SESSION['prospect_key_once']);
    // Delivery evidence: the last email to each prospect, and the latest emails overall
    $lastSend = [];
    foreach (rows('SELECT s.* FROM prospect_sends s JOIN (SELECT prospect_id, MAX(id) AS id FROM prospect_sends GROUP BY prospect_id) x ON x.id = s.id') as $s) {
        $lastSend[(int) $s['prospect_id']] = $s;
    }
    $sends = rows('SELECT s.*, p.org FROM prospect_sends s LEFT JOIN prospects p ON p.id = s.prospect_id ORDER BY s.id DESC LIMIT 100');
    $dayStart = (new DateTime('today', outreach_tz()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $today = row("SELECT COUNT(*) AS sent, SUM(in_sent) AS in_sent, SUM(outcome = 'bounced') AS bounced, SUM(outcome = 'replied') AS replied FROM prospect_sends WHERE sent_at >= ?", [$dayStart]);
    $totals = row("SELECT COUNT(*) AS sent, SUM(outcome = 'bounced') AS bounced, SUM(outcome = 'replied') AS replied FROM prospect_sends");
    view('platform/super_prospects', compact('list', 'status', 'counts', 'newKey', 'lastSend', 'sends', 'today', 'totals'), 'platform');
}

/** Paste box: one prospect per line, comma or tab separated. */
function super_prospects_add(): void
{
    require_super();
    $cols = ['org', 'first_name', 'email', 'city', 'state', 'kind', 'website', 'phone'];
    $list = [];
    foreach (preg_split('/\R/', (string) input('lines')) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $cells = str_getcsv($line, str_contains($line, "\t") ? "\t" : ',');
        if (strtolower(trim($cells[0] ?? '')) === 'organization') {
            continue; // header row
        }
        $list[] = array_combine($cols, array_pad(array_slice(array_map('trim', $cells), 0, count($cols)), count($cols), ''));
    }
    $r = prospects_add($list, 'manual');
    audit_note(null, $r['added'] . ' added, ' . count($r['skipped']) . ' skipped');
    flash($r['added'] ? 'success' : 'error', $r['added'] . ' prospect(s) added.'
        . ($r['skipped'] ? ' Skipped: ' . implode('; ', array_map(fn($s) => $s[0] . ' (' . $s[1] . ')', array_slice($r['skipped'], 0, 10))) . (count($r['skipped']) > 10 ? '…' : '') : ''));
    redirect(base_path() . '/super/prospects');
}

function super_prospects_settings(): void
{
    require_super();
    setting_set('prospects_on', input('prospects_on') === '1' ? '1' : '0');
    setting_set('prospects_daily', (string) max(1, min(100, (int) input('prospects_daily') ?: 25)));
    $imap = strtolower(trim((string) input('imap_host')));
    setting_set('imap_host', preg_match('/^[a-z0-9.-]{3,190}$/', $imap) ? $imap : '');
    setting_set('outreach_save_sent', input('outreach_save_sent') === '1' ? '1' : '0');
    setting_set('outreach_track_opens', input('outreach_track_opens') === '1' ? '1' : '0');
    setting_set('imap_sent_folder', ''); // found again on the next send
    setting_set('outreach_name', mb_substr(trim((string) input('outreach_name')), 0, 80));
    setting_set('outreach_phone', mb_substr(trim((string) input('outreach_phone')), 0, 40));
    setting_set('outreach_address', mb_substr(trim((string) input('outreach_address')), 0, 300));
    $tz = (string) input('outreach_tz');
    setting_set('outreach_tz', valid_tz($tz) ? $tz : 'America/Denver');
    if (setting('prospects_on') === '1' && ($p = outreach_problem())) {
        setting_set('prospects_on', '0');
        flash('error', 'Saved, but sending stays off: ' . $p);
    } else {
        flash('success', 'Outreach settings saved.');
    }
    redirect(base_path() . '/super/prospects');
}

/** New key for the prospect finder. Only its hash is stored; the key is shown once. */
function super_prospects_key(): void
{
    require_super();
    $key = 'pk_' . bin2hex(random_bytes(24));
    setting_set('prospect_api_key_hash', hash('sha256', $key));
    $_SESSION['prospect_key_once'] = $key;
    redirect(base_path() . '/super/prospects#finder');
}

function super_prospect_preview(int $id): void
{
    require_super();
    $p = row('SELECT * FROM prospects WHERE id = ?', [$id]) ?? not_found();
    $step = max(0, min(2, (int) input('step')));
    [$subject, $html] = prospect_email($p, $step);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>' . e($subject) . '</title><body style="margin:0;background:#F4F5FA">'
        . '<div style="font-family:Arial,sans-serif;max-width:600px;margin:16px auto 0;padding:0 12px"><b>To:</b> ' . e($p['email']) . '<br><b>Subject:</b> ' . e($subject)
        . '<br>' . implode(' · ', array_map(fn($i, $n) => $i === $step ? "<b>$n</b>" : '<a href="?step=' . $i . '">' . $n . '</a>', [0, 1, 2], ['Email 1', 'Follow-up (day 4)', 'Last note (day 9)'])) . '</div>'
        . $html . '</body>';
    exit;
}

function super_prospect_update(int $id): void
{
    require_super();
    $p = row('SELECT * FROM prospects WHERE id = ?', [$id]);
    if ($p) {
        $status = (string) input('status');
        $new = ['status' => isset(PROSPECT_STATUSES[$status]) ? $status : $p['status'], 'notes' => mb_substr(trim((string) input('notes')), 0, 3000) ?: null];
        if ($new['status'] !== $p['status']) {
            if (in_array($new['status'], PROSPECT_STOPPED, true)) {
                $new['next_at'] = null;
            } elseif ($new['status'] === 'new') {
                $new['step'] = 0; // start the emails again from the first one
                $new['next_at'] = null;
            } elseif ($new['status'] === 'active') {
                $new['next_at'] = gmdate('Y-m-d H:i:s'); // continue with the next email
            }
        }
        audit_note($p['org'] . ' — ' . $p['email'], audit_diff($p, $new));
        update('prospects', $new, 'id = ?', [$id]);
        if ($new['status'] !== $p['status'] && in_array($new['status'], SALES_ONCE, true)) {
            prospect_event($id, $new['status']); // e.g. you marked a phone reply: it counts on the dashboard
        }
        flash('success', 'Prospect updated.');
    }
    redirect(base_path() . '/super/prospects' . (input('back') !== '' ? '?status=' . urlencode((string) input('back')) : ''));
}

/** Link in every outreach email. Opening it only shows a button, so email scanners do not unsubscribe people. */
function platform_unsubscribe(): void
{
    $token = (string) input('t');
    $p = preg_match('/^[a-f0-9]{32}$/', $token) ? row('SELECT * FROM prospects WHERE token = ?', [$token]) : null;
    $done = false;
    if ($p && is_post()) {
        update('prospects', ['status' => 'unsubscribed', 'next_at' => null], 'id = ?', [$p['id']]);
        prospect_event((int) $p['id'], 'unsubscribed');
        $GLOBALS['audit_system'] = true;
        audit('Prospect unsubscribed', $p['org'] . ' — ' . $p['email'], true);
        $done = true;
    }
    view('platform/unsubscribe', ['p' => $p, 'done' => $done || ($p && $p['status'] === 'unsubscribed'), 'token' => $token, 'metaTitle' => 'Unsubscribe | ' . cfg('app_name')], 'platform');
}

/**
 * One-click unsubscribe (RFC 8058) from the List-Unsubscribe header: the mail app POSTs here, no page or form.
 * The secret token in the link is the proof (that is why it is a webhook_ route without a form token). GET shows the normal page.
 */
function webhook_unsubscribe_one_click(): void
{
    $token = (string) input('t');
    if (!is_post()) {
        redirect(base_path() . '/unsubscribe?t=' . (preg_match('/^[a-f0-9]{32}$/', $token) ? $token : ''));
    }
    $p = preg_match('/^[a-f0-9]{32}$/', $token) ? row('SELECT * FROM prospects WHERE token = ?', [$token]) : null;
    if ($p && $p['status'] !== 'unsubscribed') {
        update('prospects', ['status' => 'unsubscribed', 'next_at' => null], 'id = ?', [$p['id']]);
        prospect_event((int) $p['id'], 'unsubscribed');
        $GLOBALS['audit_system'] = true;
        audit('Prospect unsubscribed (one-click)', $p['org'] . ' — ' . $p['email'], true);
    }
    http_response_code(200);
    exit('Unsubscribed.');
}

/**
 * API for the daily prospect finder. Header X-Api-Key: the key from /super → Prospects.
 *   GET  → {"known_domains": [...]} so the finder skips organizations already in the list
 *   POST {"prospects": [{"org","first_name","email","phone","website","city","state","kind","source"}, ...]}
 *        → {"added": n, "skipped": [[email, reason], ...]}
 */
function webhook_prospects(): void
{
    $hash = (string) setting('prospect_api_key_hash', '');
    $key = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($hash === '' || $key === '' || !hash_equals($hash, hash('sha256', $key))) {
        json_out(['error' => 'Invalid or missing X-Api-Key'], 401);
    }
    $GLOBALS['audit_system'] = true;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && input('summary') === '1') {
        // Read-only status for the provider's assistant: what was sent, what is queued, did the hourly job run
        $counts = array_column(rows('SELECT status, COUNT(*) AS n FROM prospects GROUP BY status'), 'n', 'status');
        $dayStart = (new DateTime('today', outreach_tz()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        json_out([
            'sending_on'   => setting('prospects_on', '0') === '1',
            'problem'      => outreach_problem(),
            'daily_cap'    => (int) setting('prospects_daily', '20'),
            'counts'       => array_map('intval', $counts),
            'sent_today'   => (int) val('SELECT COUNT(*) FROM prospects WHERE last_sent_at >= ?', [$dayStart]),
            'delivery_today' => row("SELECT COUNT(*) AS accepted, COALESCE(SUM(in_sent), 0) AS in_sent_folder, COALESCE(SUM(outcome = 'bounced'), 0) AS bounced, COALESCE(SUM(outcome = 'replied'), 0) AS replied FROM prospect_sends WHERE sent_at >= ?", [$dayStart]),
            'recent_delivery' => rows('SELECT s.sent_at, p.org, s.to_email, s.step, s.server_reply, s.in_sent, s.outcome, s.outcome_note FROM prospect_sends s LEFT JOIN prospects p ON p.id = s.prospect_id ORDER BY s.id DESC LIMIT 30'),
            'mailbox'      => ['ready' => mailbox_ready(), 'problem' => mailbox_problem(), 'driver' => (string) (mail_config('contact')['driver'] ?? ''), 'host' => (string) (mail_config('contact')['host'] ?? ''), 'last_ok_utc' => setting('imap_last_ok', null), 'error' => (string) setting('imap_last_error', '')],
            'recent_sends' => rows('SELECT org, email, state, kind, status, step, last_sent_at FROM prospects WHERE last_sent_at IS NOT NULL ORDER BY last_sent_at DESC LIMIT 50'),
            'hourly_job'   => ['last_run_utc' => setting('cron_last_run', null), 'errors' => (int) setting('cron_last_errors', '0'),
                               'status' => hourly_job_status()[0], 'log' => (string) setting('cron_last_log', '')],
        ]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $domains = [];
        foreach (rows('SELECT email, website FROM prospects') as $r) {
            $domains[prospect_domain($r['email'])] = true; // franchise domains are dropped below; the finder checks those by email
            if ($r['website'] && ($h = parse_url($r['website'], PHP_URL_HOST))) {
                $domains[preg_replace('/^www\./', '', strtolower($h))] = true;
            }
        }
        $known = array_keys(array_diff_key($domains, array_flip(array_merge(FREEMAIL, SHARED_DOMAINS))));
        json_out(['known_domains' => $known, 'count' => count($known)]);
    }
    $body = json_decode((string) file_get_contents('php://input'), true);
    $list = is_array($body['prospects'] ?? null) ? $body['prospects'] : null;
    if ($list === null) {
        json_out(['error' => 'Send JSON: {"prospects": [...]}'], 400);
    }
    $r = prospects_add($list, 'finder');
    audit('Prospect finder', null, true, $r['added'] . ' added, ' . count($r['skipped']) . ' skipped');
    json_out($r);
}

/** Sends the 3 outreach emails for a sample business to one address, now, marked [TEST]. Real prospects are untouched. */
function super_prospects_test(): void
{
    require_super();
    // One or more addresses (comma or one per line), kept as the saved test list for next time
    $raw = preg_split('/[\s,;]+/', strtolower((string) input('test_to')), -1, PREG_SPLIT_NO_EMPTY);
    $bad = array_values(array_filter($raw, fn($a) => !filter_var($a, FILTER_VALIDATE_EMAIL)));
    $list = array_slice(array_values(array_unique(array_filter($raw, fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)))), 0, 10);
    $kind = isset(PROSPECT_KINDS[(string) input('test_kind')]) ? (string) input('test_kind') : 'dental';
    if ($bad) {
        flash('error', 'Fix these addresses first: ' . implode(', ', $bad));
        redirect(base_path() . '/super/prospects#test');
    }
    if (!$list) {
        flash('error', 'Enter the email address(es) the test should go to.');
        redirect(base_path() . '/super/prospects#test');
    }
    setting_set('test_recipients', implode("\n", $list));
    if ($p = outreach_problem()) {
        flash('error', 'Save the outreach settings first: ' . $p);
        redirect(base_path() . '/super/prospects#test');
    }
    $names = ['clinic' => 'Summit Family Clinic', 'dental' => 'Smile Bright Dental', 'daycare' => 'Little Stars Daycare',
        'tutoring' => 'Bright Minds Tutoring', 'school' => 'Mountain View Academy', 'office' => 'Summit CPA Group', 'gym' => 'Iron Valley Fitness',
        'construction' => 'Peak Roofing', 'retail' => 'Main Street Outfitters', 'restaurant' => 'Canyon Grill', 'other' => 'Sample Business'];
    $sample = ['org' => trim((string) input('test_org')) ?: $names[$kind],
               'first_name' => trim((string) input('test_first')) ?: null, 'kind' => $kind, 'token' => str_repeat('0', 32)];
    $sent = 0;
    $error = '';
    foreach ($list as $to) {
        foreach ([0, 1, 2] as $step) {
            [$subject, $html] = prospect_email($sample, $step);
            if (send_mail([$to], '[TEST ' . ($step + 1) . '/3] ' . $subject, $html)) {
                $sent++;
            } else {
                $error = $to . ': ' . (string) ($GLOBALS['mail_error'] ?? 'unknown error');
                break 2;
            }
        }
    }
    $want = 3 * count($list);
    audit_note(implode(', ', $list), "$sent of $want test emails ($kind)" . ($error ? "; error: $error" : ''));
    flash($sent === $want ? 'success' : 'error', $sent === $want
        ? "$sent test emails sent (3 each to " . count($list) . ' address' . (count($list) > 1 ? 'es' : '') . '). Check each inbox and spam folder; contact@ Sent Items shows them too.'
        : "Only $sent of $want test emails were sent. Mail error: $error");
    redirect(base_path() . '/super/prospects#test');
}

/** Runs the hourly job now, exactly like the cron, and shows its output. */
function super_jobs_run(): void
{
    require_super();
    $lines = run_hourly_jobs();
    $_SESSION['job_output'] = implode("\n", $lines) ?: 'Nothing due.';
    flash('success', 'The hourly job ran. Its output is shown below the status line.');
    redirect(base_path() . '/super/prospects');
}
