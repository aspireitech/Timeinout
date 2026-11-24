<?php
// The contact mailbox over IMAP (no PHP extension needed), used by outreach:
//   * files a copy of every outreach email in the mailbox's Sent folder, so you can see exactly what went out
//     (email sent by an app through SMTP never appears in Sent on its own);
//   * reads new messages in the inbox each hour: a bounce marks the prospect "Bounced", a reply marks them
//     "Replied" (no more automatic emails) and emails you an alert. Messages are only read, never changed or deleted.
// Server: /super → Prospects → "Mailbox server (IMAP)"; default = the SMTP host with smtp. replaced by imap.
// Login: the same mailbox and password as the contact sender.

final class ImapClient
{
    /** @var resource */
    private $fp;
    private int $n = 0;

    public function __construct(string $host, int $port, int $timeout = 20)
    {
        $fp = @stream_socket_client('ssl://' . $host . ':' . $port, $errno, $err, $timeout);
        if (!$fp) {
            throw new RuntimeException("cannot reach the mailbox server $host:$port ($err)");
        }
        $this->fp = $fp;
        stream_set_timeout($fp, $timeout);
        $hello = $this->line();
        if (!preg_match('/^\* (OK|PREAUTH)/i', $hello)) {
            throw new RuntimeException('mailbox server said: ' . trim($hello));
        }
    }

    private function line(): string
    {
        $l = fgets($this->fp, 65536);
        if ($l === false) {
            throw new RuntimeException('the mailbox server closed the connection');
        }
        return $l;
    }

    private function read(int $len): string
    {
        $buf = '';
        while (strlen($buf) < $len) {
            $chunk = fread($this->fp, min(65536, $len - strlen($buf)));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('the mailbox server stopped sending');
            }
            $buf .= $chunk;
        }
        return $buf;
    }

    private function put(string $s): void
    {
        while ($s !== '') {
            $w = fwrite($this->fp, $s);
            if ($w === false || $w === 0) {
                throw new RuntimeException('cannot write to the mailbox server');
            }
            $s = (string) substr($s, $w);
        }
    }

    /**
     * Runs one command (optionally ending in a literal, e.g. APPEND). Returns
     * ['lines' => untagged lines, 'lits' => [[text before the literal, literal], ...]].
     */
    public function cmd(string $command, ?string $literal = null): array
    {
        $tag = 'C' . (++$this->n);
        if ($literal !== null) {
            $this->put("$tag $command {" . strlen($literal) . "}\r\n");
            $l = $this->line();
            if ($l[0] !== '+') {
                throw new RuntimeException('mailbox server refused: ' . trim($l));
            }
            $this->put($literal . "\r\n");
        } else {
            $this->put("$tag $command\r\n");
        }
        $out = ['lines' => [], 'lits' => []];
        while (true) {
            $l = $this->line();
            while (preg_match('/\{(\d+)\}\r\n$/', $l, $m)) {
                $out['lits'][] = [$l, $this->read((int) $m[1])];
                $l = $this->line();
            }
            if (str_starts_with($l, $tag . ' ')) {
                if (!preg_match('/^' . $tag . ' OK/i', $l)) {
                    throw new RuntimeException(trim(substr($l, strlen($tag) + 1)));
                }
                return $out;
            }
            $out['lines'][] = $l;
        }
    }

    public static function q(string $s): string
    {
        return '"' . addcslashes($s, "\"\\") . '"';
    }

    public function close(): void
    {
        try {
            $this->cmd('LOGOUT');
        } catch (Throwable $e) {
            // closing anyway
        }
        @fclose($this->fp);
    }
}

