<?php $portal = tenant_url($t, '/'); $login = tenant_url($t, '/login?welcome=1&email=' . urlencode($email)); ?>
<div style="max-width:620px;margin:10px auto 40px">
  <div class="card" style="text-align:center">
    <div class="success" style="padding:10px 0 0"><div class="tick"><?= icon('check', 54) ?></div></div>
    <h1>Your space is ready!</h1>
    <p class="muted">We created <b><?= e($t['name']) ?></b> with your admin account, kiosk buttons for your industry<?= val('SELECT COUNT(*) FROM students WHERE tenant_id = ?', [$t['id']]) ? ' and sample people to try' : '' ?>.</p>
    <div class="card" style="background:var(--soft);box-shadow:none;margin:18px 0;text-align:left">
      <div class="small muted">Your address</div>
      <div style="font-weight:800;font-size:1.15rem;word-break:break-all"><a href="<?= e($portal) ?>"><?= e($portal) ?></a></div>
      <div class="small muted" style="margin-top:6px">Bookmark it: this is the address for your kiosk tablet and your admin sign-in.</div>
    </div>
    <div class="row" style="justify-content:center">
      <a class="btn btn-primary btn-lg" href="<?= e($login) ?>">Sign in to my space</a>
    </div>
    <?php if (!empty($mailed)): ?><div class="flash info" style="margin-top:16px;text-align:left"><?= icon('mail', 18) ?> <span>We emailed your address, sign-in link and first steps to <b><?= e($email) ?></b>. Check your inbox (and spam folder).</span></div><?php endif; ?>
    <p class="muted small" style="margin-top:14px">Your <?= (int) cfg('trial_days', 7) ?>-day free trial has started. Choose a plan any time in Admin → Billing.</p>
  </div>
</div>
