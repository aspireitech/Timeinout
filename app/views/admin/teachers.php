<div class="topbar">
  <div><h1><?= e(term('b2')) ?></h1><div class="muted"><?= count($teachers) ?> shown</div></div>
  <div class="row">
    <a class="btn btn-ghost" href="<?= e(url('/admin/import')) ?>"><?= icon('upload') ?> Import CSV</a>
    <button class="btn btn-primary" onclick="document.getElementById('add').classList.toggle('hidden')"><?= icon('plus') ?> Add <?= e(strtolower(term('b1'))) ?></button>
  </div>
</div>
<div class="card hidden" id="add" style="margin-bottom:18px">
  <h3>New <?= e(strtolower(term('b1'))) ?></h3>
  <form method="post" action="<?= e(url('/admin/teachers')) ?>">
    <?= csrf_field() ?>
    <div class="grid c3">
      <div class="field"><label>First name *</label><input type="text" name="first_name" required></div>
      <div class="field"><label>Last name</label><input type="text" name="last_name"></div>
      <div class="field"><label>Employee ID</label><input type="text" name="employee_code"></div>
      <div class="field"><label>Email</label><input type="email" name="email"></div>
      <div class="field"><label>Phone</label><input type="text" name="phone"></div>
    </div>
    <button class="btn btn-primary">Save</button>
  </form>
</div>
<div class="card">
  <form class="inline-form" style="margin-bottom:14px" method="get">
    <div class="field"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or ID…"></div>
    <button class="btn"><?= icon('search', 16) ?> Search</button>
  </form>
  <div class="table-wrap">
    <table class="table">
      <tr><th>Name</th><th>Employee ID</th><th>Contact</th><th>Status</th><th></th></tr>
      <?php foreach ($teachers as $t): ?>
        <tr>
          <td><div class="person"><span class="avatar teacher"><?= e(initials(full_name($t))) ?></span><a href="<?= e(url('/admin/teachers/' . $t['id'])) ?>"><b><?= e(full_name($t)) ?></b></a></div></td>
          <td><?= e($t['employee_code']) ?></td>
          <td class="small"><?= e(decrypt_pii($t['email'])) ?><br><?= e(decrypt_pii($t['phone'])) ?></td>
          <td><?= $t['active'] ? '<span class="badge in">Active</span>' : '<span class="badge gray">Inactive</span>' ?></td>
          <td class="right"><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/teachers/' . $t['id'])) ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$teachers): ?><tr><td colspan="5" class="empty">No teachers yet.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