/** IMAP settings for the contact mailbox: [host, port, user, pass]. Host '' when unknown. */
function mailbox_config(): array
{
    $m = mail_config('contact');
    $host = trim((string) setting('imap_host', ''));
    if ($host === '' && preg_match('/^smtp\./i', (string) ($m['host'] ?? ''))) {
        $host = 'imap.' . substr((string) $m['host'], 5);
    }
    $user = (string) (($m['user'] ?? '') ?: ($m['from_email'] ?? ''));
    return [$host, (int) setting('imap_port', '993') ?: 993, $user, (string) ($m['pass'] ?? '')];
}

/** Is the mailbox reachable from here (SMTP sender with a login, IMAP host known)? */
function mailbox_ready(): bool
{
    return mailbox_problem() === null;
}

/** Why the Sent copies and the reply / bounce check cannot run, in plain words (never shows a password), or null. */
function mailbox_problem(): ?string
{
    $m = mail_config('contact');
    [$host, , $user, $pass] = mailbox_config();
    $driver = (string) ($m['driver'] ?? 'mail');
    if ($driver === 'graph') { // Microsoft 365: Sent copies by Microsoft, inbox read through Graph (Mail.Read)
        $g = cfg('graph', []);
        return empty($g['tenant_id']) || empty($g['client_id']) || empty($g['client_secret']) ? 'Microsoft Graph is not fully set up in app/config.php.' : null;
    }
    if ($driver !== 'smtp') {
        return 'Email is sent with the server\'s built-in PHP mail(), not through your mailbox. In app/config.php set \'mail\' → \'driver\' => \'smtp\' (host smtp.hostinger.com, port 465, secure ssl) and the contact mailbox password under \'mail_senders\' → \'contact\' → \'pass\'.';
    }
    if ($user === '') {
        return 'No mailbox login: add \'user\' => \'contact@…\' under \'mail_senders\' → \'contact\' in app/config.php.';
    }
    if ($pass === '') {
        return 'No mailbox password for ' . $user . ': add it under \'mail_senders\' → \'contact\' → \'pass\' in app/config.php.';
    }
    if ($host === '') {
        return 'The mailbox (IMAP) server is unknown because the SMTP server is ' . ($m['host'] ?? '?') . ': type it in /super → Prospects → "Mailbox server (IMAP)" (Hostinger: imap.hostinger.com).';
    }
    return null;
}

function mailbox_open(): ImapClient
{
    [$host, $port, $user, $pass] = mailbox_config();
    $c = new ImapClient($host, $port);
    try {
        $c->cmd('LOGIN ' . ImapClient::q($user) . ' ' . ImapClient::q($pass));
    } catch (Throwable $e) {
        $c->close();
        throw new RuntimeException('mailbox sign-in failed (' . $e->getMessage() . ')');
    }
    return $c;
}

/** The Sent folder's name: the folder marked \Sent, else a usual name. Remembered once found. */
function mailbox_sent_folder(ImapClient $c): string
{
    $known = (string) setting('imap_sent_folder', '');
    if ($known !== '') {
        return $known;
    }
    $names = [];
    foreach ($c->cmd('LIST "" "*"')['lines'] as $l) {
        if (!preg_match('/^\* LIST \(([^)]*)\) (?:"(?:[^"\\\\]|\\\\.)*"|NIL) (.+)$/i', rtrim($l), $m)) {
            continue;
        }
        $name = trim($m[2]);
        if ($name !== '' && $name[0] === '"') {
            $name = stripcslashes(substr($name, 1, -1));
        }
        if (stripos($m[1], '\\Sent') !== false) {
            setting_set('imap_sent_folder', $name);
            return $name;
        }
        $names[] = $name;
    }
    foreach ($names as $name) {
        if (preg_match('/^(INBOX[.\/])?Sent( Items| Messages| Mail)?$/i', $name)) {
            setting_set('imap_sent_folder', $name);
            return $name;
        }
    }
    throw new RuntimeException('no Sent folder found in the mailbox');
}

/**
 * Files copies of sent messages (raw RFC 822 text) in the Sent folder, marked as read.
 * Returns how many were saved; problems are reported in $GLOBALS['mailbox_error'].
 */
