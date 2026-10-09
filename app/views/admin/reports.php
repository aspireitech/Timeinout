<?php $t = tenant(); $base = '/admin/reports?period=' . $period . '&date='; ?>
<div class="topbar">
  <div><h1>Reports</h1><div class="muted"><?= e(ucfirst($period)) ?> · <?= e($label) ?></div></div>
  <div class="row">
    <a class="btn btn-ghost" href="<?= e(url('/admin/reports/download?format=pdf&period=' . $period . '&date=' . $date)) ?>"><?= icon('download') ?> PDF</a>
    <a class="btn btn-ghost" href="<?= e(url('/admin/reports/download?format=csv&period=' . $period . '&date=' . $date)) ?>"><?= icon('download') ?> CSV</a>
    <details class="send-menu">
      <summary class="btn btn-primary"><?= icon('mail') ?> Email now</summary>
      <div class="send-pop card">
        <form method="post" action="<?= e(url('/admin/reports/send')) ?>"><?= csrf_field() ?><input type="hidden" name="period" value="<?= e($period) ?>"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="to" value="list">
          <button class="btn btn-block">To report recipients</button></form>
        <form method="post" action="<?= e(url('/admin/reports/send')) ?>"><?= csrf_field() ?><input type="hidden" name="period" value="<?= e($period) ?>"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="to" value="me">
          <button class="btn btn-block btn-ghost">Only to me (<?= e(current_user()['email']) ?>)</button></form>
      </div>
    </details>
  </div>
</div>

<div class="card" style="margin-bottom:18px">
  <form class="inline-form" method="get">
    <div class="field" style="flex:0 0 auto"><label>Period</label>
      <div class="row" style="gap:6px">
        <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $k => $l): ?>
          <a class="btn btn-sm <?= $period === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/reports?period=' . $k . '&date=' . $date)) ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <input type="hidden" name="period" value="<?= e($period) ?>">
    <div class="field" style="flex:0 1 200px"><label>Date in period</label><input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
    <?php
      $step = ['daily' => '1 day', 'weekly' => '1 week', 'monthly' => '1 month'][$period];
      $prev = (new DateTime($from))->modify('-' . $step)->format('Y-m-d');
      $next = (new DateTime($from))->modify('+' . $step)->format('Y-m-d');
    ?>
    <a class="btn btn-sm btn-ghost" href="<?= e(url($base . $prev)) ?>">← Previous</a>
    <a class="btn btn-sm btn-ghost" href="<?= e(url($base . $next)) ?>">Next →</a>
  </form>
</div>

<div class="grid c4" style="margin-bottom:18px">
  <div class="tile t1"><?= icon('in', 30) ?><div class="num"><?= $r['totals']['student_in'] ?></div><div class="lbl"><?= e(term('a1')) ?> sign-ins · <?= $r['unique_students'] ?> unique</div></div>
  <div class="tile t2"><?= icon('out', 30) ?><div class="num"><?= $r['totals']['student_out'] ?></div><div class="lbl"><?= e(term('a1')) ?> sign-outs</div></div>
  <div class="tile t3"><?= icon('teacher', 30) ?><div class="num"><?= $r['totals']['teacher_in'] ?></div><div class="lbl"><?= e(term('b1')) ?> sign-ins · <?= $r['unique_teachers'] ?> unique</div></div>
  <div class="tile t4"><?= icon('box', 30) ?><div class="num"><?= $r['totals']['pickups'] ?></div><div class="lbl">Material pickups</div></div>
</div>

