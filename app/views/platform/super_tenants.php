<?php $deleted = array_values(array_filter($tenants, fn($x) => !empty($x['deleted_at']))); $tenants = array_values(array_filter($tenants, fn($x) => empty($x['deleted_at']))); ?>
<div class="card" style="margin-bottom:20px">
  <div class="card-head">
    <h2><?= icon('globe') ?> Subscribers (<?= count($tenants) ?>)</h2>
    <div class="row">
      <form method="post" action="<?= e(base_path() . '/super/demo') ?>" data-confirm="Build the demo portal? Any existing demo data is replaced.">
        <?= csrf_field() ?><button class="btn btn-accent btn-sm"><?= icon('users', 16) ?> <?= demo_tenant() ? 'Reset demo portal' : 'Load demo portal' ?></button>
      </form>
      <a class="btn btn-primary btn-sm" href="<?= e(base_path() . '/super/portals/new') ?>"><?= icon('plus', 16) ?> New portal</a>
    </div>
  </div>
  <div class="table-wrap">
  <table class="table">
    <tr><th>Organization</th><th>Owner</th><th>People</th><th>Activity 30d</th><th>Billing</th><th>Plan &amp; status</th></tr>
    <?php foreach ($tenants as $t): ?>
      <tr>
        <td><b><?= e($t['name']) ?></b><br><a class="small" href="<?= e(tenant_url($t, '/admin')) ?>" target="_blank"><?= e(preg_replace('#^https?://#', '', tenant_url($t, ''))) ?></a>
          <br><span class="badge <?= tenant_db_config($t) ? 'in' : 'student' ?>"><?= tenant_db_config($t) ? 'Own DB · ' . e($t['db_name']) : 'Shared DB' ?></span>
          <?php if (!empty($t['db_error'])): ?><br><span class="badge danger" title="<?= e($t['db_error']) ?>">database not reachable</span><?php endif; ?>
          <?php if ($t['custom_domain']): ?><br><span class="small muted"><?= e($t['custom_domain']) ?></span><?php endif; ?></td>
        <td class="small"><?= e($t['owner_email']) ?></td>
        <td class="small"><?= (int) $t['students'] ?> students<br><?= (int) $t['teachers'] ?> teachers</td>
        <td><?= (int) $t['events30'] ?></td>
        <td class="small"><?php $bs = billing_state($t); ?>
          <span class="badge <?= ['active' => 'in', 'trial' => 'student', 'grace' => 'warn', 'expired' => 'danger', 'suspended' => 'danger'][$bs] ?>"><?= e(['active' => 'Active', 'trial' => 'Trial', 'grace' => 'Trial ended', 'expired' => 'Expired', 'suspended' => 'Suspended'][$bs]) ?></span>
          <?php if ($t['billing_provider']): ?><br><?= e(ucfirst($t['billing_provider'])) ?><?= $t['subscription_status'] && $t['subscription_status'] !== 'active' ? ' · ' . e($t['subscription_status']) : '' ?><?php endif; ?>
          <?php if ($bs === 'trial'): ?><br><span class="muted">ends <?= e(date('M j', strtotime((string) $t['trial_ends_at']))) ?></span><?php elseif ($t['current_period_end']): ?><br><span class="muted">renews <?= e(date('M j', strtotime($t['current_period_end']))) ?></span><?php endif; ?>
          <br><a class="small" href="<?= e(base_path() . '/super/payments?tenant=' . $t['id']) ?>"><?= e(money((int) $t['paid_total'])) ?> paid</a>
          <br><span class="muted"><?= e(industry($t['industry'] ?? 'school')['name']) ?> · since <?= e(date('M Y', strtotime($t['created_at']))) ?></span></td>
        <td>
          <form method="post" action="<?= e(base_path() . '/super/tenants/' . $t['id']) ?>" class="inline-form">
            <?= csrf_field() ?>
            <select name="plan" style="width:auto"><?php foreach (plans() as $k => $p): ?><option value="<?= e($k) ?>" <?= $t['plan'] === $k ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select>
            <select name="status" style="width:auto"><?php foreach (['trial', 'active', 'suspended', 'cancelled'] as $s): ?><option <?= $t['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
            <button class="btn btn-sm">Save</button>
          </form>
          <form method="post" action="<?= e(base_path() . '/super/tenants/' . $t['id'] . '/password') ?>" style="margin-top:6px" data-confirm="Give the owner of <?= e($t['name']) ?> a new temporary password? Their old password stops working.">
            <?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= icon('shield', 14) ?> Reset owner password</button>
          </form>
          <form method="get" action="<?= e(base_path() . '/super/tenants/' . $t['id'] . '/delete') ?>" style="margin-top:6px" data-confirm="Are you going to delete <?= e($t['name']) ?>? You will be asked to type its address and your password next.">
            <button class="btn btn-danger btn-sm"><?= icon('shield', 14) ?> Delete portal</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$tenants): ?><tr><td colspan="6" class="empty">No subscribers yet.</td></tr><?php endif; ?>
  </table>
  </div>
</div>
<?php if ($deleted): ?>
<div class="card" style="margin-bottom:60px">
  <h2><?= icon('shield') ?> Deleted portals (<?= count($deleted) ?>)</h2>
  <p class="muted small">Closed and kept for <?= DELETE_GRACE_DAYS ?> days, then removed automatically by the hourly job.</p>
  <div class="table-wrap"><table class="table">
    <tr><th>Organization</th><th>Deleted</th><th>Removed for good</th><th></th></tr>
    <?php foreach ($deleted as $t): $purge = strtotime($t['deleted_at'] . ' UTC') + DELETE_GRACE_DAYS * 86400; ?>
      <tr>
        <td><b><?= e($t['name']) ?></b><br><span class="small muted"><?= e($t['slug']) ?> · <?= tenant_db_config($t) ? 'Own DB ' . e($t['db_name']) : 'Shared DB' ?></span></td>
        <td class="small"><?= e(date('M j, Y', strtotime($t['deleted_at'] . ' UTC'))) ?></td>
        <td class="small"><?= e(date('M j, Y', $purge)) ?><br><span class="muted">in <?= max(0, (int) ceil(($purge - time()) / 86400)) ?> days</span></td>
        <td class="row">
          <form method="post" action="<?= e(base_path() . '/super/tenants/' . $t['id'] . '/restore') ?>" data-confirm="Restore <?= e($t['name']) ?> and open it again?">
            <?= csrf_field() ?><button class="btn btn-accent btn-sm">Restore</button></form>
          <form method="get" action="<?= e(base_path() . '/super/tenants/' . $t['id'] . '/delete') ?>" data-confirm="Permanently remove <?= e($t['name']) ?> now? You will be asked to type its address and your password next.">
            <button class="btn btn-danger btn-sm">Remove permanently now</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
