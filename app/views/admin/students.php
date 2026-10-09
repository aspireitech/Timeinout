<div class="topbar">
  <div><h1><?= e(term('a2')) ?><?= uses_contacts() ? ' &amp; ' . e(strtolower(term('c2'))) : '' ?></h1><div class="muted"><?= count($students) ?> shown</div></div>
  <div class="row">
    <a class="btn btn-ghost" href="<?= e(url('/admin/import')) ?>"><?= icon('upload') ?> Import CSV</a>
    <button class="btn btn-primary" onclick="document.getElementById('add').classList.toggle('hidden')"><?= icon('plus') ?> Add <?= e(strtolower(term('a1'))) ?></button>
  </div>
</div>

<div class="card hidden" id="add" style="margin-bottom:18px">
  <h3>New <?= e(strtolower(term('a1'))) ?></h3>
  <form method="post" action="<?= e(url('/admin/students')) ?>">
    <?= csrf_field() ?>
    <div class="grid c4">
      <div class="field"><label>First name *</label><input type="text" name="first_name" required></div>
      <div class="field"><label>Last name</label><input type="text" name="last_name"></div>
      <div class="field"><label>Grade / group</label><input type="text" name="grade" placeholder="Grade 2"></div>
      <div class="field"><label>ID number</label><input type="text" name="student_code" placeholder="Optional"></div>
    </div>
    <?php if (uses_contacts()) foreach ([1 => 'Father', 2 => 'Mother'] as $i => $rel): ?>
      <div class="grid c3">
        <div class="field"><label><?= e(term('c1')) ?> <?= $i ?> name</label><input type="text" name="g<?= $i ?>_name"></div>
        <div class="field"><label>Relationship</label><input type="text" name="g<?= $i ?>_rel" placeholder="<?= $rel ?>"></div>
        <div class="field"><label>Phone</label><input type="text" name="g<?= $i ?>_phone"></div>
      </div>
    <?php endforeach; ?>
    <button class="btn btn-primary">Save student</button>
  </form>
</div>

<div class="card">
  <form class="inline-form" style="margin-bottom:14px" method="get">
    <div class="field"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or ID…"></div>
    <div class="field" style="flex:0 1 180px"><select name="grade"><option value="">All groups</option><?php foreach ($grades as $g): ?><option <?= $g === $grade ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?></select></div>
    <button class="btn"><?= icon('search', 16) ?> Search</button>
  </form>
  <div class="table-wrap">
    <table class="table">
      <tr><th><?= e(term('a1')) ?></th><th>Grade / group</th><th><?= e(term('c2')) ?></th><th>Status</th><th></th></tr>
      <?php foreach ($students as $s): ?>
        <tr>
          <td><div class="person"><span class="avatar"><?= e(initials(full_name($s))) ?></span><div><a href="<?= e(url('/admin/students/' . $s['id'])) ?>"><b><?= e(full_name($s)) ?></b></a><?php if ($s['student_code']): ?><div class="muted small">ID <?= e($s['student_code']) ?></div><?php endif; ?></div></div></td>
          <td><?= e($s['grade']) ?></td>
          <td><?php foreach (array_filter(explode('||', (string) $s['guardian_list'])) as $g): ?><span class="chip"><?= e($g) ?></span><?php endforeach; ?>
            <?php if (!$s['guardian_list']): ?><span class="badge warn">No guardian</span><?php endif; ?></td>
          <td><?= $s['active'] ? '<span class="badge in">Active</span>' : '<span class="badge gray">Inactive</span>' ?></td>
          <td class="right"><a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/students/' . $s['id'])) ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$students): ?><tr><td colspan="5" class="empty">No students yet. Add one above or import a CSV file.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
