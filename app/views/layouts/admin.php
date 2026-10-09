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
      <div class="nav-label">People</div>
      <?= $nav('/admin/students', 'users', term('a2') . (uses_contacts() ? ' & ' . strtolower(term('c2')) : '')) ?>
      <?= $nav('/admin/teachers', 'teacher', term('b2')) ?>
      <?= $nav('/admin/materials', 'box', 'Pickup ' . strtolower(term('items'))) ?>
      <?= $nav('/admin/import', 'upload', 'Import CSV') ?>
      <div class="nav-label">Setup</div>
      <?= $nav('/admin/tiles', 'grid', 'Kiosk tiles') ?>
      <?= $nav('/admin/settings', 'settings', 'Branding & settings') ?>
      <?= $nav('/admin/billing', 'card', 'Billing') ?>
      <?= $nav('/admin/users', 'shield', 'Users') ?>
      <?= $nav('/admin/audit', 'list', 'Audit log') ?>
      <a href="<?= e(url('/logout')) ?>"><?= icon('logout') ?><span class="t">Log out</span></a>
    </nav>
    <div class="foot">
      Signed in as <b><?= e($me['name']) ?></b><br>
      <span class="muted"><?= e(ucfirst($me['role'])) ?></span>
    </div>
  </aside>
  <main class="main">
    <?php $bs = billing_state($t); if (in_array($bs, ['trial', 'grace'], true) && !str_starts_with($here, '/admin/billing')): $left = trial_days_left($t); ?>
      <div class="banner"><?= icon('clock', 16) ?>
        <?= $bs === 'trial' ? "Free trial: $left day" . ($left === 1 ? '' : 's') . ' left.' : 'Your free trial has ended.' ?>
        <a href="<?= e(url('/admin/billing')) ?>"><b>Choose a plan →</b></a>
      </div>
    <?php elseif ($t['subscription_status'] === 'past_due'): ?>
      <div class="banner"><?= icon('card', 16) ?> Your last card payment failed. <a href="<?= e(url('/admin/billing')) ?>"><b>Update your card →</b></a></div>
    <?php endif; ?>
    <?php require APP_DIR . '/views/partials/flashes.php'; ?>
    <?= $content ?>
  </main>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
