<?php $showSchool = $showSchool ?? false; ?>
<div class="table-wrap">
  <table class="table audit-table">
    <tr><th>When</th><?php if ($showSchool): ?><th>School</th><?php endif; ?><th>Who</th><th>Action</th><th>Details</th><th>Result</th></tr>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td class="nowrap small tnum"><?= e(audit_time($l['created_at'])) ?></td>
        <?php if ($showSchool): ?><td class="small"><?= e($l['school'] ?? 'Platform') ?></td><?php endif; ?>
        <td class="small"><b><?= e($l['user_name'] ?: '—') ?></b><?php if ($l['user_email']): ?><br><span class="muted"><?= e($l['user_email']) ?></span><?php endif; ?>
          <?php if ($l['ip']): ?><br><span class="muted">IP <?= e($l['ip']) ?></span><?php endif; ?></td>
        <td><b><?= e($l['action']) ?></b><?php if ($l['target']): ?><br><span class="small"><?= e($l['target']) ?></span><?php endif; ?></td>
        <td class="small audit-details"><?= e($l['details']) ?></td>
        <td><span class="badge <?= $l['result'] === 'success' ? 'in' : 'danger' ?>"><?= $l['result'] === 'success' ? 'Success' : 'Failed' ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="6" class="empty">No entries for these filters.</td></tr><?php endif; ?>
  </table>
</div>
