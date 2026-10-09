<?php
$typeHelp = ['members' => 'People on your ' . term('a2') . ' list sign in and out', 'staff' => 'People on your ' . term('b2') . ' list sign in and out',
    'pickup' => 'Record ' . strtolower(term('items')) . ' collected for someone', 'visitor' => 'Guests type their own name'];
$colorField = function (string $name, string $value): string {
    $h = '<div class="swatches">';
    foreach (TILE_COLORS as $k => [$a, $b]) {
        $h .= '<label class="sw" title="' . e(ucfirst($k)) . '"><input type="radio" name="' . e($name) . '" value="' . e($k) . '"' . ($k === $value ? ' checked' : '') . '><i style="background:linear-gradient(135deg,' . $a . ',' . $b . ')"></i></label>';
    }
    return $h . '</div>';
};
$iconField = function (string $value): string {
    $h = '<div class="icon-pick">';
    foreach (TILE_ICONS as $i) {
        $h .= '<label title="' . e($i) . '"><input type="radio" name="icon" value="' . e($i) . '"' . ($i === $value ? ' checked' : '') . '><span>' . icon($i, 20) . '</span></label>';
    }
    return $h . '</div>';
};
?>
<div class="topbar">
  <div><h1>Kiosk tiles</h1><div class="muted">The buttons people tap on the kiosk. Rename, recolor, reorder, hide or add them.</div></div>
  <a class="btn btn-ghost" href="<?= e(url('/')) ?>" target="_blank"><?= icon('kiosk') ?> Preview kiosk</a>
</div>

<div class="tile-preview">
  <?php foreach ($tiles as $tl): if (!$tl['active']) continue; ?>
    <div class="mini-tile" style="background:<?= e(tile_gradient($tl['color'])) ?>"><?= icon($tl['icon'], 22) ?><b><?= e($tl['label']) ?></b></div>
  <?php endforeach; ?>
</div>

<div class="stack">
  <?php foreach ($tiles as $n => $tl): ?>
    <details class="card tile-row" <?= $tl['active'] ? '' : 'data-off' ?>>
      <summary>
        <span class="mini-ic" style="background:<?= e(tile_gradient($tl['color'])) ?>"><?= icon($tl['icon'], 20) ?></span>
        <span class="tile-name"><b><?= e($tl['label']) ?></b><span class="muted small"><?= e(TILE_TYPES[$tl['type']]) ?><?= $tl['needs_contact'] ? ' · asks for ' . e(strtolower(term('c1'))) : '' ?></span></span>
        <?= $tl['active'] ? '<span class="badge in">Shown</span>' : '<span class="badge gray">Hidden</span>' ?>
        <span class="tile-moves">
          <?php if ($n > 0): ?><form method="post" action="<?= e(url('/admin/tiles/' . $tl['id'] . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="dir" value="up"><button class="btn btn-sm btn-ghost" aria-label="Move <?= e($tl['label']) ?> up">↑</button></form><?php endif; ?>
          <?php if ($n < count($tiles) - 1): ?><form method="post" action="<?= e(url('/admin/tiles/' . $tl['id'] . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="dir" value="down"><button class="btn btn-sm btn-ghost" aria-label="Move <?= e($tl['label']) ?> down">↓</button></form><?php endif; ?>
        </span>
        <span class="btn btn-sm">Edit</span>
      </summary>
      <form method="post" action="<?= e(url('/admin/tiles')) ?>" class="tile-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $tl['id'] ?>">
        <div class="grid c2">
          <div class="field"><label for="tl-<?= $tl['id'] ?>">Button name</label><input type="text" id="tl-<?= $tl['id'] ?>" name="label" value="<?= e($tl['label']) ?>" maxlength="40" required></div>
          <div class="field"><label for="ts-<?= $tl['id'] ?>">Small text under it</label><input type="text" id="ts-<?= $tl['id'] ?>" name="subtitle" value="<?= e($tl['subtitle']) ?>" maxlength="120"></div>
        </div>
        <div class="field"><label>Color</label><?= $colorField('color', $tl['color']) ?></div>
        <div class="field"><label>Icon</label><?= $iconField($tl['icon']) ?></div>
        <div class="row">
          <?php if (in_array($tl['type'], ['members', 'pickup'], true)): ?>
            <label class="check"><input type="checkbox" name="needs_contact" value="1" <?= $tl['needs_contact'] ? 'checked' : '' ?>> Ask who is with them (<?= e(strtolower(term('c1'))) ?>)</label>
          <?php endif; ?>
          <input type="hidden" name="active" value="0"><!-- sent when the box below is unticked -->
          <label class="check"><input type="checkbox" name="active" value="1" <?= $tl['active'] ? 'checked' : '' ?>> Show on the kiosk</label>
        </div>
        <div class="row" style="margin-top:12px">
          <button class="btn btn-primary">Save tile</button>
        </div>
      </form>
      <form method="post" action="<?= e(url('/admin/tiles/' . $tl['id'] . '/delete')) ?>" data-confirm="Remove the <?= e($tl['label']) ?> tile?" style="margin-top:8px"><?= csrf_field() ?><button class="link-btn"><?= icon('trash', 14) ?> Remove this tile</button></form>
    </details>
  <?php endforeach; ?>

  <details class="card" <?= $tiles ? '' : 'open' ?>>
    <summary class="btn btn-primary" style="display:inline-flex"><?= icon('plus', 16) ?> Add a tile</summary>
    <form method="post" action="<?= e(url('/admin/tiles')) ?>" class="tile-form" style="margin-top:14px">
      <?= csrf_field() ?>
      <div class="field"><label>What should it do?</label>
        <div class="type-pick">
          <?php foreach (TILE_TYPES as $k => $label): ?>
            <label><input type="radio" name="type" value="<?= e($k) ?>" <?= $k === 'members' ? 'checked' : '' ?>><span><b><?= e($label) ?></b><small><?= e($typeHelp[$k]) ?></small></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="grid c2">
        <div class="field"><label for="nt-label">Button name</label><input type="text" id="nt-label" name="label" maxlength="40" required placeholder="e.g. Contractor"></div>
        <div class="field"><label for="nt-sub">Small text under it</label><input type="text" id="nt-sub" name="subtitle" maxlength="120" placeholder="e.g. Sign in before entering the site"></div>
      </div>
      <div class="field"><label>Color</label><?= $colorField('color', 'blue') ?></div>
      <div class="field"><label>Icon</label><?= $iconField('badge') ?></div>
      <label class="check"><input type="checkbox" name="needs_contact" value="1"> Ask who is with them (for sign-in and pickup tiles)</label>
      <input type="hidden" name="active" value="1">
      <div style="margin-top:12px"><button class="btn btn-primary">Add tile</button></div>
    </form>
  </details>
