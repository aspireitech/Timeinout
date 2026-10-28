<div class="card" style="margin-bottom:60px">
  <div class="card-head">
    <h2><?= icon('spark') ?> Feature requests (<?= count($requests) ?>)</h2>
    <div class="row">
      <a class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(base_path() . '/super/requests') ?>">All</a>
      <?php foreach (FEATURE_STATUSES as $k => $label): ?>
        <a class="btn btn-sm <?= $status === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(base_path() . '/super/requests?status=' . $k) ?>"><?= e($label) ?> <span class="muted">(<?= (int) ($counts[$k] ?? 0) ?>)</span></a>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="muted small">Requests come from customers' Admin → What's new. The status and your reply are shown to the customer. Edit the public roadmap in app/lib/whatsnew.php.</p>
  <div class="table-wrap"><table class="table">
    <tr><th>Request</th><th>From</th><th>Details</th><th>Status &amp; reply (visible to the customer)</th></tr>
    <?php foreach ($requests as $r): ?>
      <tr>
        <td><b><?= e($r['title']) ?></b><br><span class="small muted">#<?= (int) $r['id'] ?> · <?= e(FEATURE_CATEGORIES[$r['category']] ?? '') ?><br><?= e(FEATURE_PRIORITIES[$r['priority']] ?? '') ?><?= $r['people'] ? ' · ' . e($r['people']) : '' ?></span></td>
        <td class="small"><b><?= e((string) $r['portal']) ?></b><br><?= e((string) $r['user_name']) ?><br><a href="mailto:<?= e((string) $r['user_email']) ?>"><?= e((string) $r['user_email']) ?></a><br><span class="muted"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></span></td>
        <td class="small" style="max-width:340px"><?= nl2br(e($r['details'])) ?><?php if ($r['problem']): ?><br><span class="muted">Problem: <?= nl2br(e($r['problem'])) ?></span><?php endif; ?></td>
        <td><form method="post" action="<?= e(base_path() . '/super/requests/' . $r['id']) ?>" class="stack">
          <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($status) ?>">
          <select name="status"><?php foreach (FEATURE_STATUSES as $k => $label): ?><option value="<?= e($k) ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
          <textarea name="reply" rows="2" placeholder="Reply to the customer"><?= e((string) $r['reply']) ?></textarea>
          <button class="btn btn-sm">Save</button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$requests): ?><tr><td colspan="4" class="empty">No feature requests yet.</td></tr><?php endif; ?>
  </table></div>
</div>
