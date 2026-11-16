<?php $contacts = uses_contacts(); ?>
<style>
  .badge-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 14px; }
  .bcard { border: 1px solid #E2E4EF; border-radius: 16px; padding: 14px; text-align: center; background: #fff; break-inside: avoid; }
  .bcard .org { font-size: .78rem; color: #6B7090; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
  .bcard .nm { font-weight: 800; font-size: 1.05rem; margin-top: 4px; }
  .bcard .gp { color: #6B7090; font-size: .85rem; }
  .bcard .qr svg { width: 150px; height: 150px; margin: 8px auto 4px; display: block; }
  .bcard .cd { font-family: monospace; font-size: .72rem; color: #8A8FA8; }
  @media print {
    .sidebar, .page-head .row, .no-print, .flash { display: none !important; }
    .admin, .main, body { background: #fff !important; }
    .badge-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .bcard { border: 1px dashed #999; }
  }
</style>
<div class="page-head">
  <div><h1>QR badges</h1><p class="muted">Each person has a personal QR code. At the kiosk, tap <b>Scan my badge</b> and hold it to the camera, or use a USB / Bluetooth badge scanner. Taps and scans work together.</p></div>
  <div class="row">
    <button type="button" class="btn btn-primary" onclick="window.print()"><?= icon('download') ?> Print badges</button>
  </div>
</div>

<div class="card no-print" style="margin-bottom:14px">
  <div class="row" style="justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
    <div class="row">
      <?php foreach (['all' => 'Everyone', 'students' => term('a2'), 'staff' => term('b2')] as $k => $label): ?>
        <a class="btn btn-sm <?= $who === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/badges' . ($k === 'all' ? '' : '?who=' . $k))) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="row">
      <form method="post" action="<?= e(url('/admin/badges/email')) ?>" data-confirm="Email every <?= e(strtolower(term('b1'))) ?> with an email address on file their personal badge link?"><?= csrf_field() ?><input type="hidden" name="who" value="staff"><button class="btn btn-ghost btn-sm"><?= icon('mail', 16) ?> Email <?= e(strtolower(term('b2'))) ?> their badge</button></form>
      <?php if ($contacts): ?><form method="post" action="<?= e(url('/admin/badges/email')) ?>" data-confirm="Email each <?= e(strtolower(term('c1'))) ?> on file the badge link of their <?= e(strtolower(term('a1'))) ?>?"><?= csrf_field() ?><input type="hidden" name="who" value="students"><button class="btn btn-ghost btn-sm"><?= icon('mail', 16) ?> Email <?= e(strtolower(term('c2'))) ?> their <?= e(strtolower(term('a1'))) ?>'s badge</button></form><?php endif; ?>
    </div>
  </div>
  <p class="muted small" style="margin:8px 0 0">Lost a badge? Use <b>New badge</b> on that card: the old code stops working at once.</p>
</div>

<div class="badge-grid">
  <?php foreach ($people as $p): ?>
    <div class="bcard">
      <div class="org"><?= e($t['name']) ?></div>
      <div class="qr" data-code="<?= e((string) $p['code']) ?>"></div>
      <div class="nm"><?= e($p['name']) ?></div>
      <div class="gp"><?= e((string) $p['group']) ?></div>
      <div class="cd"><?= e((string) $p['code']) ?></div>
      <form method="post" action="<?= e(url('/admin/badges/reissue')) ?>" class="no-print" style="margin-top:8px" data-confirm="Cancel <?= e($p['name']) ?>'s badge and make a new one? The old badge stops working.">
        <?= csrf_field() ?><input type="hidden" name="type" value="<?= e($p['type']) ?>"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <button class="btn btn-ghost btn-sm">New badge</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$people): ?><p class="empty">Nobody to show yet. Add people first.</p><?php endif; ?>
</div>
<script src="<?= asset('js/vendor/qrcode.js') ?>"></script>
<script>
document.querySelectorAll('.bcard .qr').forEach(function (el) {
  if (!el.dataset.code) return;
  var q = qrcode(0, 'M'); q.addData(el.dataset.code); q.make();
  el.innerHTML = q.createSvgTag({ cellSize: 4, margin: 1, scalable: true });
});
</script>
