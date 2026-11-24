<?php
$on = setting('prospects_on', '0') === '1';
$problem = outreach_problem();
$badge = ['new' => 'gray', 'active' => 'out', 'done' => 'gray', 'replied' => 'in', 'demo' => 'in', 'signed_up' => 'in', 'unsubscribed' => 'warn', 'bounced' => 'danger', 'removed' => 'gray'];
$total = array_sum($counts);
?>
<div class="stack" style="margin-bottom:60px">
  <div class="card">
    <div class="card-head">
      <h2><?= icon('mail') ?> Outreach to prospects</h2>
      <span class="badge <?= $on ? 'in' : 'warn' ?>"><?= $on ? 'Sending is on' : 'Sending is off' ?></span>
    </div>
    <?php [$jobMsg, $jobCls] = hourly_job_status(); ?>
    <p style="margin:0 0 10px"><span class="badge <?= e($jobCls) ?>"><?= e($jobMsg) ?></span></p>
    <form method="post" action="<?= e(base_path() . '/super/jobs/run') ?>" style="margin:0 0 10px"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Run the hourly job now</button>
      <span class="muted small">Runs exactly what the cron runs. Outreach still only sends on weekdays in each lead's business hours.</span></form>
    <?php if (!empty($_SESSION['job_output'])): ?><pre class="small" style="white-space:pre-wrap;max-height:220px;overflow:auto;background:#F4F5FA;padding:8px;border-radius:8px"><?= e($_SESSION['job_output']) ?></pre><?php unset($_SESSION['job_output']); endif; ?>
    <?php if (setting('cron_last_log', '') !== ''): ?><details style="margin:0 0 10px"><summary class="small">Last hourly job output</summary><pre class="small" style="white-space:pre-wrap;max-height:220px;overflow:auto"><?= e((string) setting('cron_last_log')) ?></pre></details><?php endif; ?>
    <p class="muted small">Each prospect gets <b>email 1</b>, a <b>follow-up 4 days later</b> and a <b>last note 5 days after that</b>, in each lead's own business hours (weekdays, by their state's time zone), at most <?= (int) ceil(max(1, (int) setting('prospects_daily', '25')) / 4) ?> per hour.
      Emails stop by themselves when the prospect signs up, books a demo, unsubscribes, <b>replies</b> or <b>bounces</b>: every hour the contact inbox is checked, and you get an email the moment someone replies.
      Sent from your contact mailbox, so replies come to you, and a copy of each email is filed in its <b>Sent</b> folder.</p>
    <form method="post" action="<?= e(base_path() . '/super/prospects/settings') ?>">
      <?= csrf_field() ?>
      <div class="grid c2">
        <label class="check" style="grid-column:1 / -1"><input type="checkbox" name="prospects_on" value="1" <?= $on ? 'checked' : '' ?>>
          <span>Send outreach emails automatically<?php if ($problem): ?><br><span class="small" style="color:#B4232A"><?= e($problem) ?></span><?php endif; ?></span></label>
        <div class="field"><label for="o-name">Your name (signature)</label><input type="text" id="o-name" name="outreach_name" value="<?= e(setting('outreach_name', '')) ?>" placeholder="e.g. Alex Morgan"></div>
        <div class="field"><label for="o-phone">Phone (signature, optional)</label><input type="text" id="o-phone" name="outreach_phone" value="<?= e(setting('outreach_phone', '')) ?>" placeholder="+1 (555) 123-4567"></div>
        <div class="field" style="grid-column:1 / -1"><label for="o-from">Send outreach from (separate mailbox, optional)</label><input type="email" id="o-from" name="outreach_from_email" value="<?= e(setting('outreach_from_email', '')) ?>" placeholder="e.g. sukanta@tryclockinout.com">
          <span class="muted small">A mailbox on a second domain in the same Microsoft 365 account, warmed up first. Cold emails then never affect the main domain that sends sign-in codes and reports. Empty = the main contact mailbox. Replies and bounces in that mailbox are read too.</span></div>
        <div class="field" style="grid-column:1 / -1"><label for="o-addr">Business postal address (required by US law in sales emails)</label><textarea id="o-addr" name="outreach_address" rows="3" placeholder="Company name&#10;Street&#10;City, State ZIP"><?= e(setting('outreach_address', '')) ?></textarea></div>
        <div class="field"><label for="o-cap">Emails per day (all steps together)</label><input type="number" id="o-cap" name="prospects_daily" min="1" max="100" value="<?= e(setting('prospects_daily', '20')) ?>">
          <span class="muted small">25 is a safe daily amount. Raise it slowly (about 10 more per week) while bounces stay under 3%; a sudden jump lands in spam.</span></div>
        <div class="field"><label for="o-tz">Sending hours follow this time zone</label><input type="text" id="o-tz" name="outreach_tz" value="<?= e(setting('outreach_tz', 'America/Denver')) ?>"></div>
        <?php [$imapHost] = mailbox_config(); if (mailbox_is_graph()): ?>
        <div class="field"><label>Mailbox</label><p class="small" style="margin:4px 0 0">Microsoft 365: copies go to <b>Sent Items</b> by themselves; replies and bounces are read through Microsoft Graph (needs the <b>Mail.Read</b> permission on your app).</p></div>
        <?php else: ?>
        <div class="field"><label for="o-imap">Mailbox server (IMAP) for Sent copies and the reply / bounce check</label><input type="text" id="o-imap" name="imap_host" value="<?= e(setting('imap_host', '')) ?>" placeholder="<?= e($imapHost ?: 'imap.hostinger.com') ?>">
          <span class="muted small">Leave empty to use <?= e($imapHost ?: 'the SMTP server name with imap.') ?>. Same mailbox and password as sending.</span></div>
        <?php endif; ?>
        <label class="check"><input type="checkbox" name="outreach_save_sent" value="1" <?= setting('outreach_save_sent', '1') === '1' ? 'checked' : '' ?>> <span>Save a copy of each outreach email in the Sent folder</span></label>
        <label class="check"><input type="checkbox" name="outreach_track_opens" value="1" <?= setting('outreach_track_opens', '0') === '1' ? 'checked' : '' ?>> <span>Count opens (estimate) with a tiny image<br><span class="muted small">Off is recommended: iPhones "open" every email by themselves, and tracking images can push cold email into spam. Clicks and replies are counted either way.</span></span></label>
      </div>
      <button class="btn btn-primary btn-sm">Save outreach settings</button>
    </form>
  </div>

  <div class="card" id="delivery">
    <div class="card-head">
      <h2><?= icon('check') ?> Delivery log</h2>
      <span class="muted small">Today: <b><?= (int) ($today['sent'] ?? 0) ?></b> accepted by the mail server · <?= (int) ($today['in_sent'] ?? 0) ?> in Sent · <?= (int) ($today['bounced'] ?? 0) ?> bounced · <?= (int) ($today['replied'] ?? 0) ?> replied
        &nbsp;|&nbsp; All time: <?= (int) ($totals['sent'] ?? 0) ?> sent · <?= (int) ($totals['bounced'] ?? 0) ?> bounced · <?= (int) ($totals['replied'] ?? 0) ?> replied</span>
    </div>
    <?php $imapErr = (string) setting('imap_last_error', ''); $imapOk = (string) setting('imap_last_ok', ''); ?>
    <?php if (!mailbox_ready()): ?>
      <p class="small"><span class="badge warn">Sent copies and inbox check are off</span> Sending works, but no copy goes to Sent and replies and bounces are not read automatically. Why: <?= e((string) mailbox_problem()) ?></p>
    <?php elseif ($imapErr !== ''): ?>
      <p class="small"><span class="badge danger">Mailbox problem</span> <?= e($imapErr) ?> Sending is not affected.<?= mailbox_is_graph() ? '' : ' Check the IMAP server name above (Hostinger: imap.hostinger.com).' ?></p>
    <?php elseif ($imapOk !== ''): ?>
      <p class="small"><span class="badge in">Inbox checked <?= e((new DateTime($imapOk, new DateTimeZone('UTC')))->setTimezone(outreach_tz())->format('M j, g:i A')) ?></span> Replies and bounces update the prospects below by themselves.</p>
    <?php endif; ?>
    <p class="muted small" style="margin-top:0"><b>Accepted</b> = your mail server took the email for delivery (its own answer is shown). A bad address comes back as a <b>bounce</b> within minutes to a day, and is then shown here. Accepted and not bounced = delivered to their mail server.</p>
    <div class="table-wrap" style="max-height:420px;overflow:auto">
    <table class="table">
      <tr><th>Sent</th><th>To</th><th>Email</th><th>Mail server answer</th><th>Result</th></tr>
      <?php foreach ($sends as $s): ?>
        <tr class="small">
          <td class="nowrap"><?= e((new DateTime($s['sent_at'], new DateTimeZone('UTC')))->setTimezone(outreach_tz())->format('M j, g:i A')) ?></td>
          <td><b><?= e((string) $s['org']) ?></b><br><?= e($s['to_email']) ?></td>
          <td class="nowrap"><?= e(['', 'Email 1', 'Follow-up', 'Last note'][(int) $s['step']] ?? 'Email ' . (int) $s['step']) ?><?= $s['in_sent'] ? '<br><span class="muted">copy in Sent</span>' : '' ?></td>
          <td class="muted" style="max-width:260px;word-break:break-word"><?= e((string) ($s['server_reply'] ?: 'accepted')) ?></td>
          <td><?php if ($s['outcome'] === 'bounced'): ?><span class="badge danger">Bounced</span><?php elseif ($s['outcome'] === 'replied'): ?><span class="badge in">Replied</span><?php else: ?><span class="badge out">Delivered</span><?php endif; ?>
            <?php if ($s['outcome_note']): ?><br><span class="muted"><?= e($s['outcome_note']) ?></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$sends): ?><tr><td colspan="5" class="empty">Nothing sent since this log started. Each outreach email will be listed here with the mail server's answer.</td></tr><?php endif; ?>
    </table>
    </div>
  </div>

  <div class="card" id="test">
    <h2><?= icon('mail') ?> Send me a test</h2>
    <p class="muted small">Sends all 3 outreach emails (first email, follow-up, last note) for a sample business to your saved test addresses, right now, marked [TEST]. Use it to check a new setup or the mail flow. Real prospects are not touched.</p>
    <form method="post" action="<?= e(base_path() . '/super/prospects/test') ?>">
      <?= csrf_field() ?>
      <div class="grid c2">
        <div class="field" style="grid-column:1 / -1"><label for="t-to">Test addresses (one per line, up to 10; saved for next time)</label><textarea id="t-to" name="test_to" rows="4" required placeholder="you@example.com"><?= e((string) setting('test_recipients', '')) ?></textarea></div>
        <div class="field"><label for="t-kind">Type of business</label><select id="t-kind" name="test_kind"><?php foreach (PROSPECT_KINDS as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="t-org">Business name (optional)</label><input type="text" id="t-org" name="test_org" placeholder="Leave empty for a realistic sample name"></div>
        <div class="field"><label for="t-first">First name (optional)</label><input type="text" id="t-first" name="test_first" placeholder="Megan"></div>
      </div>
      <button class="btn btn-primary btn-sm">Send 3 test emails</button>
    </form>
  </div>

  <div class="card" id="finder">
    <h2><?= icon('search') ?> Add prospects</h2>
    <form method="post" action="<?= e(base_path() . '/super/prospects/add') ?>" class="stack">
      <?= csrf_field() ?>
      <div class="field"><label for="p-lines">Paste one per line: Organization, First name, Email, City, State, Type, Website, Phone</label>
        <textarea id="p-lines" name="lines" rows="4" placeholder="Bright Minds Tutoring, Sarah, sarah@brightminds.com, Draper, UT, tutoring, brightminds.com"></textarea>
        <span class="muted small">Type: clinic, dental, daycare, tutoring, school, office, gym, construction, retail, restaurant or other (it picks the matching email). First name, city, state, type, website and phone may be empty. Copy straight from Excel works too. Public school districts (k12, .gov) are skipped because they block outside email.</span></div>
      <div><button class="btn btn-primary btn-sm">Add prospects</button></div>
    </form>
    <h3 style="margin-top:14px">Daily prospect finder (Claude)</h3>
    <p class="muted small">A daily Claude task finds new prospects on the web and sends them here with this key (header <code>X-Api-Key</code>, <code>POST <?= e(scheme() . '://' . cfg('base_domain') . base_path()) ?>/api/prospects</code>).
      Last finder delivery: <?= e(($x = val("SELECT MAX(created_at) FROM prospects WHERE added_by = 'finder'")) ? date('M j, Y H:i', strtotime($x)) . ' UTC' : 'none yet') ?>.</p>
    <?php if ($newKey): ?>
      <div class="flash success" style="margin:8px 0"><b>Your finder key (shown only now):</b><br><code style="user-select:all;word-break:break-all"><?= e($newKey) ?></code><br><span class="small">Enter it in the secure form Claude shows you. Do not paste it in a chat or email.</span></div>
    <?php endif; ?>
    <form method="post" action="<?= e(base_path() . '/super/prospects/key') ?>" onsubmit="return <?= setting('prospect_api_key_hash', '') ? "confirm('Create a new key? The old key stops working.')" : 'true' ?>">
      <?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= setting('prospect_api_key_hash', '') ? 'Replace finder key' : 'Create finder key' ?></button>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <h2><?= icon('users') ?> Prospects (<?= count($list) ?>)</h2>
      <div class="row">
        <a class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(base_path() . '/super/prospects') ?>">All <span class="muted">(<?= (int) $total ?>)</span></a>
        <?php foreach (PROSPECT_STATUSES as $k => $label): if (empty($counts[$k])) continue; ?>
          <a class="btn btn-sm <?= $status === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(base_path() . '/super/prospects?status=' . $k) ?>"><?= e($label) ?> <span class="muted">(<?= (int) $counts[$k] ?>)</span></a>
        <?php endforeach; ?>
        <a class="btn btn-accent btn-sm" href="<?= e(base_path() . '/super/prospects?export=csv' . ($status !== '' ? '&status=' . urlencode($status) : '')) ?>">Export CSV</a>
      </div>
    </div>
    <div class="table-wrap">
    <table class="table">
      <tr><th>Organization</th><th>Contact</th><th>Emails</th><th>Status &amp; notes</th></tr>
      <?php foreach ($list as $p): ?>
        <tr>
          <td class="small"><b><?= e($p['org']) ?></b><br><?= e(PROSPECT_KINDS[$p['kind']] ?? $p['kind']) ?><?= $p['city'] ? ' · ' . e($p['city']) . ($p['state'] ? ', ' . e($p['state']) : '') : '' ?>
            <?php if ($p['website']): ?><br><a href="<?= e($p['website']) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://(www\.)?#', '', rtrim($p['website'], '/'))) ?></a><?php endif; ?>
            <br><span class="muted">added <?= e(date('M j', strtotime($p['created_at']))) ?> · <?= e($p['added_by']) ?></span></td>
          <td class="small"><?= e((string) $p['first_name']) ?><br><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a><?php if ($p['phone']): ?><br><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $p['phone'])) ?>"><?= e($p['phone']) ?></a><?php endif; ?></td>
          <td class="small nowrap"><span class="badge <?= $badge[$p['status']] ?? 'gray' ?>"><?= e(PROSPECT_STATUSES[$p['status']] ?? $p['status']) ?></span><br><?= (int) $p['step'] ?> of 3 sent
            <?php if ($p['last_sent_at']): ?><br><span class="muted">last <?= e(date('M j', strtotime($p['last_sent_at']))) ?></span><?php endif; ?>
            <?php if ($ls = $lastSend[(int) $p['id']] ?? null): ?><br><span class="muted" title="<?= e((string) $ls['server_reply']) ?>"><?= $ls['outcome'] === 'bounced' ? 'bounced' : ($ls['outcome'] === 'replied' ? 'replied' : '✓ accepted' . ($ls['in_sent'] ? ', in Sent' : '')) ?></span><?php endif; ?>
            <?php if ($p['next_at'] && $p['status'] === 'active'): ?><br><span class="muted">next <?= e(date('M j', strtotime($p['next_at']))) ?></span><?php endif; ?>
            <br><a href="<?= e(base_path() . '/super/prospects/' . $p['id'] . '/preview?step=' . min(2, (int) $p['step'])) ?>" target="_blank">Preview</a></td>
          <td>
            <form method="post" action="<?= e(base_path() . '/super/prospects/' . $p['id']) ?>" class="stack">
              <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($status) ?>">
              <select name="status"><?php foreach (PROSPECT_STATUSES as $k => $label): ?><option value="<?= e($k) ?>" <?= $p['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
              <textarea name="notes" rows="2" placeholder="Notes"><?= e((string) $p['notes']) ?></textarea>
              <button class="btn btn-sm">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="4" class="empty">No prospects yet. Paste some above, or let the daily finder add them.</td></tr><?php endif; ?>
    </table>
    </div>
  </div>
</div>
