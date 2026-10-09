<!doctype html>
<html lang="en">
<head>
<?php require APP_DIR . '/views/partials/head.php'; ?>
<title><?= e(cfg('app_name')) ?> — Simple check-in &amp; check-out for schools</title>
<meta name="description" content="Student, teacher and material pickup check-in for schools, daycares and after-school programs.">
</head>
<body>
<div class="wrap">
  <header class="pnav">
    <a class="brand" href="<?= e(base_path() . '/') ?>"><span class="logo-mark"><?= icon('clock', 20) ?></span><?= e(cfg('app_name')) ?></a>
    <div class="row">
      <?php if (!empty($_SESSION['super'])): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super') ?>">Subscribers</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/audit') ?>">Audit log</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/settings') ?>">Email &amp; security</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/logout') ?>">Log out</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/#pricing') ?>">Pricing</a>
        <a class="btn btn-primary btn-sm" href="<?= e(base_path() . '/signup') ?>">Start free trial</a>
      <?php endif; ?>
    </div>
  </header>
  <?php require APP_DIR . '/views/partials/flashes.php'; ?>
  <?= $content ?>
  <footer class="footer">© <?= date('Y') ?> <?= e(cfg('app_name')) ?> · Made for schools, daycares and after-school programs</footer>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
