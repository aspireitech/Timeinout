<div style="max-width:720px;margin:10px auto 40px">
  <div class="card">
    <h1>Create your space</h1>
    <p class="muted">Takes about a minute. Your kiosk is ready the moment you finish.</p>
    <form method="post" action="<?= e(base_path() . '/signup') ?>" id="signup-form" novalidate>
      <?= csrf_field() ?><?php if ($old['src']): ?><input type="hidden" name="src" value="<?= e($old['src']) ?>"><?php endif; ?>
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
        <label>Location</label>
        <div class="grid c2" style="gap:10px">
          <div><label for="state" class="sub">State</label>
            <select id="state" name="state" required>
              <option value="">Choose…</option>
              <?php foreach (US_STATES as $code => $name): ?><option value="<?= $code ?>" <?= $old['state'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
              <option value="XX" <?= $old['state'] === 'XX' ? 'selected' : '' ?>>Outside the US</option>
            </select></div>
          <div><label for="city" class="sub">City <span class="muted">(optional)</span></label><input type="text" id="city" name="city" value="<?= e($old['city']) ?>" maxlength="40" placeholder="Sandy"></div>
        </div>
        <?php if (isset($errors['state'])): ?><div class="err"><?= e($errors['state']) ?></div><?php endif; ?>
      </div>

      <?php $suffix = cfg('tenant_url_mode') === 'subdomain' ? '.' . cfg('base_domain') : ''; $prefix = $suffix ? '' : cfg('base_domain') . '/s/'; ?>
      <div class="field">
        <label for="slug">Your web address</label>
        <div class="addr-preview" id="addr-preview" data-check="<?= e(base_path() . '/check-address') ?>"><?= icon('globe', 16) ?> <span id="addr-text">Type your business name to see your address.</span></div>
        <button type="button" class="addr-change <?= ($old['slug'] || isset($errors['slug'])) ? 'hidden' : '' ?>" id="addr-edit">Don't like this address? Click here to choose your own →</button>
        <div class="addr-custom <?= ($old['slug'] || isset($errors['slug'])) ? '' : 'hidden' ?>" id="addr-custom">
          <?php if ($prefix): ?><span class="muted"><?= e($prefix) ?></span><?php endif; ?>
          <input type="text" id="slug" name="slug" value="<?= e($old['slug']) ?>" maxlength="30" autocomplete="off" spellcheck="false" placeholder="yourname" aria-describedby="addr-hint">
          <?php if ($suffix): ?><span class="muted"><?= e($suffix) ?></span><?php endif; ?>
        </div>
        <?php if (isset($errors['slug'])): ?><div class="err"><?= e($errors['slug']) ?></div><?php endif; ?>
        <div class="hint" id="addr-hint"><?= $suffix ? 'Your portal address always ends with <b>' . e($suffix) . '</b>.' : 'Your portal address always starts with <b>' . e($prefix) . '</b>.' ?> Use 3–30 letters, numbers or dashes. It can't be changed later.</div>
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
        <input type="password" id="password" name="password" minlength="10" autocomplete="new-password" required data-pw-min="10">
        <?php if (isset($errors['password'])): ?><div class="err"><?= e($errors['password']) ?></div><?php endif; ?>
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
