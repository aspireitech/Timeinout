<?php
$isToday = $date === tenant_today();
$link = function (array $over = []) use ($type, $date, $status, $q) {
    $p = array_filter(array_merge(['type' => $type, 'date' => $date, 'status' => $status, 'q' => $q], $over), fn($v) => $v !== '' && $v !== null);
    return url('/admin/attendance?' . http_build_query($p));
};
$prev = (new DateTime($date))->modify('-1 day')->format('Y-m-d');
$next = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
$teacher = $type === 'teacher';
$visitor = $type === 'visitor';
$label = ['in' => 'Not checked out', 'out' => 'Checked out', 'absent' => 'Absent'];
$cls = ['in' => 'warn', 'out' => 'in', 'absent' => 'gray'];
?>
<div class="topbar">
  <div>
    <h1><?= $isToday ? "Today's attendance" : 'Attendance' ?></h1>
    <div class="muted"><?= e(fmt_date($date)) ?></div>
  </div>
  <div class="row">
    <a class="btn btn-ghost" href="<?= e($link(['export' => 'pdf'])) ?>"><?= icon('download') ?> PDF</a>
    <a class="btn btn-ghost" href="<?= e($link(['export' => 'csv'])) ?>"><?= icon('download') ?> CSV</a>
  </div>
</div>

<div class="card att-bar">
  <div class="att-tabs">
    <a class="<?= $teacher ? '' : 'on' ?>" href="<?= e($link(['type' => 'student', 'status' => ''])) ?>"><?= icon('users', 18) ?> <?= e(term('a2')) ?></a>
    <a class="<?= $teacher ? 'on' : '' ?>" href="<?= e($link(['type' => 'teacher', 'status' => ''])) ?>"><?= icon('teacher', 18) ?> <?= e(term('b2')) ?></a>
    <a class="<?= $type === 'visitor' ? 'on' : '' ?>" href="<?= e($link(['type' => 'visitor', 'status' => ''])) ?>"><?= icon('badge', 18) ?> <?= e(term('v2')) ?></a>
  </div>
  <form class="att-date" method="get">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <a class="btn btn-sm btn-ghost" href="<?= e($link(['date' => $prev])) ?>" aria-label="Previous day">←</a>
    <input type="date" name="date" value="<?= e($date) ?>" max="<?= e(tenant_today()) ?>" onchange="this.form.submit()" aria-label="Date">
    <?php if (!$isToday): ?><a class="btn btn-sm btn-ghost" href="<?= e($link(['date' => $next])) ?>" aria-label="Next day">→</a>
      <a class="btn btn-sm btn-primary" href="<?= e($link(['date' => tenant_today()])) ?>">Today</a><?php endif; ?>
  </form>
</div>

<div class="att-sum">
  <a class="sum s-all <?= $status === '' ? 'on' : '' ?>" href="<?= e($link(['status' => ''])) ?>"><b><?= $count['present'] ?></b><span>Checked in<?= $isToday ? ' today' : '' ?></span></a>
  <a class="sum s-out <?= $status === 'out' ? 'on' : '' ?>" href="<?= e($link(['status' => 'out'])) ?>"><b><?= $count['out'] ?></b><span>Checked out</span></a>
  <a class="sum s-in <?= $status === 'in' ? 'on' : '' ?>" href="<?= e($link(['status' => 'in'])) ?>"><b><?= $count['in'] ?></b><span><?= $isToday ? 'Still here' : 'Never checked out' ?></span></a>
<?php if (!$visitor): ?>  <a class="sum s-abs <?= $status === 'absent' ? 'on' : '' ?>" href="<?= e($link(['status' => 'absent'])) ?>"><b><?= $count['absent'] ?></b><span>Absent</span></a><?php endif; ?>
</div>

<div class="card">
  <form class="inline-form" method="get" style="margin-bottom:12px">
    <input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="field"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name…" aria-label="Search"></div>
    <button class="btn"><?= icon('search', 16) ?> Search</button>
    <?php if ($status !== '' || $q !== ''): ?><a class="btn btn-ghost" href="<?= e($link(['status' => '', 'q' => ''])) ?>">Show all</a><?php endif; ?>
  </form>
  <div class="table-wrap">
    <table class="table att-table">
      <tr>
        <th><?= e($visitor ? term('v1') : ($teacher ? term('b1') : term('a1'))) ?></th>
        <?php if ($visitor): ?><th>Company</th><th>Visiting</th><?php elseif (!$teacher): ?><th>Came with</th><?php endif; ?>
        <th>Check-in</th>
        <?php if (!$teacher && !$visitor): ?><th>Left with</th><?php endif; ?>
        <th>Check-out</th>
        <th><?= $teacher ? 'Hours' : 'Time on site' ?></th>
        <th>Status</th>
      </tr>
      <?php foreach ($rows as $r): ?>
        <tr class="<?= $r['status'] === 'absent' ? 'is-absent' : '' ?>">
          <td><div class="person"><span class="avatar <?= $teacher ? 'teacher' : '' ?>" style="width:32px;height:32px;border-radius:10px;font-size:.75rem"><?= e(initials($r['name'])) ?></span>
            <div><b><?= e($r['name']) ?></b><?php if ($r['grade'] && !$visitor): ?><div class="muted small"><?= e($r['grade']) ?></div><?php endif; ?></div></div></td>
          <?php if ($visitor): ?><td><?= e($r['in_by']) ?></td><td><?= e($r['grade']) ?></td><?php elseif (!$teacher): ?><td><?= e($r['in_by']) ?></td><?php endif; ?>
          <td class="nowrap tnum"><?= e(fmt_time($r['in'])) ?></td>
          <?php if (!$teacher && !$visitor): ?><td><?= e($r['out_by']) ?></td><?php endif; ?>
          <td class="nowrap tnum"><?= e(fmt_time($r['out'])) ?></td>
          <td class="nowrap tnum muted"><?= $r['minutes'] !== null ? e(fmt_minutes($r['minutes'])) : '' ?></td>
          <td><span class="badge <?= $cls[$r['status']] ?>"><?= e($label[$r['status']]) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="empty">Nobody matches. <a href="<?= e($link(['status' => '', 'q' => ''])) ?>">Show everyone</a></td></tr><?php endif; ?>
    </table>
  </div>
  <?php if (!$teacher): ?><p class="muted small" style="margin:12px 0 0">A student who leaves and comes back the same day appears on two rows, one per visit.</p><?php endif; ?>
</div>
