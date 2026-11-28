<?php $t = tenant(); ?>
<div class="card" style="max-width:440px;margin:0 auto;text-align:center;padding:26px 22px">
  <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:14px">
    <?php if ($t['logo_path']): ?><img src="<?= e(upload_url($t['logo_path'])) ?>" alt="" style="height:36px"><?php else: ?><span class="logo-mark"><?= e(initials($t['name'])) ?></span><?php endif; ?>
    <b><?= e($t['name']) ?></b>
  </div>
  <?php if (!$valid): ?>
    <h1 style="font-size:1.3rem">This link is not valid</h1>
    <p class="muted">Please use the link from the latest reminder email, or contact <?= e($t['name']) ?>.</p>
  <?php elseif ($done): ?>
    <h1 style="font-size:1.3rem">Reminders stopped</h1>
    <p class="muted">You will not get missed sign-out reminders from <?= e($t['name']) ?> any more. Please still sign out at the kiosk at every pickup.</p>
  <?php else: ?>
    <h1 style="font-size:1.3rem">Stop missed sign-out reminders?</h1>
    <p class="muted">These emails only come when a pickup was not signed out at the kiosk.</p>
    <form method="post" action="<?= e(url('/reminders/stop?g=' . (int) $g . '&k=' . $k)) ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary">Stop the reminders</button>
    </form>
  <?php endif; ?>
</div>
