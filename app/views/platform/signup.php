<div style="max-width:720px;margin:10px auto 40px">
  <div class="card">
    <h1>Create your space</h1>
    <p class="muted">Takes about a minute. Your kiosk is ready the moment you finish.</p>
    <form method="post" action="<?= e(base_path() . '/signup') ?>" id="signup-form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="org_name">Business name</label>
        <input type="text" id="org_name" name="org_name" value="<?= e($old['org_name']) ?>" placeholder="South Jordan Kumon" required autofocus>
        <?php if (isset($errors['org_name'])): ?><div class="err"><?= e($errors['org_name']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label>Industry</label>
        <div class="ind-pick">
          <?php foreach (industries() as $k => $i): ?>
            <label><input type="radio" name="industry" value="<?= e($k) ?>" <?= $old['industry'] === $k ? 'checked' : '' ?>>
              <span><?= icon($i['icon'], 20) ?><b><?= e($i['name']) ?></b><small><?= e(implode(' · ', array_map(fn($tl) => $tl[1], array_filter($i['tiles'], fn($tl) => $tl[6])))) ?></small></span></label>
          <?php endforeach; ?>
        </div>
        <div class="hint">Sets the kiosk buttons and the words used in the app. You can rename or add buttons later.</div>
      </div>

      <div class="field">
        <label>Your web address</label>
        <div class="addr-grid">
          <div><label for="state" class="sub">State</label>
            <select id="state" name="state" required>
              <option value="">Choose…</option>
              <?php foreach (US_STATES as $code => $name): ?><option value="<?= $code ?>" <?= $old['state'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
              <option value="XX" <?= $old['state'] === 'XX' ? 'selected' : '' ?>>Outside the US (no prefix)</option>
            </select></div>
          <div><label for="city" class="sub">City <span class="muted">(optional)</span></label><input type="text" id="city" name="city" value="<?= e($old['city']) ?>" maxlength="15" placeholder="Sandy"></div>
          <div><label for="short" class="sub">Short name, 3–10 letters</label><input type="text" id="short" name="short" value="<?= e($old['short']) ?>" maxlength="10" placeholder="sjkumon" required></div>
        </div>
        <div class="addr-preview" id="addr-preview" data-check="<?= e(base_path() . '/check-address') ?>"><?= icon('globe', 16) ?> <span>Choose a state and a short name to see your address.</span></div>
        <?php if (isset($errors['state'])): ?><div class="err"><?= e($errors['state']) ?></div><?php endif; ?>
        <?php if (isset($errors['short'])): ?><div class="err"><?= e($errors['short']) ?></div><?php endif; ?>
        <div class="hint">The state prefix is fixed once your space is created.</div>
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
        <input type="password" id="password" name="password" minlength="10" required>
        <?php if (isset($errors['password'])): ?><div class="err"><?= e($errors['password']) ?></div><?php else: ?><div class="hint">At least 10 characters, with letters and a number.</div><?php endif; ?>
      </div>
      <div class="grid c2">
        <div class="field">
          <label for="plan">Plan</label>
          <select id="plan" name="plan">
            <?php foreach ($plans as $k => $p): ?><option value="<?= e($k) ?>" <?= $old['plan'] === $k ? 'selected' : '' ?>><?= e($p['name'] . ': ' . $p['price']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="timezone">Time zone</label>
          <select id="timezone" name="timezone">
            <option value="">Automatic (each device's own time)</option>
            <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?><option <?= $old['timezone'] === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <label class="check" style="margin:6px 0 18px"><input type="checkbox" name="demo" value="1" <?= $old['demo'] ? 'checked' : '' ?>> Add sample people so I can try it right away</label>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Create my space</button>
      <p class="muted small" style="text-align:center;margin-top:12px"><?= (int) cfg('trial_days', 30) ?>-day free trial · no card needed · cancel anytime</p>
    </form>
  </div>
</div>
