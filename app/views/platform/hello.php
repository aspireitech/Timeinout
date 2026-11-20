<?php $offer = outreach_offer(); $signup = base_path() . '/signup?src=outreach' . ($p ? '&industry=' . rawurlencode(prospect_pitch((string) $p['kind'])['industry'] ?? '') : ''); ?>
<div class="lead-wrap">
  <div class="lead-intro">
    <span class="lead-pill"><?= icon('clock', 16) ?> 10-minute call</span>
    <h1><?= $p ? 'Hi ' . e($p['first_name'] ?: $p['org']) . '!' : 'Let\'s talk' ?></h1>
    <p class="muted">Tell us when to call. We will show you <?= e(cfg('app_name')) ?> with your own people and answer every question. No pressure, no card.</p>
    <ul class="lead-points">
      <li><?= icon('check', 18) ?> A tablet at the door: people tap their name to sign in and out</li>
      <li><?= icon('check', 18) ?> Live list of who is here, and reports in your inbox every morning</li>
      <li><?= icon('check', 18) ?> We set it up for you: send us your list in any format</li>
    </ul>
    <?php if ($offer): ?><p style="background:#FFF6E5;border-radius:12px;padding:12px 14px;margin-top:14px"><b><?= e($offer) ?></b></p><?php endif; ?>
  </div>
  <?php if ($sent): $GLOBALS['gads_conv'] = 'lead'; ?>
    <div class="lead-card">
      <div class="lead-capsule ok"><div class="lead-capsule-ic"><?= icon('check', 26) ?></div>
        <div><b>Thank you, <?= e($old['name']) ?>!</b><span>We will call you at <?= e($old['phone']) ?> (<?= e(strtolower($times[$old['time']])) ?>), usually within one business day.</span></div></div>
      <h3 style="margin-top:22px">Can't wait?</h3>
      <p class="muted">Start your free 7-day trial now. It takes a minute and needs no credit card.</p>
      <a class="btn btn-primary btn-block" href="<?= e($signup) ?>">Start my free trial</a>
    </div>
  <?php else: ?>
    <form class="lead-card" method="post" action="<?= e(base_path() . '/hello' . ($token ? '?t=' . $token : '')) ?>" novalidate>
      <?= csrf_field() ?>
      <?php if (isset($errors['form'])): ?><div class="flash error"><?= e($errors['form']) ?></div><?php endif; ?>
      <div class="lead-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="grid c2">
        <div class="field"><label for="h-name">Your name</label><input class="lead-in" type="text" id="h-name" name="name" value="<?= e($old['name']) ?>" autocomplete="name" required autofocus>
          <?php if (isset($errors['name'])): ?><div class="err"><?= e($errors['name']) ?></div><?php endif; ?></div>
        <div class="field"><label for="h-phone">Phone</label><input class="lead-in" type="tel" id="h-phone" name="phone" value="<?= e($old['phone']) ?>" autocomplete="tel" placeholder="(801) 555-0123" required>
          <?php if (isset($errors['phone'])): ?><div class="err"><?= e($errors['phone']) ?></div><?php endif; ?></div>
      </div>
      <div class="field"><label for="h-time">Best time to call</label>
        <select class="lead-in" id="h-time" name="time"><?php foreach ($times as $k => $l): ?><option value="<?= e($k) ?>" <?= $old['time'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="grid c2">
        <div class="field"><label for="h-company">Organization</label><input class="lead-in" type="text" id="h-company" name="company" value="<?= e($old['company']) ?>" autocomplete="organization" required>
          <?php if (isset($errors['company'])): ?><div class="err"><?= e($errors['company']) ?></div><?php endif; ?></div>
        <div class="field"><label for="h-email">Email (optional)</label><input class="lead-in" type="email" id="h-email" name="email" value="<?= e($old['email']) ?>" autocomplete="email">
          <?php if (isset($errors['email'])): ?><div class="err"><?= e($errors['email']) ?></div><?php endif; ?></div>
      </div>
      <button class="btn btn-primary btn-lg btn-block">Call me back</button>
      <p class="muted small" style="text-align:center;margin-top:10px">Prefer to try it yourself? <a href="<?= e($signup) ?>">Start a free 7-day trial</a>, no credit card.</p>
    </form>
  <?php endif; ?>
</div>
