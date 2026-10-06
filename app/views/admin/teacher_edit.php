<div class="topbar">
  <div><a class="small" href="<?= e(url('/admin/teachers')) ?>">← All teachers</a><h1><?= e(full_name($teacher)) ?></h1></div>
  <form method="post" action="<?= e(url('/admin/teachers/' . $teacher['id'] . '/delete')) ?>" data-confirm="Delete this teacher? Past check-ins stay in the log.">
    <?= csrf_field() ?><button class="btn btn-danger"><?= icon('trash', 16) ?> Delete</button>
  </form>
</div>
<div class="grid c2">
  <div class="card">
    <form method="post" action="<?= e(url('/admin/teachers/' . $teacher['id'])) ?>">
      <?= csrf_field() ?>
      <div class="grid c2">
        <div class="field"><label>First name *</label><input type="text" name="first_name" value="<?= e($teacher['first_name']) ?>" required></div>
        <div class="field"><label>Last name</label><input type="text" name="last_name" value="<?= e($teacher['last_name']) ?>"></div>
        <div class="field"><label>Employee ID</label><input type="text" name="employee_code" value="<?= e($teacher['employee_code']) ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($teacher['email']) ?>"></div>
        <div class="field"><label>Phone</label><input type="text" name="phone" value="<?= e($teacher['phone']) ?>"></div>
        <div class="field"><label>Status</label><select name="active"><option value="1">Active</option><option value="0" <?= $teacher['active'] ? '' : 'selected' ?>>Inactive</option></select></div>
      </div>
      <button class="btn btn-primary">Save</button>
    </form>
  </div>
  <div class="card">
    <h3><?= icon('clock') ?> Recent activity</h3>
    <?php if (!$history): ?><div class="empty">No check-ins yet.</div><?php endif; ?>
    <?php foreach ($history as $r): require APP_DIR . '/views/partials/event_row.php'; endforeach; ?>
  </div>
</div>