</div>

<div class="grid c2" style="margin-top:18px">
  <div class="card">
    <h3><?= icon('settings') ?> Names used in the app</h3>
    <p class="muted small">These words appear in menus, reports and the kiosk.</p>
    <form method="post" action="<?= e(url('/admin/terms')) ?>">
      <?= csrf_field() ?>
      <div class="grid c2">
        <div class="field"><label for="tm-a1">Main list, one person</label><input type="text" id="tm-a1" name="a1" value="<?= e(term('a1')) ?>"></div>
        <div class="field"><label for="tm-a2">Main list, many</label><input type="text" id="tm-a2" name="a2" value="<?= e(term('a2')) ?>"></div>
        <div class="field"><label for="tm-c1">Contact, one</label><input type="text" id="tm-c1" name="c1" value="<?= e(term('c1')) ?>"></div>
        <div class="field"><label for="tm-c2">Contacts, many</label><input type="text" id="tm-c2" name="c2" value="<?= e(term('c2')) ?>"></div>
        <div class="field"><label for="tm-b1">Staff list, one person</label><input type="text" id="tm-b1" name="b1" value="<?= e(term('b1')) ?>"></div>
        <div class="field"><label for="tm-b2">Staff list, many</label><input type="text" id="tm-b2" name="b2" value="<?= e(term('b2')) ?>"></div>
        <div class="field"><label for="tm-items">Pickup items</label><input type="text" id="tm-items" name="items" value="<?= e(term('items')) ?>"></div>
      </div>
      <button class="btn btn-primary">Save names</button>
    </form>
  </div>
  <div class="card" style="align-self:start">
    <h3><?= icon('grid') ?> Industry</h3>
    <p class="muted small">Currently <b><?= e($ind['name']) ?></b>. Switching replaces all tiles and names with that industry's defaults. People and history are not changed.</p>
    <form method="post" action="<?= e(url('/admin/tiles/reset')) ?>" data-confirm="Replace all kiosk tiles and names with the industry defaults?" class="inline-form">
      <?= csrf_field() ?>
      <div class="field"><label for="ind-pick">Industry</label><select id="ind-pick" name="industry"><?php foreach (industries() as $k => $i): ?><option value="<?= e($k) ?>" <?= ($t = tenant())['industry'] === $k ? 'selected' : '' ?>><?= e($i['name']) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-ghost">Reset to defaults</button>
    </form>
  </div>
</div>
