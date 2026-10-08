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
  <?php if ($t['slug'] === DEMO_SLUG): ?>
    <div class="demo-creds">
      <b>Demo logins</b> <span class="muted small">tap to fill</span>
      <?php foreach (DEMO_LOGINS as [$role, , $dEmail, $dPass]): ?>
        <button type="button" class="demo-fill" data-email="<?= e($dEmail) ?>" data-pass="<?= e($dPass) ?>">
          <span class="badge <?= $role === 'owner' ? 'student' : 'gray' ?>"><?= $role === 'owner' ? 'Admin' : 'Staff' ?></span>
          <code><?= e($dEmail) ?></code> / <code><?= e($dPass) ?></code>
        </button>
      <?php endforeach; ?>
    </div>
    <script>
      document.querySelectorAll('.demo-fill').forEach(function (b) {
        b.addEventListener('click', function () {
          document.getElementById('email').value = b.dataset.email;
          document.getElementById('password').value = b.dataset.pass;
        });
      });
    </script>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/login')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($email) ?>" required <?= $email ? '' : 'autofocus' ?>></div>
    <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required <?= $email ? 'autofocus' : '' ?>></div>
    <button class="btn btn-primary btn-lg btn-block" type="submit">Sign in</button>
  </form>
</div>
