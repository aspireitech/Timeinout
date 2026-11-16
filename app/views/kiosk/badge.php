<!doctype html>
<html lang="en">
<head>
<?php require APP_DIR . '/views/partials/head.php'; ?>
<title><?= $person ? e($person) . ' · badge' : 'Badge' ?> · <?= e($t['name']) ?></title>
<meta name="robots" content="noindex">
</head>
<body style="background:#F4F5FA">
<div style="max-width:420px;margin:24px auto;padding:0 16px;text-align:center;font-family:inherit">
  <div class="card" style="padding:26px 22px">
    <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:14px">
      <?php if ($t['logo_path']): ?><img src="<?= e(upload_url($t['logo_path'])) ?>" alt="" style="height:36px"><?php else: ?><span class="logo-mark"><?= e(initials($t['name'])) ?></span><?php endif; ?>
      <b><?= e($t['name']) ?></b>
    </div>
    <?php if ($person): ?>
      <h1 style="margin:0 0 4px;font-size:1.6rem"><?= e($person) ?></h1>
      <p class="muted" style="margin:0 0 16px">Show this code to the kiosk camera to sign in or out.</p>
      <div id="qr" data-code="<?= e($code) ?>" style="background:#fff;padding:14px;border-radius:16px;display:inline-block;max-width:100%"></div>
      <p class="muted small" style="margin-top:14px">Tip: add this page to your home screen, or take a screenshot. Keep it private: it works like a key card.</p>
    <?php else: ?>
      <h1 style="margin:0 0 8px;font-size:1.4rem">This badge is not active</h1>
      <p class="muted">It may have been replaced with a new one. Please ask <?= e($t['name']) ?> for your current badge.</p>
    <?php endif; ?>
  </div>
</div>
<?php if ($person): ?>
<script src="<?= asset('js/vendor/qrcode.js') ?>"></script>
<script>
(function () {
  var el = document.getElementById('qr'); var q = qrcode(0, 'M'); q.addData(el.dataset.code); q.make();
  el.innerHTML = q.createSvgTag({ cellSize: 8, margin: 2, scalable: true });
  var svg = el.querySelector('svg'); if (svg) { svg.style.width = '280px'; svg.style.maxWidth = '100%'; svg.style.height = 'auto'; }
})();
</script>
<?php endif; ?>
</body>
</html>
