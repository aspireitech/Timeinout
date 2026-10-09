<div class="card auth-card">
  <div class="logo"><div class="logo-mark" style="margin:0 auto;width:60px;height:60px"><?= icon('mail', 28) ?></div></div>
  <h2 style="text-align:center">Check your email</h2>
  <p class="muted" style="text-align:center">We sent a 6-digit sign-in code to <b><?= e($email) ?></b>. It expires in 10 minutes.</p>
  <?php if ($demoCode): ?>
    <div class="flash info"><?= icon('shield', 18) ?> <span>Demo school only: the code is <b style="letter-spacing:2px"><?= e($demoCode) ?></b>. Real schools receive it by email.</span></div>
  <?php endif; ?>
  <?php if ($info): ?><div class="flash success"><?= icon('check', 18) ?> <?= e($info) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <div class="field">
      <label for="code">Sign-in code</label>
      <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus
             style="font-size:1.6rem;letter-spacing:.4em;text-align:center" placeholder="••••••">
    </div>
    <button class="btn btn-primary btn-lg btn-block" type="submit">Verify and sign in</button>
  </form>
  <form method="post" action="<?= e($action) ?>" style="margin-top:12px;text-align:center">
    <?= csrf_field() ?><input type="hidden" name="resend" value="1">
    <button class="link-btn" style="color:var(--brand)" type="submit">Send a new code</button>
    <span class="muted small"> · </span><a class="small" href="<?= e($back) ?>">Start over</a>
  </form>
</div>