function mailbox_file_sent(array $raws): int
{
    $GLOBALS['mailbox_error'] = null;
    $raws = array_values(array_filter($raws, 'is_string'));
    if (!$raws || setting('outreach_save_sent', '1') !== '1' || !mailbox_ready() || mailbox_is_graph()) {
        return 0;
    }
    $saved = 0;
    try {
        $c = mailbox_open();
        try {
            $folder = mailbox_sent_folder($c);
            foreach ($raws as $raw) {
                $c->cmd('APPEND ' . ImapClient::q($folder) . ' (\\Seen)', $raw);
                $saved++;
            }
        } finally {
            $c->close();
        }
    } catch (Throwable $e) {
        $GLOBALS['mailbox_error'] = $e->getMessage();
        setting_set('imap_last_error', mb_substr('Saving to Sent: ' . $e->getMessage(), 0, 300));
    }
    return $saved;
}

/** "Name: value" headers (unfolded, lower-case names; MIME words decoded). */
function mailbox_headers(string $raw): array
{
    $h = [];
    foreach (explode("\n", preg_replace("/\r?\n[ \t]+/", ' ', str_replace("\r", '', $raw))) as $line) {
        if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $m)) {
            $v = trim($m[2]);
            $dec = @mb_decode_mimeheader($v);
            $h[strtolower($m[1])] = is_string($dec) ? $dec : $v;
        }
    }
    return $h;
}

