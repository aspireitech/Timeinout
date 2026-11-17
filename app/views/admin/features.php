<?php
$p = PACKS[$pack];
// Only released features are shown; unfinished ones stay hidden until they ship
$free = array_filter($avail, fn($f) => $f['pack'] === null && $f['ready']);
$paid = array_filter($avail, fn($f) => $f['pack'] !== null && $f['ready']);
$packActive = ($t['addon_status'] ?? '') === 'active';
$inTrial = billing_state($t) === 'trial';
$toggle = function (string $key, array $f, bool $usable) use ($on) {
    $checked = in_array($key, $on, true) ? 'checked' : '';
    $state = !$f['ready'] ? '<span class="badge gray">Coming soon</span>' : ($usable ? '' : '<span class="badge warn">Needs the pack</span>');
    return '<label class="check" style="align-items:flex-start;padding:12px 0;border-top:1px solid var(--line, #ECEEF6)">'
        . '<input type="checkbox" name="f[]" value="' . e($key) . '" ' . $checked . ' ' . ($f['ready'] && $usable ? '' : 'disabled') . '>'
        . '<span><b>' . e($f['name']) . '</b> ' . $state . '<br><span class="muted small">' . e($f['what']) . '</span></span></label>';
};
?>
<div class="page-head"><div><h1>Features &amp; add-ons</h1><p class="muted">Turn on only what you need. Nothing changes for your kiosk or people until you switch a feature on.</p></div></div>
<form method="post" class="stack" style="max-width:900px">
  <?= csrf_field() ?>
  <div class="card">
    <h2><?= icon('check') ?> Included in every plan</h2>
    <?php foreach ($free as $key => $f): ?><?= $toggle($key, $f, true) ?>
      <?php if ($key === 'signout_reminders'): ?>
        <div class="grid c2" style="margin:0 0 12px 30px">
          <div class="field" style="margin:0"><label for="r-mode">Who gets the reminder</label>
            <select id="r-mode" name="signout_mode"><?php foreach (REMINDER_MODES as $mk => $ml): ?><option value="<?= e($mk) ?>" <?= ($t['signout_mode'] ?? 'both') === $mk ? 'selected' : '' ?>><?= e($ml) ?></option><?php endforeach; ?></select></div>
          <label class="check" style="align-self:end"><input type="checkbox" name="signout_weekdays" value="1" <?= ($t['signout_weekdays'] ?? 1) ? 'checked' : '' ?>> <span><b>No reminders on Saturday and Sunday</b><br><span class="muted small">Missed sign-outs from Friday, Saturday and Sunday are emailed on Monday morning instead.</span></span></label>
          <div class="field" style="margin:0;grid-column:1 / -1"><label for="r-close">Closing-time check (optional): your closing time</label>
            <input type="time" id="r-close" name="closing_time" value="<?= e(substr((string) ($t['closing_time'] ?? ''), 0, 5)) ?>" style="max-width:160px">
            <span class="muted small">Leave empty for none. Within the hour after this time, if anyone is still signed in today, your report recipients get one email listing them, with who brought each child and their phone number. Nothing is sent when everyone has left.</span></div>
          <p class="muted small" style="grid-column:1 / -1;margin:0">Reminders are sent at your report hour (<?= (int) ($t['report_hour'] ?? 7) ?>:00) from reports@, replies go to <?= e(reminder_reply_to($t) ?? 'your administrator') ?>. Staff and parents need an email on file. You get no extra email: your <b>daily report</b> lists who was not signed out, who was reminded and who keeps forgetting<?= empty($t['report_daily']) ? ' <b style="color:#B4232A">(the daily report is off: turn it on in Admin → Reports)</b>' : '' ?>.</p>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($paid): ?>
  <div class="card" style="border:2px solid var(--brand, #5B4CE0)">
    <div class="card-head">
      <h2><?= icon('shield') ?> <?= e($p['name']) ?> pack</h2>
      <span class="badge <?= $packActive ? 'in' : 'student' ?>"><?= $packActive ? 'Active' : '$' . PACK_PRICE . ' / month' ?></span>
    </div>
    <p class="muted small" style="margin-top:0"><?= e($p['pitch']) ?>
      <?php if (!$packActive && $inTrial): ?><b>Free to try during your trial.</b><?php endif; ?></p>
    <?php foreach ($paid as $key => $f): ?><?= $toggle($key, $f, pack_active($t)) ?><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div><button class="btn btn-primary">Save features</button></div>
