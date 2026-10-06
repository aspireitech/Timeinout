<div class="card" style="margin-bottom:60px">
  <div class="card-head">
    <h2><?= icon('globe') ?> Subscribers (<?= count($tenants) ?>)</h2>
    <a class="btn btn-primary btn-sm" href="<?= e(base_path() . '/signup') ?>"><?= icon('plus', 16) ?> New portal</a>
  </div>
  <div class="table-wrap">
  <table class="table">
    <tr><th>Organization</th><th>Owner</th><th>People</th><th>Activity 30d</th><th>Created</th><th>Plan &amp; status</th></tr>
    <?php foreach ($tenants as $t): ?>
      <tr>
        <td><b><?= e($t['name']) ?></b><br><a class="small" href="<?= e(tenant_url($t, '/admin')) ?>" target="_blank"><?= e($t['slug'] . '.' . cfg('base_domain')) ?></a>
          <?php if ($t['custom_domain']): ?><br><span class="small muted"><?= e($t['custom_domain']) ?></span><?php endif; ?></td>
        <td class="small"><?= e($t['owner_email']) ?></td>
        <td class="small"><?= (int) $t['students'] ?> students<br><?= (int) $t['teachers'] ?> teachers</td>
        <td><?= (int) $t['events30'] ?></td>
        <td class="small nowrap"><?= e(date('M j, Y', strtotime($t['created_at']))) ?>
          <?php if ($t['status'] === 'trial'): ?><br><span class="muted">trial ends <?= e(date('M j', strtotime((string) $t['trial_ends_at']))) ?></span><?php endif; ?></td>
        <td>
          <form method="post" action="<?= e(base_path() . '/super/tenants/' . $t['id']) ?>" class="inline-form">
            <?= csrf_field() ?>
            <select name="plan" style="width:auto"><?php foreach (cfg('plans') as $k => $p): ?><option value="<?= e($k) ?>" <?= $t['plan'] === $k ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select>
            <select name="status" style="width:auto"><?php foreach (['trial', 'active', 'suspended', 'cancelled'] as $s): ?><option <?= $t['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
            <button class="btn btn-sm">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$tenants): ?><tr><td colspan="6" class="empty">No subscribers yet.</td></tr><?php endif; ?>
  </table>
  </div>
</div>
