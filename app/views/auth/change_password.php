<?php $t = tenant(); $admin = in_array($u['role'], ['owner', 'admin'], true); ?>
<div class="card auth-card">
  <div class="logo"><div class="logo-mark" style="margin:0 auto;width:60px;height:60px"><?= icon('shield', 28) ?></div></div>
  <h2 style="text-align:center"><?= $forced ? 'Choose your password' : 'Change your password' ?></h2>
  <p class="muted" style="text-align:center"><?= e($t['name']) ?> · signed in as <b><?= e(login_name($u)) ?></b></p>
  <?php if ($forced): ?><div class="flash info"><?= icon('shield', 18) ?> <span>You signed in with a temporary password. Choose your own password to continue.</span></div><?php endif; ?>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/account/password')) ?>">
    <?= csrf_field() ?>
    <?php if (!$forced): ?>
      <div class="field"><label for="cp-cur">Current password</label><input type="password" id="cp-cur" name="current_password" autocomplete="current-password" required></div>
    <?php endif; ?>
    <div class="field"><label for="cp-new">New password</label><input type="password" id="cp-new" name="new_password" autocomplete="new-password" minlength="<?= $admin ? 10 : 8 ?>" required autofocus data-pw-min="<?= $admin ? 10 : 8 ?>"></div>
    <div class="field"><label for="cp-new2">New password again</label><input type="password" id="cp-new2" name="confirm_password" autocomplete="new-password" required data-pw-confirm="cp-new"></div>
    <div class="field"><label for="cp-email">Email for notices <span class="muted"><?= $admin ? '(required for admins)' : '(optional)' ?></span></label>
      <input type="email" id="cp-email" name="email" value="<?= e((string) $u['email']) ?>" autocomplete="email" <?= $admin ? 'required' : '' ?>></div>
    <button class="btn btn-primary btn-lg btn-block">Save and continue</button>
  </form>
  <p style="text-align:center;margin-top:12px"><a class="small" href="<?= e(url('/logout')) ?>">Sign out</a></p>
</div>