</form>

<div class="stack" style="max-width:900px;margin-top:16px">
  <?php if ($paid): ?>
  <div class="card">
    <h2><?= icon('card') ?> Add or stop the <?= e($p['name']) ?> pack</h2>
    <?php if ($packActive): ?>
      <p>The <?= e($p['name']) ?> pack is active ($<?= PACK_PRICE ?>/month).</p>
      <form method="post" action="<?= e(url('/admin/features/request')) ?>" data-confirm="Ask to stop the <?= e($p['name']) ?> pack?"><?= csrf_field() ?><input type="hidden" name="what" value="pack_cancel"><button class="btn btn-ghost btn-sm">Stop the pack</button></form>
    <?php elseif (($t['addon_status'] ?? '') === 'requested'): ?>
      <p><span class="badge warn">Requested</span> We have your request and will confirm by email within one business day.</p>
    <?php else: ?>
      <p class="muted small">$<?= PACK_PRICE ?>/month on top of your plan, monthly, cancel any time.</p>
      <form method="post" action="<?= e(url('/admin/features/request')) ?>"><?= csrf_field() ?><input type="hidden" name="what" value="pack"><button class="btn btn-primary btn-sm">Add <?= e($p['name']) ?> for $<?= PACK_PRICE ?>/month</button></form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head"><h2><?= icon('heart') ?> Support</h2><span class="badge <?= support_premium($t) ? 'in' : 'gray' ?>"><?= support_premium($t) ? 'Premium Support' : 'Regular Support' ?></span></div>
    <div class="grid c2">
      <div>
        <h3 style="margin:0 0 6px">Regular Support <span class="muted small">· included</span></h3>
        <ul class="small" style="margin:0;padding-left:18px">
          <li>Email support, Monday–Friday 8 AM–6 PM Mountain time</li>
          <li>Help center and guides</li>
        </ul>
      </div>
      <div>
        <h3 style="margin:0 0 6px">Premium Support <span class="muted small">· $<?= SUPPORT_PRICE ?>/month</span></h3>
        <ul class="small" style="margin:0;padding-left:18px">
          <li>24/7 for urgent problems (site or kiosk down), answered within 1 hour</li>
          <li>Direct line to your technical account manager</li>
          <li>One-click help from inside your portal</li>
          <li>Proactive monitoring: we contact you first if something is wrong</li>
          <li>Setup and data import done for you, monthly health check</li>
        </ul>
      </div>
    </div>
    <p class="small" style="margin-top:12px"><b>Focus on your business. We'll look after your front door.</b></p>
    <?php if (support_premium($t)): ?>
      <form method="post" action="<?= e(url('/admin/features/request')) ?>" data-confirm="Ask to stop Premium Support?"><?= csrf_field() ?><input type="hidden" name="what" value="support_cancel"><button class="btn btn-ghost btn-sm">Stop Premium Support</button></form>
    <?php elseif (($t['support_status'] ?? '') === 'requested'): ?>
      <p><span class="badge warn">Requested</span> We will confirm by email within one business day.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('/admin/features/request')) ?>"><?= csrf_field() ?><input type="hidden" name="what" value="support"><button class="btn btn-primary btn-sm">Get Premium Support for $<?= SUPPORT_PRICE ?>/month</button></form>
    <?php endif; ?>
  </div>
</div>
