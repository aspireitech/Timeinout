<div style="max-width:560px;margin:10px auto 40px">
  <div class="card">
    <h1>Create your portal</h1>
    <p class="muted">Takes about a minute. Your portal is ready the moment you click the button.</p>
    <form method="post" action="<?= e(base_path() . '/signup') ?>" id="signup-form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="org_name">School or company name</label>
        <input type="text" id="org_name" name="org_name" value="<?= e($old['org_name']) ?>" placeholder="Sunrise Academy" required autofocus>
        <?php if (isset($errors['org_name'])): ?><div class="err"><?= e($errors['org_name']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label for="slug">Your portal address</label>
        <div class="slug-input">
          <input type="text" id="slug" name="slug" value="<?= e($old['slug']) ?>" placeholder="sunrise" required data-check="<?= e(base_path() . '/check-address') ?>">
          <span>.<?= e(cfg('base_domain')) ?></span>
        </div>
        <div class="hint" id="slug-hint">Lowercase letters, numbers and dashes.</div>
        <?php if (isset($errors['slug'])): ?><div class="err"><?= e($errors['slug']) ?></div><?php endif; ?>
      </div>
      <div class="grid c2">
        <div class="field">
          <label for="admin_name">Your name</label>
          <input type="text" id="admin_name" name="admin_name" value="<?= e($old['admin_name']) ?>" required>
          <?php if (isset($errors['admin_name'])): ?><div class="err"><?= e($errors['admin_name']) ?></div><?php endif; ?>
        </div>
        <div class="field">
          <label for="email">Email (your login)</label>
          <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
          <?php if (isset($errors['email'])): ?><div class="err"><?= e($errors['email']) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="8" required>
        <?php if (isset($errors['password'])): ?><div class="err"><?= e($errors['password']) ?></div><?php else: ?><div class="hint">At least 8 characters.</div><?php endif; ?>
      </div>
      <div class="grid c2">
        <div class="field">
          <label for="plan">Plan</label>
          <select id="plan" name="plan">
            <?php foreach ($plans as $k => $p): ?><option value="<?= e($k) ?>" <?= $old['plan'] === $k ? 'selected' : '' ?>><?= e($p['name'] . ' — ' . $p['price']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="timezone">Time zone</label>
          <select id="timezone" name="timezone" data-auto="1">
            <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?><option <?= $old['timezone'] === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <label class="check" style="margin:6px 0 18px"><input type="checkbox" name="demo" value="1" <?= $old['demo'] ? 'checked' : '' ?>> Add sample students, parents and teachers so I can try it right away</label>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Create my portal</button>
      <p class="muted small" style="text-align:center;margin-top:12px"><?= (int) cfg('trial_days', 30) ?>-day free trial · cancel anytime</p>
    </form>
  </div>
</div>
