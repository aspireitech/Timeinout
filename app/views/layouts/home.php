<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($metaTitle ?? cfg('app_name') . ': time & attendance for any front door') ?></title>
<meta name="description" content="<?= e($metaDesc ?? 'Sign-in and sign-out kiosk for students, staff, members and visitors. Live attendance, safe pickups and reports emailed at 7:00.') ?>">
<?php require APP_DIR . '/views/partials/share.php'; ?>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="icon" href="<?= asset('img/favicon-32.png') ?>" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="<?= asset('img/apple-touch-icon.png') ?>">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="<?= asset('css/home.css') ?>">
<script>try { document.cookie = 'tio_tz=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone) + '; path=/; max-age=31536000; samesite=lax'; document.cookie = 'tio_tzo=' + (-new Date().getTimezoneOffset()) + '; path=/; max-age=31536000; samesite=lax'; } catch (e) {}</script>
</head>
<body>
<?= $content ?>
<?php if (preg_match('#^/(for/[a-z-]+)?$#', (string) ($GLOBALS['request_path'] ?? ''))): $_q = ($_s = lead_source()) ? '?src=' . rawurlencode($_s) : ''; ?>
<div class="sticky-cta" id="sticky-cta" aria-hidden="true">
  <a class="btn btn-primary" href="<?= e(base_path() . '/signup' . $_q) ?>">Start free trial</a>
  <a class="btn btn-ghost" href="<?= e(base_path() . '/demo' . $_q) ?>">Book a demo</a>
</div>
<script>(function () { var b = document.getElementById('sticky-cta'); if (!b) return;
  function f() { var on = window.scrollY > 520; b.classList.toggle('on', on); b.setAttribute('aria-hidden', on ? 'false' : 'true'); }
  window.addEventListener('scroll', f, { passive: true }); f(); })();</script>
<?php endif; ?>
<script src="<?= asset('js/home.js') ?>"></script>
</body>
</html>
