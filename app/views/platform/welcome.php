<?php $portal = tenant_url($t, '/'); $login = tenant_url($t, '/login?welcome=1&email=' . urlencode($email)); ?>
<div style="max-width:620px;margin:10px auto 40px">
  <div class="card" style="text-align:center">
    <div class="success" style="padding:10px 0 0"><div class="tick"><?= icon('check', 54) ?></div></div>
    <h1>Your space is ready!</h1>
    <p class="muted">We created <b><?= e($t['name']) ?></b> with your admin account, kiosk buttons for your industry<?= val('SELECT COUNT(*) FROM students WHERE tenant_id = ?', [$t['id']]) ? ' and sample people to try' : '' ?>.</p>
    <div class="card" style="background:var(--soft);box-shadow:none;margin:18px 0;text-align:left">
      <div class="small muted">Your address</div>
      <div style="font-weight:800;font-size:1.15rem;word-break:break-all"><a href="<?= e($portal) ?>"><?= e($portal) ?></a></div>
      <?php if (cfg('tenant_url_mode') !== 'subdomain'): ?>
        <div class="small muted" style="margin-top:6px">Once wildcard DNS is on, it will also work at <b><?= e($t['slug'] . '.' . cfg('base_domain')) ?></b>.</div>
      <?php endif; ?>
    </div>
    <div class="row" style="justify-content:center">
      <a class="btn btn-primary btn-lg" href="<?= e($login) ?>">Sign in to my space</a>
    </div>
    <p class="muted small" style="margin-top:14px">Your free trial has started. Choose a plan any time in Admin → Billing.</p>
  </div>
</div>
