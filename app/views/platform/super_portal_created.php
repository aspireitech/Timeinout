<?php $portal = tenant_url($t, '/'); $login = tenant_url($t, '/login?email=' . urlencode($email)); ?>
<div style="max-width:620px;margin:20px auto 60px" class="card">
  <h1><?= icon('check') ?> <?= $reset ? 'New owner password' : 'Portal ready' ?></h1>
  <p class="muted"><b><?= e($t['name']) ?></b><?= tenant_db_config($t) ? ' · own database <code>' . e($t['db_name']) . '</code>' : ' · shared database' ?></p>
  <div class="flash info"><?= icon('shield', 18) ?> <span>Copy this now: the password is shown only once. Send it to the customer separately from the link (for example, the link by email and the password by text).</span></div>
  <table class="table">
    <tr><th>Portal (kiosk)</th><td><a href="<?= e($portal) ?>" target="_blank"><?= e($portal) ?></a></td></tr>
    <tr><th>Admin sign-in</th><td><a href="<?= e($login) ?>" target="_blank"><?= e(tenant_url($t, '/login')) ?></a></td></tr>
    <tr><th>Owner email</th><td><code><?= e($email) ?></code></td></tr>
    <tr><th>Temporary password</th><td><code style="font-size:1.1rem"><?= e($password) ?></code></td></tr>
  </table>
  <p class="small muted">At sign-in the owner also gets a 6-digit code by email. Ask them to change the password under <b>Admin → Users → Change my password</b> after the first sign-in.</p>
  <a class="btn btn-primary" href="<?= e(base_path() . '/super') ?>">Back to subscribers</a>
</div>
