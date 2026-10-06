<?php $t = tenant(); ?>
<div class="card auth-card">
  <div class="logo">
    <?php if ($t['logo_path']): ?><img src="<?= e(upload_url($t['logo_path'])) ?>" alt="<?= e($t['name']) ?>">
    <?php else: ?><div class="logo-mark" style="margin:0 auto;width:60px;height:60px;font-size:1.4rem"><?= e(initials($t['name'])) ?></div><?php endif; ?>
  </div>
  <h2 style="text-align:center"><?= e($t['name']) ?></h2>
  <p class="muted" style="text-align:center">Sign in to open the kiosk or the admin portal.</p>
  <?php if ($welcome): ?><div class="flash success"><?= icon('check', 18) ?> Your portal is ready — log in with the password you just chose.</div><?php endif; ?>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/login')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($email) ?>" required <?= $email ? '' : 'autofocus' ?>></div>
    <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required <?= $email ? 'autofocus' : '' ?>></div>
    <button class="btn btn-primary btn-lg btn-block" type="submit">Sign in</button>
  </form>
</div>
