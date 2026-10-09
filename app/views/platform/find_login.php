<div style="max-width:460px;margin:30px auto 60px" class="card">
  <h2><?= icon('globe') ?> Sign in to your space</h2>
  <p class="muted">Enter your space's address, for example <b>utsjkumon</b>.</p>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label for="space">Your address</label><input type="text" id="space" name="space" required autofocus autocapitalize="none" placeholder="utsjkumon"></div>
    <button class="btn btn-primary btn-block">Continue</button>
  </form>
  <p class="muted small" style="margin-top:14px">New here? <a href="<?= e(base_path() . '/signup') ?>">Create your space</a>.</p>
</div>
