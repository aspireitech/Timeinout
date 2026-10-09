<?php
$t = tenant();
$me = current_user();
$here = $GLOBALS['request_path'] ?? '';
$nav = function (string $path, string $ico, string $label) use ($here) {
    $active = $path === '/admin' ? $here === '/admin' : str_starts_with($here, $path);
    return '<a class="' . ($active ? 'active' : '') . '" href="' . e(url($path)) . '">' . icon($ico) . '<span class="t">' . e($label) . '</span></a>';
};
?>
<!doctype html>
<html lang="en">
<head><?php require APP_DIR . '/views/partials/head.php'; ?><title>Admin · <?= e($t['name']) ?></title></head>
<body>
<div class="admin">
  <aside class="sidebar">
    <div class="brand">
      <?php if ($t['logo_path']): ?><img src="<?= e(upload_url($t['logo_path'])) ?>" alt=""><?php else: ?><span class="logo-mark"><?= e(initials($t['name'])) ?></span><?php endif; ?>
      <span><?= e($t['name']) ?></span>
    </div>
    <nav>
      <?= $nav('/admin', 'home', 'Dashboard') ?>
      <a href="<?= e(url('/')) ?>"><?= icon('kiosk') ?><span class="t">Open kiosk</span></a>
      <?= $nav('/admin/attendance', 'calendar', "Today's attendance") ?>
      <?= $nav('/admin/logs', 'list', 'Activity log') ?>
      <?= $nav('/admin/reports', 'chart', 'Reports') ?>
      <?php if (is_admin()): ?>
        <div class="nav-label">People</div>
        <?= $nav('/admin/students', 'users', 'Students & guardians') ?>
        <?= $nav('/admin/teachers', 'teacher', 'Teachers & staff') ?>
        <?= $nav('/admin/materials', 'box', 'Materials') ?>
        <?= $nav('/admin/import', 'upload', 'Import CSV') ?>
        <div class="nav-label">Setup</div>
        <?= $nav('/admin/settings', 'settings', 'Branding & settings') ?>
      <?php endif; ?>
      <?= $nav('/admin/users', 'shield', is_admin() ? 'Users' : 'My password') ?>
      <a href="<?= e(url('/logout')) ?>"><?= icon('logout') ?><span class="t">Log out</span></a>
    </nav>
    <div class="foot">
      Signed in as <b><?= e($me['name']) ?></b><br>
      <span class="muted"><?= e(ucfirst($me['role'])) ?></span>
    </div>
  </aside>
  <main class="main">
    <?php if ($t['status'] === 'trial' && $t['trial_ends_at']):
        $left = (int) ceil((strtotime($t['trial_ends_at']) - time()) / 86400); ?>
      <div class="banner"><?= icon('clock', 16) ?>
        <?= $left > 0 ? "Free trial — $left day" . ($left === 1 ? '' : 's') . ' left.' : 'Your free trial has ended.' ?>
        <?php if (cfg('payment_link')): ?><a href="<?= e(cfg('payment_link')) ?>?client_reference_id=<?= e($t['slug']) ?>" target="_blank" rel="noopener"><b>Subscribe now →</b></a><?php endif; ?>
      </div>
    <?php endif; ?>
    <?php require APP_DIR . '/views/partials/flashes.php'; ?>
    <?= $content ?>
  </main>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
