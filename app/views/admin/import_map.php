<?php $guardianCols = array_keys(array_filter($map, fn($m) => $m[0] === 'guardian')); ?>
<div class="topbar"><div><a class="small" href="<?= e(url('/admin/import?')) ?>">← Import</a>
  <h1>Match your columns</h1>
  <div class="muted"><?= e($fileName) ?> · <?= (int) $count ?> row<?= $count === 1 ? '' : 's' ?> · <?= $type === 'teachers' ? e(term('b2')) : e(term('a2')) ?></div></div></div>
<?php $typeName = fn($t) => $t === 'teachers' ? term('b2') : term('a2'); $other = $type === 'teachers' ? 'students' : 'teachers'; ?>
<div class="card import-into">
  <div><span class="muted small">Importing into</span><div class="into"><?= icon($type === 'teachers' ? 'teacher' : 'users', 20) ?> <?= e($typeName($type)) ?></div></div>
  <a class="btn btn-ghost" href="<?= e(url('/admin/import/map?as=' . $other)) ?>">Import as <?= e(strtolower($typeName($other))) ?> instead</a>
</div>
<?php if ($guess && $guess !== $type): ?>
  <div class="flash error"><?= icon('help', 18) ?> <span>These columns look like a <b><?= e(strtolower($typeName($guess))) ?></b> list, but you are importing them into <b><?= e(strtolower($typeName($type))) ?></b>.
    <a href="<?= e(url('/admin/import/map?as=' . $guess)) ?>"><b>Import as <?= e(strtolower($typeName($guess))) ?></b></a></span></div>
<?php endif; ?>
<div class="card">
  <p class="muted">For each column in your file, choose what it is. We filled in our best guess (and your choices from last time). Columns you keep as <b>extra info</b> are saved on each person and shown on their page.</p>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/import/map')) ?>" id="import-map">
    <?= csrf_field() ?>
    <div class="table-wrap"><table class="table map-table">
      <tr><th>Column in your file</th><th>Examples</th><th>Use as</th><th>Details</th></tr>
      <?php foreach ($headers as $i => $h): [$field, $label, $target] = $map[$i]; ?>
        <tr data-col="<?= (int) $i ?>">
          <td><b><?= e($h !== '' ? $h : '(no header)') ?></b></td>
          <td class="small muted"><?= $samples[$i] ? e(implode(' · ', $samples[$i])) : '<i>empty</i>' ?></td>
          <td><select name="field[<?= (int) $i ?>]" class="map-field" aria-label="Use column <?= e($h) ?> as">
            <?php foreach ($fields as $k => $l): ?><option value="<?= e($k) ?>" <?= $field === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
          </select></td>
          <td>
            <div class="map-label" <?= $field === 'guardian' ? '' : 'hidden' ?>><input type="text" name="label[<?= (int) $i ?>]" value="<?= e($label) ?>" placeholder="Shown on the kiosk as, e.g. Father" maxlength="40" aria-label="Relationship shown for <?= e($h) ?>">
              <div class="hint">Used unless the cell says otherwise, e.g. "Rose Ng (Grandmother)".</div></div>
            <div class="map-target" <?= str_starts_with($field, 'g_') ? '' : 'hidden' ?>><select name="target[<?= (int) $i ?>]" aria-label="Guardian column for <?= e($h) ?>">
              <option value="">For which guardian column?</option>
              <?php foreach ($headers as $j => $hj): ?><option value="<?= (int) $j ?>" <?= $target === $j ? 'selected' : '' ?>><?= e($hj) ?></option><?php endforeach; ?>
            </select></div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <p class="small muted" style="margin-top:12px">Re-importing is safe: people are matched by ID (or by name) and updated, new guardians are added, nobody is removed. Cells with N/A count as empty.</p>
    <div class="row" style="margin-top:8px"><button class="btn btn-primary btn-lg"><?= icon('upload', 16) ?> Import <?= (int) $count ?> row<?= $count === 1 ? '' : 's' ?> into <?= e(strtolower($typeName($type))) ?></button>
      <a class="btn btn-ghost" href="<?= e(url('/admin/import/map?cancel=1')) ?>">Cancel</a></div>
  </form>
</div>
