<div class="card" style="margin-bottom:60px">
  <div class="card-head">
    <h2><?= icon('users') ?> Leads (<?= count($leads) ?>)</h2>
    <div class="row">
      <a class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(base_path() . '/super/leads') ?>">All</a>
      <?php foreach (LEAD_STATUSES as $k => $label): ?>
        <a class="btn btn-sm <?= $status === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(base_path() . '/super/leads?status=' . $k) ?>"><?= e($label) ?> <span class="muted">(<?= (int) ($counts[$k] ?? 0) ?>)</span></a>
      <?php endforeach; ?>
      <a class="btn btn-accent btn-sm" href="<?= e(base_path() . '/super/leads?export=csv' . ($status !== '' ? '&status=' . urlencode($status) : '')) ?>">Export CSV</a>
    </div>
  </div>
  <div class="table-wrap">
  <table class="table">
    <tr><th>Received</th><th>Contact</th><th>Organization</th><th>Message</th><th>Status &amp; notes</th></tr>
    <?php foreach ($leads as $l): ?>
      <tr>
        <td class="small nowrap"><?= e(date('M j, Y', strtotime($l['created_at']))) ?><br><span class="muted"><?= e(date('H:i', strtotime($l['created_at']))) ?> UTC</span>
          <?php if (!$l['emailed']): ?><br><span class="badge warn">email not sent</span><?php endif; ?></td>
        <td class="small"><b><?= e($l['name']) ?></b><br><a href="mailto:<?= e($l['email']) ?>"><?= e($l['email']) ?></a><br><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $l['phone'])) ?>"><?= e($l['phone']) ?></a></td>
        <td class="small"><b><?= e($l['company']) ?></b><br><?= e(industry((string) $l['industry'])['name']) ?><br><span class="muted"><?= e(EMPLOYEE_RANGES[$l['employees']] ?? $l['employees']) ?> people</span>
          <?php if (!empty($l['source'])): ?><br><span class="badge student">via <?= e($l['source']) ?></span><?php endif; ?></td>
        <td class="small" style="max-width:280px"><?= nl2br(e((string) $l['message'])) ?: '<span class="muted">—</span>' ?></td>
        <td>
          <form method="post" action="<?= e(base_path() . '/super/leads/' . $l['id']) ?>" class="stack">
            <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($status) ?>">
            <select name="status"><?php foreach (LEAD_STATUSES as $k => $label): ?><option value="<?= e($k) ?>" <?= $l['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            <textarea name="notes" rows="2" placeholder="Notes"><?= e((string) $l['notes']) ?></textarea>
            <button class="btn btn-sm">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$leads): ?><tr><td colspan="5" class="empty">No leads yet. They appear here when someone fills in the demo form at <?= e(base_path() . '/demo') ?>.</td></tr><?php endif; ?>
  </table>
  </div>
</div>
