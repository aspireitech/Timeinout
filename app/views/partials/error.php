<!doctype html>
<html lang="en">
<head><?php require APP_DIR . '/views/partials/head.php'; ?><title><?= (int) $code ?> · <?= e(cfg('app_name')) ?></title></head>
<body>
<div class="auth-wrap">
  <div class="card auth-card" style="text-align:center">
    <div class="logo-mark" style="margin:0 auto 14px;width:56px;height:56px;font-size:1.3rem"><?= (int) $code ?></div>
    <h2><?= $code == 404 ? 'Not found' : ($code == 403 ? 'Unavailable' : 'Something went wrong') ?></h2>
    <p class="muted"><?= e($message) ?></p>
    <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go to start</a>
  </div>
</div>
</body>
</html>
