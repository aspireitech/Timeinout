<div class="topbar">
  <div><h1>Good <?= (int) tenant_now()->format('G') < 12 ? 'morning' : ((int) tenant_now()->format('G') < 17 ? 'afternoon' : 'evening') ?> 👋</h1>
    <div class="muted"><?= e(fmt_date($today)) ?> · <?= (int) $totals['students'] ?> students · <?= (int) $totals['teachers'] ?> teachers on file</div></div>
  <div class="row"><a class="btn btn-ghost" href="<?= e(url('/admin/attendance')) ?>"><?= icon('calendar') ?> Today's attendance</a><a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= icon('kiosk') ?> Open kiosk</a></div>
</div>

<div class="grid c4" style="margin-bottom:18px">
  <a class="tile t1" href="<?= e(url('/admin/attendance?status=in')) ?>"><?= icon('users', 30) ?><div class="num"><?= count($students) ?></div><div class="lbl"><?= e(term('a2')) ?> on site now →</div></a>
  <a class="tile t2" href="<?= e(url('/admin/attendance?status=out')) ?>"><?= icon('out', 30) ?><div class="num"><?= $counts['s_out'] ?? 0 ?></div><div class="lbl"><?= e(term('a1')) ?> sign-outs today →</div></a>
  <a class="tile t3" href="<?= e(url('/admin/attendance?type=teacher&status=in')) ?>"><?= icon('teacher', 30) ?><div class="num"><?= count($teachers) ?></div><div class="lbl"><?= e(term('b2')) ?> on site now →</div></a>
  <div class="tile t4"><?= icon('box', 30) ?><div class="num"><?= $counts['pickups'] ?? 0 ?></div><div class="lbl">Material pickups today</div></div>
</div>

<div class="grid c2" style="margin-bottom:18px">
  <div class="card">
    <div class="card-head"><h3><?= icon('users') ?> <?= e(term('a2')) ?> on site</h3><span class="badge in"><?= count($students) ?> in</span></div>
    <?php if (!$students): ?><div class="empty">No students signed in yet today.</div><?php endif; ?>
    <?php foreach ($students as $s): ?>
      <div class="person" style="padding:8px 0;border-bottom:1px solid var(--line)">
        <span class="avatar"><?= e(initials($s['person_name'])) ?></span>
        <div style="flex:1"><b><?= e($s['person_name']) ?></b><div class="muted small">Dropped off by <?= e($s['guardian_name']) ?></div></div>
        <span class="badge in"><?= e(fmt_time($s['event_time'])) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('teacher') ?> <?= e(term('b2')) ?> on site</h3><span class="badge teacher"><?= count($teachers) ?> in</span></div>
    <?php if (!$teachers): ?><div class="empty">No teachers signed in yet today.</div><?php endif; ?>
    <?php foreach ($teachers as $s): ?>
      <div class="person" style="padding:8px 0;border-bottom:1px solid var(--line)">
        <span class="avatar teacher"><?= e(initials($s['person_name'])) ?></span>
        <div style="flex:1"><b><?= e($s['person_name']) ?></b></div>
        <span class="badge in">since <?= e(fmt_time($s['event_time'])) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid c2">
  <div class="card">
    <div class="card-head"><h3><?= icon('chart') ?> <?= e(term('a1')) ?> sign-ins, last 14 days</h3></div>
    <?php $max = max(1, max($trend)); ?>
    <div class="bars">
      <?php foreach ($trend as $d => $n): ?>
        <div class="bar"><span class="v"><?= $n ?: '' ?></span><i style="height:<?= round($n / $max * 100) ?>%"></i><b><?= e(date('j', strtotime($d))) ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('clock') ?> Latest activity</h3><a class="small" href="<?= e(url('/admin/logs')) ?>">View all →</a></div>
    <?php if (!$recent): ?><div class="empty">Nothing yet. Open the kiosk and try a check-in!</div><?php endif; ?>
    <?php foreach ($recent as $r): require APP_DIR . '/views/partials/event_row.php'; endforeach; ?>
  </div>
</div>
