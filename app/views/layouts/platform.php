<!doctype html>
<html lang="en">
<head>
<?php require APP_DIR . '/views/partials/head.php'; ?>
<title><?= e($metaTitle ?? cfg('app_name') . ': time & attendance for any front door') ?></title>
<meta name="description" content="<?= e($metaDesc ?? 'Sign-in and sign-out kiosk for students, staff, members and visitors. Live attendance, safe pickups and reports emailed at 7:00.') ?>">
<?php require APP_DIR . '/views/partials/share.php'; ?>
</head>
<body>
<div class="wrap">
  <header class="pnav">
    <a class="brand" href="<?= e(base_path() . '/') ?>"><span class="logo-mark"><?= icon('clock', 20) ?></span><?= e(cfg('app_name')) ?></a>
    <div class="row">
      <?php if (!empty($_SESSION['super'])): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super') ?>">Subscribers</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/leads') ?>">Leads</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/requests') ?>">Requests</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/payments') ?>">Payments</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/audit') ?>">Audit log</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/settings') ?>">Settings</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/security') ?>">Sign-in</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/logout') ?>">Log out</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/#pricing') ?>">Pricing</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/whats-new') ?>">What's new</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/demo') ?>">Book a demo</a>
        <a class="btn btn-primary btn-sm" href="<?= e(base_path() . '/signup') ?>">Start free trial</a>
      <?php endif; ?>
    </div>
  </header>
  <?php require APP_DIR . '/views/partials/flashes.php'; ?>
  <?= $content ?>
  <footer class="footer">© <?= date('Y') ?> <?= e(cfg('app_name')) ?> · Time &amp; attendance for any front door</footer>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