<div class="grid c2" style="margin-bottom:18px">
  <div class="card">
    <h3><?= icon('chart') ?> <?= e(term('a1')) ?> sign-ins by day</h3>
    <?php $max = max(1, max(array_column($r['by_day'], 'student_in') ?: [0])); $many = count($r['by_day']) > 10; ?>
    <div class="bars">
      <?php foreach ($r['by_day'] as $d => $v): ?>
        <div class="bar"><span class="v"><?= $v['student_in'] ?: '' ?></span><i style="height:<?= round($v['student_in'] / $max * 100) ?>%"></i><b><?= e(date($many ? 'j' : 'D', strtotime($d))) ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <h3><?= icon('clock') ?> <?= e(term('b1')) ?> hours</h3>
    <?php if (!$r['teacher_minutes']): ?><div class="empty">No completed teacher sign-in/out pairs.</div><?php endif; ?>
    <table class="table">
      <?php foreach ($r['teacher_minutes'] as $name => $m): ?>
        <tr><td><div class="person"><span class="avatar teacher" style="width:30px;height:30px;border-radius:9px;font-size:.75rem"><?= e(initials($name)) ?></span><?= e($name) ?></div></td><td class="right"><b><?= fmt_minutes($m) ?></b></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="grid c2" style="margin-bottom:18px">
  <div class="card">
    <h3 style="color:var(--danger)"><?= icon('shield') ?> Signed in, never signed out (<?= count($r['not_signed_out']) ?>)</h3>
    <?php if (!$r['not_signed_out']): ?><div class="empty">Everyone was signed out. 🎉</div><?php endif; ?>
    <?php foreach ($r['not_signed_out'] as $ev): ?>
      <div class="person" style="padding:6px 0;border-bottom:1px solid var(--line)"><span class="badge <?= e($ev['person_type']) ?>"><?= e(ucfirst($ev['person_type'])) ?></span>
        <b><?= e($ev['person_name']) ?></b><span class="muted small" style="margin-left:auto"><?= e(date('M j', strtotime($ev['event_date']))) ?> · in <?= e(fmt_time($ev['event_time'])) ?></span></div>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <h3><?= icon('box') ?> Material pickups</h3>
    <?php if (!$r['pickups']): ?><div class="empty">No pickups in this period.</div><?php endif; ?>
    <?php foreach (array_slice($r['pickups'], 0, 30) as $ev): ?>
      <div style="padding:6px 0;border-bottom:1px solid var(--line)"><b><?= e($ev['person_name']) ?></b> <span class="muted small">by <?= e($ev['guardian_name']) ?> · <?= e(date('M j', strtotime($ev['event_date']))) ?> <?= e(fmt_time($ev['event_time'])) ?></span><br><span class="small"><?= e($ev['materials']) ?></span></div>
    <?php endforeach; ?>
  </div>
</div>

<?php $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
      $attach = explode(',', (string) ($t['report_attach'] ?? 'pdf,csv')); $hour = (int) ($t['report_hour'] ?? 7); ?>
<div class="card">
  <h3><?= icon('calendar') ?> Scheduled email reports</h3>
  <p class="muted small">Sent automatically at the time you choose (school time). Daily covers the day before and attaches the attendance sheet; weekly covers the last full Monday–Sunday week; monthly goes out on the 1st for the previous month.</p>
  <form method="post" action="<?= e(url('/admin/reports/settings')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label for="r-emails">Send to (comma-separated emails)</label><input type="text" id="r-emails" name="report_emails" value="<?= e(str_replace(',', ', ', (string) $t['report_emails'])) ?>" placeholder="owner@school.org, office@school.org"></div>
    <div class="grid c3">
      <div class="field"><label>Which reports</label>
        <label class="check"><input type="checkbox" name="report_daily" value="1" <?= $t['report_daily'] ? 'checked' : '' ?>> Daily</label>
        <label class="check"><input type="checkbox" name="report_weekly" value="1" <?= $t['report_weekly'] ? 'checked' : '' ?>> Weekly</label>
        <label class="check"><input type="checkbox" name="report_monthly" value="1" <?= $t['report_monthly'] ? 'checked' : '' ?>> Monthly</label>
      </div>
      <div class="field"><label for="r-hour">Send time</label>
        <select id="r-hour" name="report_hour"><?php for ($h = 0; $h < 24; $h++): ?><option value="<?= $h ?>" <?= $h === $hour ? 'selected' : '' ?>><?= date('g:00 A', mktime($h, 0)) ?></option><?php endfor; ?></select>
        <label for="r-day" style="margin-top:10px">Weekly report day</label>
        <select id="r-day" name="report_weekday"><?php foreach ($days as $n => $d): ?><option value="<?= $n ?>" <?= (int) ($t['report_weekday'] ?? 1) === $n ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select>
      </div>
      <div class="field"><label>Attachments</label>
        <label class="check"><input type="checkbox" name="attach[]" value="pdf" <?= in_array('pdf', $attach, true) ? 'checked' : '' ?>> PDF</label>
        <label class="check"><input type="checkbox" name="attach[]" value="csv" <?= in_array('csv', $attach, true) ? 'checked' : '' ?>> CSV (Excel)</label>
      </div>
    </div>
    <button class="btn btn-primary">Save schedule</button>
  </form>
  <?php if ($sent): ?>
    <div class="muted small" style="margin-top:14px">Recently sent on schedule: <?php foreach ($sent as $x): ?><span class="chip"><?= e($x['period'] . ' ' . $x['period_start']) ?></span><?php endforeach; ?></div>
  <?php endif; ?>
  <p class="muted small" style="margin:12px 0 0">Every send, download and change is recorded in the <a href="<?= e(url('/admin/audit?q=report')) ?>">Audit log</a>.</p>
</div>
