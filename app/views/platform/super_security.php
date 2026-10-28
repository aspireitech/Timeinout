<div style="max-width:760px;margin:10px auto 60px">
  <div class="card" style="margin-bottom:18px">
    <h2><?= icon('shield') ?> Provider sign-in</h2>
    <p class="muted">How you get into this console, strongest first. Every sign-in is confirmed with a code emailed to <?= e(cfg('super_admin')['email'] ?? '') ?>.</p>
    <ol>
      <li><b>Sign in with Google</b> — <?= google_ready() ? '<span class="badge in">on</span>' : '<span class="badge warn">off: add google_oauth to app/config.php</span>' ?></li>
      <li><b>Email and password</b> — <?= $dbPassword ? '<span class="badge in">password stored hashed in the database</span>' : ($configPassword ? '<span class="badge warn">still read from app/config.php: set it below</span>' : '<span class="badge gray">not set</span>') ?></li>
      <li><b>Recovery codes</b> when the code email cannot be sent — <?= $left ? '<span class="badge in">' . (int) $left . ' left</span>' : '<span class="badge warn">none yet: create them below</span>' ?></li>
      <li><b>Last resort</b> (server access): <code>php cron/super_password.php</code> sets a new password in the database.</li>
    </ol>
  </div>

  <div class="card" style="margin-bottom:18px">
    <h3>Change password</h3>
    <p class="muted small">Stored hashed in the database. After saving, remove the <code>password</code> line from <code>super_admin</code> in app/config.php.</p>
    <form method="post" action="<?= e(base_path() . '/super/security') ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="field"><label for="sp-cur">Current password</label><input type="password" id="sp-cur" name="current_password" autocomplete="current-password" required></div>
      <div class="grid c2">
        <div class="field"><label for="sp-new">New password</label><input type="password" id="sp-new" name="new_password" minlength="14" autocomplete="new-password" required><div class="hint">At least 14 characters, letters and a number.</div></div>
        <div class="field"><label for="sp-new2">New password again</label><input type="password" id="sp-new2" name="confirm_password" autocomplete="new-password" required></div>
      </div>
      <button class="btn btn-primary">Save password</button>
    </form>
  </div>

  <div class="card">
    <h3>Recovery codes</h3>
    <?php if ($codes): ?>
      <div class="flash info"><?= icon('shield', 18) ?> <span>Save these 10 codes in your password manager now. They are shown only once; each works one time. Old codes no longer work.</span></div>
      <div class="rc-grid"><?php foreach ($codes as $c): ?><code><?= e($c) ?></code><?php endforeach; ?></div>
    <?php else: ?>
      <p class="muted small">If the sign-in code email ever fails, the sign-in page lets you use one of these instead. Creating new codes replaces the old ones.</p>
    <?php endif; ?>
    <form method="post" action="<?= e(base_path() . '/super/security') ?>" data-confirm="Create 10 new recovery codes? Any old codes stop working.">
      <?= csrf_field() ?><input type="hidden" name="action" value="recovery">
      <button class="btn <?= $codes ? 'btn-ghost' : 'btn-accent' ?>"><?= $left ? 'Create new recovery codes' : 'Create recovery codes' ?></button>
    </form>
  </div>
</div>
