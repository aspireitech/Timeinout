<?php $employeesSel = $old['employees'] ?? ''; ?>
<div class="lead-wrap">
  <div class="lead-intro">
    <span class="lead-pill"><?= icon('clock', 16) ?> Live demo</span>
    <h1>See <?= e(cfg('app_name')) ?> in action</h1>
    <p class="muted">Tell us a little about your team. You get instant access to the demo portal, and we will reach out to help you set up.</p>
    <ul class="lead-points">
      <li><?= icon('check', 18) ?> A fully loaded example portal with six weeks of history</li>
      <li><?= icon('check', 18) ?> Kiosk, admin dashboard, reports and audit log</li>
      <li><?= icon('check', 18) ?> A real person follows up, no pressure</li>
    </ul>
  </div>

  <?php if ($sent): ?>
    <div class="lead-card">
      <div class="lead-capsule ok">
        <div class="lead-capsule-ic"><?= icon('check', 26) ?></div>
        <div><b>Thank you, <?= e($old['name']) ?>!</b>
          <span>Your request is in. We will contact you at <?= e($old['email']) ?> shortly.</span></div>
      </div>
      <?php if ($demo): ?>
        <h3 style="margin-top:22px">Open the demo now</h3>
        <div class="lead-creds">
          <?php foreach (DEMO_LOGINS as [$role, , $dEmail, $dPass]): ?>
            <div class="lead-cred"><b><?= $role === 'owner' ? 'Admin login' : 'Front desk (kiosk)' ?></b>
              <div><span>Email</span><code><?= e($dEmail) ?></code></div><div><span>Password</span><code><?= e($dPass) ?></code></div></div>
          <?php endforeach; ?>
        </div>
        <a class="btn btn-primary btn-block" style="margin-top:14px" href="<?= e(tenant_url($demo, '/login')) ?>">Open the demo portal</a>
        <p class="muted small" style="text-align:center;margin-top:8px">The admin sign-in code is shown on screen in the demo.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <form class="lead-card" method="post" action="<?= e(base_path() . '/demo') ?>" novalidate>
      <?= csrf_field() ?>
      <?php if (isset($errors['form'])): ?><div class="flash error"><?= e($errors['form']) ?></div><?php endif; ?>
      <?php if ($src = lead_source()): ?><input type="hidden" name="src" value="<?= e($src) ?>"><?php endif; ?>
      <div class="lead-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="grid c2">
        <div class="field"><label for="l-name">Full name</label>
          <input class="lead-in" type="text" id="l-name" name="name" value="<?= e($old['name']) ?>" autocomplete="name" required autofocus>
          <?php if (isset($errors['name'])): ?><div class="err"><?= e($errors['name']) ?></div><?php endif; ?></div>
        <div class="field"><label for="l-email">Work email</label>
          <input class="lead-in" type="email" id="l-email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" required>
          <?php if (isset($errors['email'])): ?><div class="err"><?= e($errors['email']) ?></div><?php endif; ?></div>
      </div>
      <div class="grid c2">
        <div class="field"><label for="l-company">Organization</label>
          <input class="lead-in" type="text" id="l-company" name="company" value="<?= e($old['company']) ?>" autocomplete="organization" required>
          <?php if (isset($errors['company'])): ?><div class="err"><?= e($errors['company']) ?></div><?php endif; ?></div>
        <div class="field"><label for="l-phone">Phone</label>
          <input class="lead-in" type="tel" id="l-phone" name="phone" value="<?= e($old['phone']) ?>" autocomplete="tel" placeholder="(801) 555-0123" required>
          <?php if (isset($errors['phone'])): ?><div class="err"><?= e($errors['phone']) ?></div><?php endif; ?></div>
      </div>
      <div class="grid c2">
        <div class="field"><label for="l-industry">Industry</label>
          <select class="lead-in" id="l-industry" name="industry" required>
            <option value="">Choose…</option>
            <?php foreach (industries() as $k => $i): ?><option value="<?= e($k) ?>" <?= ($old['industry'] ?? '') === $k ? 'selected' : '' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
          </select>
          <?php if (isset($errors['industry'])): ?><div class="err"><?= e($errors['industry']) ?></div><?php endif; ?></div>
        <div class="field"><label for="l-emp">Number of employees / people</label>
          <select class="lead-in" id="l-emp" name="employees" required>
            <option value="">Choose…</option>
            <?php foreach (EMPLOYEE_RANGES as $k => $label): ?><option value="<?= e($k) ?>" <?= $employeesSel === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
          <?php if (isset($errors['employees'])): ?><div class="err"><?= e($errors['employees']) ?></div><?php endif; ?></div>
      </div>
      <div class="field"><label for="l-msg">Message <span class="muted">(optional)</span></label>
        <div class="lead-capsule-box"><textarea id="l-msg" name="message" rows="4" maxlength="3000" placeholder="What would you like to see? Number of locations, kiosks, anything else…"><?= e($old['message']) ?></textarea></div></div>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Get demo access</button>
      <p class="muted small" style="text-align:center;margin-top:10px">We only use your details to contact you about <?= e(cfg('app_name')) ?>.</p>
    </form>
  <?php endif; ?>
</div>
