<?php $qs = http_build_query($f); ?>
<div class="topbar">
  <div><h1>Attendance log</h1><div class="muted"><?= $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?> · <?= e(fmt_date($f['from'])) ?><?= $f['from'] !== $f['to'] ? ' – ' . e(fmt_date($f['to'])) : '' ?></div></div>
  <a class="btn btn-ghost" href="<?= e(url('/admin/logs/export?' . $qs)) ?>"><?= icon('download') ?> Export CSV</a>
</div>
<div class="card">
  <form class="inline-form" method="get" style="margin-bottom:14px">
    <div class="field"><label>From</label><input type="date" name="from" value="<?= e($f['from']) ?>"></div>
    <div class="field"><label>To</label><input type="date" name="to" value="<?= e($f['to']) ?>"></div>
    <div class="field"><label>Who</label><select name="type"><option value="">Everyone</option><option value="student" <?= $f['type'] === 'student' ? 'selected' : '' ?>>Students</option><option value="teacher" <?= $f['type'] === 'teacher' ? 'selected' : '' ?>>Teachers</option></select></div>
    <div class="field"><label>Action</label><select name="kind"><option value="">All</option><?php foreach (['sign_in', 'sign_out', 'material_pickup'] as $k): ?><option value="<?= $k ?>" <?= $f['kind'] === $k ? 'selected' : '' ?>><?= e(kind_label($k)) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Name</label><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Student, teacher or guardian"></div>
    <button class="btn btn-primary">Filter</button>
  </form>
  <div class="table-wrap">
    <table class="table">
      <tr><th>Date</th><th>Time</th><th>Name</th><th>Action</th><th>Guardian</th><th>Details</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr>
      <?php foreach ($logs as $r): $cls = ['sign_in' => 'in', 'sign_out' => 'out', 'material_pickup' => 'pickup'][$r['kind']]; ?>
        <tr>
          <td class="nowrap"><?= e(date('D M j', strtotime($r['event_date']))) ?></td>
          <td class="nowrap"><?= e(fmt_time($r['event_time'])) ?></td>
          <td><b><?= e($r['person_name']) ?></b> <span class="badge <?= e($r['person_type']) ?>"><?= e(ucfirst($r['person_type'])) ?></span></td>
          <td><span class="badge <?= $cls ?>"><?= e(kind_label($r['kind'])) ?></span></td>
          <td><?= e($r['guardian_name']) ?></td>
          <td class="small"><?= e($r['materials']) ?><?= $r['note'] ? ' ' . e($r['note']) : '' ?></td>
          <?php if (is_admin()): ?>
            <td class="right"><form method="post" action="<?= e(url('/admin/logs/' . $r['id'] . '/delete')) ?>" data-confirm="Delete this entry?"><?= csrf_field() ?><button class="link-btn" title="Delete"><?= icon('trash', 16) ?></button></form></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="7" class="empty">No entries for these filters.</td></tr><?php endif; ?>
    </table>
  </div>
  <?php if ($total > 100): ?>
    <div class="row" style="justify-content:center;margin-top:14px">
      <?php if ($page > 1): ?><a class="btn btn-sm" href="?<?= e($qs . '&page=' . ($page - 1)) ?>">← Newer</a><?php endif; ?>
      <span class="muted small">Page <?= $page ?> of <?= (int) ceil($total / 100) ?></span>
      <?php if ($page * 100 < $total): ?><a class="btn btn-sm" href="?<?= e($qs . '&page=' . ($page + 1)) ?>">Older →</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
