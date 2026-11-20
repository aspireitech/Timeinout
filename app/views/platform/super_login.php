<?php $gsvg = '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>'; $mode = $mode ?? ''; ?>
<div style="max-width:440px;margin:30px auto 60px" class="card">
  <h2><?= icon('shield') ?> Platform owner</h2>
  <p class="muted">Manage all subscriber portals.</p>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>

  <div class="signin-step"><span class="n">1</span><b>Sign in with Google</b> <span class="badge in">recommended</span></div>
  <?php if ($google): ?>
    <a class="btn btn-primary btn-block google-btn" href="<?= e(base_path() . '/super/google') ?>"><span class="g-ic"><?= $gsvg ?></span> Sign in with Google</a>
    <p class="muted small" style="margin:8px 0 0">Most secure: uses your Google account and its 2-Step Verification.</p>
  <?php else: ?>
    <button class="btn btn-ghost btn-block google-btn" type="button" disabled><span class="g-ic"><?= $gsvg ?></span> Sign in with Google</button>
    <p class="muted small" style="margin:8px 0 0">Not set up yet. Sign in with an email code below, then add your Google keys in <b>/super → Sign-in</b>.</p>
  <?php endif; ?>

  <details class="signin-alt" <?= !$google || $mode === 'code' ? 'open' : '' ?>>
    <summary><span class="n">2</span> <?= $google ? 'Google not working? Sign in with an email code' : 'Sign in with an email code' ?></summary>
    <form method="post" action="<?= e(base_path() . '/super/login') ?>">
      <?= csrf_field() ?><input type="hidden" name="mode" value="code">
      <div class="field"><label for="sl-email">Provider email</label><input type="email" id="sl-email" name="email" autocomplete="username" required></div>
      <button class="btn btn-ghost btn-block"><?= icon('mail', 16) ?> Email me a sign-in code</button>
    </form>
  </details>

  <details class="signin-alt" <?= $mode === 'breakglass' ? 'open' : '' ?>>
    <summary><span class="n">3</span> Google and email both down? Break-glass sign-in</summary>
    <p class="muted small">Use your break-glass password and one of your one-time recovery codes (both in your password manager). Every use is logged and emailed to you.</p>
    <form method="post" action="<?= e(base_path() . '/super/login') ?>">
      <?= csrf_field() ?><input type="hidden" name="mode" value="breakglass">
      <div class="field"><label for="bg-email">Provider email</label><input type="email" id="bg-email" name="email" autocomplete="username" required></div>
      <div class="field"><label for="bg-pass">Break-glass password</label><input type="password" id="bg-pass" name="password" autocomplete="current-password" required></div>
      <div class="field"><label for="bg-code">Recovery code</label><input type="text" id="bg-code" name="recovery" autocomplete="one-time-code" placeholder="XXXX-XXXX-XXXX" required></div>
      <button class="btn btn-ghost btn-block"><?= icon('shield', 16) ?> Break-glass sign-in</button>
    </form>
  </details>
</div>
