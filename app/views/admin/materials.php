<div class="topbar"><div><h1>Pickup <?= e(strtolower(term('items'))) ?></h1><div class="muted">Items that can be picked up at the kiosk.</div></div></div>
<div class="card" style="max-width:720px">
  <form method="post" class="inline-form" style="margin-bottom:18px">
    <?= csrf_field() ?>
    <div class="field"><label>New item</label><input type="text" name="name" placeholder="e.g. Report card" required></div>
    <button class="btn btn-primary"><?= icon('plus', 16) ?> Add</button>
  </form>
  <?php $shown = array_values(array_filter($materials, fn($m) => $m['active'])); $hidden = array_values(array_filter($materials, fn($m) => !$m['active'])); ?>
  <?php $row = function (array $m) { ?>
      <tr>
        <td><span class="avatar material" style="width:30px;height:30px;border-radius:9px"><?= icon('box', 16) ?></span> <b style="margin-left:8px"><?= e($m['name']) ?></b></td>
        <td class="right nowrap">
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="toggle" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm <?= $m['active'] ? 'btn-ghost' : 'btn-primary' ?>"><?= $m['active'] ? 'Hide from kiosk' : 'Show on kiosk' ?></button></form>
          <form method="post" action="<?= e(url('/admin/materials/' . $m['id'] . '/delete')) ?>" style="display:inline" data-confirm="Delete this item?"><?= csrf_field() ?><button class="btn btn-sm btn-danger" aria-label="Delete <?= e($m['name']) ?>"><?= icon('trash', 14) ?></button></form>
        </td>
      </tr>
  <?php }; ?>
  <h3 style="margin:0 0 6px">On the kiosk <span class="badge in"><?= count($shown) ?></span></h3>
  <p class="muted small" style="margin:0 0 8px">Only these items appear when someone taps the pickup button.</p>
  <table class="table">
    <?php foreach ($shown as $m) { $row($m); } ?>
    <?php if (!$shown): ?><tr><td class="empty">No items on the kiosk. Add one above, or show a hidden one below.</td></tr><?php endif; ?>
  </table>
  <?php if ($hidden): ?>
    <details class="hidden-items" style="margin-top:18px">
      <summary class="small"><b>Hidden from the kiosk (<?= count($hidden) ?>)</b> <span class="muted">· tap to show</span></summary>
      <table class="table" style="margin-top:8px"><?php foreach ($hidden as $m) { $row($m); } ?></table>
    </details>
  <?php endif; ?>
</div>
