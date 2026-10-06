<div style="max-width:420px;margin:30px auto 60px" class="card">
  <h2><?= icon('shield') ?> Platform owner</h2>
  <p class="muted">Manage all subscriber portals.</p>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label>Email</label><input type="email" name="email" required autofocus></div>
    <div class="field"><label>Password</label><input type="password" name="password" required></div>
    <button class="btn btn-primary btn-block">Log in</button>
  </form>
</div>
