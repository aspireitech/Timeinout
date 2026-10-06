<div class="topbar"><div><h1>Materials</h1><div class="muted">Items parents can pick up from the kiosk.</div></div></div>
<div class="card" style="max-width:720px">
  <form method="post" class="inline-form" style="margin-bottom:18px">
    <?= csrf_field() ?>
    <div class="field"><label>New item</label><input type="text" name="name" placeholder="e.g. Report card" required></div>
    <button class="btn btn-primary"><?= icon('plus', 16) ?> Add</button>
  </form>
  <table class="table">
    <?php foreach ($materials as $m): ?>
      <tr>
        <td><span class="avatar material" style="width:30px;height:30px;border-radius:9px"><?= icon('box', 16) ?></span> <b style="margin-left:8px"><?= e($m['name']) ?></b></td>
        <td><?= $m['active'] ? '<span class="badge in">Shown</span>' : '<span class="badge gray">Hidden</span>' ?></td>
        <td class="right nowrap">
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="toggle" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-ghost"><?= $m['active'] ? 'Hide' : 'Show' ?></button></form>
          <form method="post" action="<?= e(url('/admin/materials/' . $m['id'] . '/delete')) ?>" style="display:inline" data-confirm="Delete this item?"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= icon('trash', 14) ?></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$materials): ?><tr><td class="empty">No items yet.</td></tr><?php endif; ?>
  </table>
</div>