/** The last email address in a header value, lower case, or ''. */
function mailbox_address(string $v): string
{
    return preg_match_all('/[A-Z0-9._%+\'=-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $v, $m) ? strtolower(end($m[0])) : '';
}

/** What kind of message this is: 'bounce', 'delay', 'auto' (out of office etc.) or 'mail'. */
function mailbox_kind(array $h, string $text): string
{
    $from = $h['from'] ?? '';
    $subject = $h['subject'] ?? '';
    $local = strtolower(strtok(mailbox_address($from), '@') ?: '');
    $isReport = stripos($h['content-type'] ?? '', 'report-type=delivery-status') !== false;
    if ($isReport || in_array($local, ['mailer-daemon', 'postmaster', 'mail-daemon'], true)
        || preg_match('/undeliver|delivery status notification|returned mail|delivery failure|failure notice|mail delivery (failed|system)|could not be delivered|delivery has failed/i', $subject)) {
        if (preg_match('/^Action:\s*delayed/im', $text) && !preg_match('/^Action:\s*failed/im', $text)) {
            return 'delay';
        }
        if (preg_match('/\(delay\)|delayed|will retry/i', $subject)) {
            return 'delay';
        }
        return 'bounce';
    }
    $auto = strtolower(trim($h['auto-submitted'] ?? 'no'));
    if (($auto !== '' && $auto !== 'no') || preg_match('/^(automatic reply|auto(matic)?[ -]?reply|autoreply|out of (the )?office|away from|on vacation|thank you for (your email|contacting))/i', $subject)) {
        return 'auto';
    }
    return 'mail';
}

/** The addresses a bounce says failed. */
function mailbox_bounced_addresses(array $h, string $text): array
{
    $list = [];
    foreach (preg_split('/[\s,]+/', $h['x-failed-recipients'] ?? '', -1, PREG_SPLIT_NO_EMPTY) as $a) {
        $list[] = mailbox_address($a);
    }
    if (preg_match_all('/^(?:Final|Original)-Recipient:\s*rfc822;\s*<?([^\s>]+)/im', $text, $m)) {
        foreach ($m[1] as $a) {
            $list[] = mailbox_address($a);
        }
    }
    return array_values(array_unique(array_filter($list)));
}

/** The server's reason in a bounce, e.g. "550 5.1.1 User unknown". */
function mailbox_bounce_reason(string $text): string
{
    if (preg_match('/^Diagnostic-Code:\s*(?:smtp;\s*)?(.+)$/im', $text, $m)) {
        return mb_substr(trim($m[1], " \t'\""), 0, 200);
    }
    if (preg_match('/\b(5\d\d[ -][0-9.]*\s*[^\r\n]{5,150})/', $text, $m)) {
        return mb_substr(trim($m[1], " \t'\""), 0, 200);
    }
    return 'The receiving mail server rejected the email.';
}

/** Records the outcome on the prospect and on its last outreach email. */
function mailbox_mark(array $p, string $status, string $note): void
{
    update('prospects', ['status' => $status, 'next_at' => null], 'id = ?', [$p['id']]);
    prospect_event((int) $p['id'], $status);
    q("UPDATE prospect_sends SET outcome = ?, outcome_at = UTC_TIMESTAMP(), outcome_note = ? WHERE prospect_id = ? ORDER BY id DESC LIMIT 1",
        [$status, mb_substr($note, 0, 250), $p['id']]);
}

/** The receiving mail server says the sender is forged (DMARC failed, or both SPF and DKIM failed). */
function mailbox_spoofed(array $h): bool
{
    $ar = strtolower((string) ($h['authentication-results'] ?? ''));
    return str_contains($ar, 'dmarc=fail') || (str_contains($ar, 'spf=fail') && str_contains($ar, 'dkim=fail'));
}

/** Acts on one inbox message: a bounce, an automatic reply or a real reply from a prospect. */
function mailbox_handle(array $h, string $text, array &$log, array &$replied): void
{
    if (mailbox_spoofed($h)) {
        return; // forged sender: never let a fake reply or bounce change a prospect
    }
    $kind = mailbox_kind($h, $text);
    if ($kind === 'bounce') {
        $addrs = mailbox_bounced_addresses($h, $text);
        if (!$addrs) { // no machine-readable report: look for an address we emailed in the text
            preg_match_all('/[A-Z0-9._%+\'=-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $m);
            $addrs = array_unique(array_map('strtolower', $m[0]));
        }
        foreach ($addrs as $a) {
            $p = row("SELECT * FROM prospects WHERE email = ? AND last_sent_at IS NOT NULL AND status IN ('new','active','done') LIMIT 1", [$a]);
            if ($p) {
                $why = mailbox_bounce_reason($text);
                mailbox_mark($p, 'bounced', $why);
                $log[] = 'prospect ' . $p['org'] . ': BOUNCED (' . $why . ')';
            }
        }
        return;
    }
    if ($kind !== 'mail' && $kind !== 'auto') {
        return;
    }
    $from = mailbox_address($h['from'] ?? '');
    $p = $from !== '' ? row("SELECT * FROM prospects WHERE email = ? AND status IN ('new','active','done') LIMIT 1", [$from]) : null;
    if (!$p && preg_match_all('/<[^<>\s]+>/', ($h['in-reply-to'] ?? '') . ' ' . ($h['references'] ?? ''), $ids)) {
        foreach (array_unique($ids[0]) as $id) { // a colleague answered our email from another address
            $p = row("SELECT p.* FROM prospects p JOIN prospect_sends s ON s.prospect_id = p.id WHERE s.message_id = ? AND p.status IN ('new','active','done') LIMIT 1", [$id]);
            if ($p) {
                break;
            }
        }
    }
    if (!$p) {
        return;
    }
    $subject = mb_substr((string) ($h['subject'] ?? ''), 0, 150);
    if ($kind === 'auto') {
        q("UPDATE prospect_sends SET outcome_note = ? WHERE prospect_id = ? AND outcome IS NULL ORDER BY id DESC LIMIT 1", ['Auto-reply: ' . $subject, $p['id']]);
        $log[] = 'prospect ' . $p['org'] . ': automatic reply (' . $subject . '), emails continue';
        return;
    }
    mailbox_mark($p, 'replied', 'Reply from ' . $from . ': ' . $subject);
    $log[] = 'prospect ' . $p['org'] . ': REPLIED, no more automatic emails';
    $replied[] = [$p, $from, $subject];
}

/**
 * Hourly: reads inbox messages that arrived since the last check (read-only: nothing is marked, moved or deleted)
 * and updates prospects. Returns log lines.
 */
function mailbox_check(): array
{
    if (!mailbox_ready() || !(int) val('SELECT COUNT(*) FROM prospects WHERE last_sent_at IS NOT NULL')) {
        return [];
    }
    $log = [];
    $replied = [];
    try {
        if (mailbox_is_graph()) {
            mailbox_check_graph($log, $replied);
        } else {
            $c = mailbox_open();
            try {
                $sel = $c->cmd('EXAMINE INBOX'); // read-only: nothing in the inbox changes
                $validity = '';
                foreach ($sel['lines'] as $l) {
                    if (preg_match('/UIDVALIDITY (\d+)/i', $l, $m)) {
                        $validity = $m[1];
                    }
                }
                $last = (int) setting('imap_last_uid', '0');
                if ($validity !== (string) setting('imap_uidvalidity', '')) {
                    $last = 0; // first check, or the mailbox was rebuilt: look back from the first outreach email
                }
                $since = (string) (val("SELECT MIN(last_sent_at) FROM prospects WHERE status IN ('new','active','done')") ?: gmdate('Y-m-d'));
                $since = max(strtotime($since . ' UTC') - 86400, time() - 30 * 86400);
                $res = $c->cmd('UID SEARCH UID ' . ($last + 1) . ':* SINCE ' . gmdate('j-M-Y', $since));
                $uids = [];
                foreach ($res['lines'] as $l) {
                    if (preg_match('/^\* SEARCH ?(.*)$/i', trim($l), $m)) {
                        $uids = array_merge($uids, array_map('intval', preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY)));
                    }
                }
                $uids = array_values(array_filter(array_unique($uids), fn($u) => $u > $last));
                sort($uids);
                $uids = array_slice($uids, 0, 150); // the rest next hour
                $max = $last;
                foreach ($uids as $uid) {
                    $r = $c->cmd("UID FETCH $uid (BODY.PEEK[HEADER.FIELDS (FROM SUBJECT IN-REPLY-TO REFERENCES AUTO-SUBMITTED X-FAILED-RECIPIENTS CONTENT-TYPE AUTHENTICATION-RESULTS)] BODY.PEEK[TEXT]<0.40000>)");
                    $head = $text = '';
                    foreach ($r['lits'] as [$before, $lit]) {
                        if (stripos($before, 'HEADER') !== false) {
                            $head = $lit;
                        } else {
                            $text = $lit;
                        }
                    }
                    $max = max($max, $uid);
                    mailbox_handle(mailbox_headers($head), $text, $log, $replied);
                }
                setting_set('imap_uidvalidity', $validity);
                setting_set('imap_last_uid', (string) $max);
            } finally {
                $c->close();
            }
        }
        setting_set('imap_last_ok', gmdate('Y-m-d H:i:s'));
        setting_set('imap_last_error', '');
    } catch (Throwable $e) {
        setting_set('imap_last_error', mb_substr('Checking the inbox: ' . $e->getMessage(), 0, 300));
        $log[] = 'mailbox check failed: ' . $e->getMessage();
    }
    foreach ($replied as [$p, $from, $subject]) {
        provider_alert('Reply from ' . $p['org'] . ': answer it today', '<h2 style="margin:0 0 8px">A prospect replied</h2>'
            . '<p><b>' . e($p['org']) . '</b>' . ($p['city'] ? ' · ' . e($p['city']) . ', ' . e((string) $p['state']) : '') . '<br>From: ' . e($from)
            . ($p['phone'] ? '<br>Phone: ' . e($p['phone']) : '') . '<br>Subject: ' . e($subject) . '</p>'
            . '<p>Their reply is in the contact inbox. Answer within a few hours: a fast answer is the best way to win the customer.'
            . ' Automatic emails to them have stopped.</p>');
    }
    return $log;
}

/** Is the contact mailbox on Microsoft 365, sending through Graph? */
function mailbox_is_graph(): bool
{
    return (mail_config('contact')['driver'] ?? '') === 'graph';
}

/**
 * Microsoft 365: reads new inbox messages through Graph (application permission Mail.Read on the same Entra app
 * that sends; nothing is changed in the mailbox). Same handling as the IMAP check.
 */
function mailbox_check_graph(array &$log, array &$replied): void
{
    // The main mailbox, plus the separate outreach mailbox (second domain) when one is set
    $boxes = ['graph_inbox_since' => (string) ((mail_config('contact')['from_email'] ?? '') ?: contact_email())];
    if (($ob = outreach_from_email()) !== null && strcasecmp($ob, $boxes['graph_inbox_since']) !== 0) {
        $boxes['graph_inbox_since_outreach'] = $ob;
    }
    foreach ($boxes as $key => $box) {
        mailbox_check_graph_box($box, $key, $log, $replied);
    }
}

function mailbox_check_graph_box(string $box, string $sinceKey, array &$log, array &$replied): void
{
    $since = (string) setting($sinceKey, '');
    if ($since === '') { // first check: look back to the earliest outreach email still in progress
        $first = (string) (val("SELECT MIN(last_sent_at) FROM prospects WHERE status IN ('new','active','done')") ?: gmdate('Y-m-d H:i:s'));
        $since = gmdate('Y-m-d\\TH:i:s\\Z', max(strtotime($first . ' UTC') - 86400, time() - 30 * 86400));
    }
    $url = rtrim(cfg('graph')['api_url'] ?? 'https://graph.microsoft.com', '/') . '/v1.0/users/' . rawurlencode($box) . '/mailFolders/inbox/messages?'
        . http_build_query(['$filter' => 'receivedDateTime gt ' . $since, '$orderby' => 'receivedDateTime asc', '$top' => 50,
            '$select' => 'receivedDateTime,from,subject,internetMessageHeaders,body']);
    $latest = $since;
    for ($page = 0; $url && $page < 3; $page++) {
        [$code, $d] = graph_http('GET', $url, ['Authorization: Bearer ' . graph_token(), 'Prefer: outlook.body-content-type="text"'], '');
        if ($code === 403 || $code === 401) {
            throw new RuntimeException('Microsoft 365 refused to show the inbox. In Microsoft Entra → App registrations → your ClockInOut app → API permissions, add Microsoft Graph → Application permission "Mail.Read", then "Grant admin consent". Sent copies work without it.');
        }
        if ($code !== 200) {
            throw new RuntimeException('Microsoft Graph: ' . ($d['error']['message'] ?? "HTTP $code"));
        }
        foreach ($d['value'] ?? [] as $msg) {
            $h = [];
            foreach ($msg['internetMessageHeaders'] ?? [] as $hd) {
                $h[strtolower((string) ($hd['name'] ?? ''))] = (string) ($hd['value'] ?? '');
            }
            $h['subject'] = (string) ($msg['subject'] ?? ($h['subject'] ?? ''));
            $addr = (string) ($msg['from']['emailAddress']['address'] ?? '');
            $h['from'] = trim(($msg['from']['emailAddress']['name'] ?? '') . ' <' . $addr . '>');
            mailbox_handle($h, mb_substr((string) ($msg['body']['content'] ?? ''), 0, 40000), $log, $replied);
            $at = (string) ($msg['receivedDateTime'] ?? '');
            if ($at !== '' && strtotime($at) > strtotime($latest)) {
                $latest = gmdate('Y-m-d\\TH:i:s\\Z', (int) strtotime($at));
            }
        }
        $url = $d['@odata.nextLink'] ?? null;
    }
    setting_set($sinceKey, $latest);
}
