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
    <?php if (setting('cron_last_log', '') !== ''): ?><details style="margin:0 0 10px"><summary class="small">Last hourly job output</summary><pre class="small" style="white-space:pre-wrap;max-height:220px;overflow:auto"><?= e((string) setting('cron_last_log')) ?></pre></details><?php endif; ?>
    <p class="muted small">Each prospect gets <b>email 1</b>, a <b>follow-up 4 days later</b> and a <b>last note 5 days after that</b>, on weekdays between 8:00 and 16:00, spread over the day.
      Emails stop by themselves when the prospect signs up, books a demo or unsubscribes. When someone replies, set them to <b>Replied</b> below so they get no more automatic emails.
      Sent from your contact mailbox, so replies come to you.</p>
    <form method="post" action="<?= e(base_path() . '/super/prospects/settings') ?>">
      <?= csrf_field() ?>
      <div class="grid c2">
        <label class="check" style="grid-column:1 / -1"><input type="checkbox" name="prospects_on" value="1" <?= $on ? 'checked' : '' ?>>
          <span>Send outreach emails automatically<?php if ($problem): ?><br><span class="small" style="color:#B4232A"><?= e($problem) ?></span><?php endif; ?></span></label>
        <div class="field"><label for="o-name">Your name (signature)</label><input type="text" id="o-name" name="outreach_name" value="<?= e(setting('outreach_name', '')) ?>" placeholder="e.g. Alex Morgan"></div>
        <div class="field"><label for="o-phone">Phone (signature, optional)</label><input type="text" id="o-phone" name="outreach_phone" value="<?= e(setting('outreach_phone', '')) ?>" placeholder="+1 (555) 123-4567"></div>
        <div class="field" style="grid-column:1 / -1"><label for="o-addr">Business postal address (required by US law in sales emails)</label><textarea id="o-addr" name="outreach_address" rows="3" placeholder="Company name&#10;Street&#10;City, State ZIP"><?= e(setting('outreach_address', '')) ?></textarea></div>
        <div class="field"><label for="o-cap">Emails per day (all steps together)</label><input type="number" id="o-cap" name="prospects_daily" min="1" max="100" value="<?= e(setting('prospects_daily', '20')) ?>">
          <span class="muted small">Start with 20. A new mailbox that sends too many sales emails lands in spam.</span></div>
        <div class="field"><label for="o-tz">Sending hours follow this time zone</label><input type="text" id="o-tz" name="outreach_tz" value="<?= e(setting('outreach_tz', 'America/Denver')) ?>"></div>
      </div>
      <button class="btn btn-primary btn-sm">Save outreach settings</button>
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
