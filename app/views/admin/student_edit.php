<div class="topbar">
  <div><a class="small" href="<?= e(url('/admin/students')) ?>">← All students</a><h1><?= e(full_name($student)) ?></h1></div>
  <form method="post" action="<?= e(url('/admin/students/' . $student['id'] . '/delete')) ?>" data-confirm="Delete this student and their guardians? Past check-ins stay in the log.">
    <?= csrf_field() ?><button class="btn btn-danger"><?= icon('trash', 16) ?> Delete</button>
  </form>
</div>
<div class="grid c2">
  <div class="card">
    <h3>Student details</h3>
    <form method="post" action="<?= e(url('/admin/students/' . $student['id'])) ?>">
      <?= csrf_field() ?>
      <div class="grid c2">
        <div class="field"><label>First name *</label><input type="text" name="first_name" value="<?= e($student['first_name']) ?>" required></div>
        <div class="field"><label>Last name</label><input type="text" name="last_name" value="<?= e($student['last_name']) ?>"></div>
        <div class="field"><label>Grade / class</label><input type="text" name="grade" value="<?= e($student['grade']) ?>"></div>
        <div class="field"><label>Student ID</label><input type="text" name="student_code" value="<?= e($student['student_code']) ?>"></div>
      </div>
      <div class="field"><label>Status</label><select name="active"><option value="1">Active — shows on kiosk</option><option value="0" <?= $student['active'] ? '' : 'selected' ?>>Inactive — hidden from kiosk</option></select></div>
      <button class="btn btn-primary">Save</button>
    </form>
  </div>
  <div class="card">
    <h3><?= icon('users') ?> Parents / guardians</h3>
    <p class="muted small">Only these people appear on the kiosk when this student is selected.</p>
    <?php foreach ($guardians as $g): ?>
      <div class="person" style="padding:8px 0;border-bottom:1px solid var(--line)">
        <span class="avatar material"><?= e(initials($g['name'])) ?></span>
        <div style="flex:1"><b><?= e($g['name']) ?></b><div class="muted small"><?= e(implode(' · ', array_filter([$g['relationship'], decrypt_pii($g['phone']), decrypt_pii($g['email'])]))) ?></div></div>
        <form method="post" action="<?= e(url('/admin/guardians/' . $g['id'] . '/delete')) ?>" data-confirm="Remove <?= e($g['name']) ?>?"><?= csrf_field() ?><button class="link-btn" title="Remove"><?= icon('trash', 18) ?></button></form>
      </div>
    <?php endforeach; ?>
    <?php if (!$guardians): ?><div class="flash info">No guardians yet — add at least one so the student can sign in.</div><?php endif; ?>
    <form method="post" action="<?= e(url('/admin/students/' . $student['id'] . '/guardians')) ?>" style="margin-top:14px">
      <?= csrf_field() ?>
      <div class="grid c2">
        <div class="field"><label>Name *</label><input type="text" name="name" required></div>
        <div class="field"><label>Relationship</label><input type="text" name="relationship" placeholder="Father, Mother, Grandparent…"></div>
        <div class="field"><label>Phone</label><input type="text" name="phone"></div>
        <div class="field"><label>Email</label><input type="email" name="email"></div>
      </div>
      <button class="btn btn-accent"><?= icon('plus', 16) ?> Add guardian</button>
    </form>
  </div>
</div>
<div class="card" style="margin-top:18px">
  <h3><?= icon('clock') ?> Recent activity</h3>
  <?php if (!$history): ?><div class="empty">No check-ins yet.</div><?php endif; ?>
  <?php foreach ($history as $r): require APP_DIR . '/views/partials/event_row.php'; endforeach; ?>
</div>
