<?php
$labels = ['active' => ['Active', 'in'], 'trial' => ['Free trial', 'student'], 'grace' => ['Trial ended', 'warn'], 'expired' => ['Subscription needed', 'danger'], 'suspended' => ['Suspended', 'danger']];
[$stateLabel, $stateCls] = $labels[$state];
$current = $plans[$t['plan']] ?? null;
$stripe = stripe_ready();
$wave = wave_ready();
$payStatus = ['paid' => ['Paid', 'in'], 'open' => ['Waiting for payment', 'warn'], 'failed' => ['Failed', 'danger'], 'refunded' => ['Refunded', 'gray']];
?>
<div class="topbar"><div><h1>Billing</h1><div class="muted">Your plan, payment method and invoices.</div></div></div>

<div class="card billing-status">
  <div>
    <span class="badge <?= $stateCls ?>"><?= e($stateLabel) ?></span>
    <h2 style="margin:10px 0 4px"><?= e($current['name'] ?? ucfirst((string) $t['plan'])) ?> plan</h2>
    <p class="muted">
      <?php if ($state === 'trial'): ?>Your free trial ends in <b><?= trial_days_left() ?> day<?= trial_days_left() === 1 ? '' : 's' ?></b> (<?= e(date('M j, Y', strtotime((string) $t['trial_ends_at']))) ?>). Choose a plan below to keep going.
      <?php elseif ($state === 'grace'): ?>Your trial has ended. Choose a plan to unlock your portal. Your kiosk keeps working until <b><?= e(date('M j', strtotime((string) $t['trial_ends_at']) + GRACE_DAYS * 86400)) ?></b>.
      <?php elseif ($state === 'expired'): ?>The kiosk is paused until a plan is chosen. Your people and history are safe.
      <?php elseif ($t['billing_provider'] === 'stripe'): ?>Paid by card through Stripe<?= $t['current_period_end'] ? '. Renews on <b>' . e(date('M j, Y', strtotime($t['current_period_end']))) . '</b>' : '' ?><?= $t['subscription_status'] === 'past_due' ? '. <b style="color:var(--danger)">The last payment failed; please update your card.</b>' : '.' ?>
      <?php elseif ($t['billing_provider'] === 'wave'): ?>Paid by invoice through Wave<?= $t['current_period_end'] ? '. Paid through <b>' . e(date('M j, Y', strtotime($t['current_period_end']))) . '</b>; the next invoice is emailed a week before.' : '.' ?>
      <?php else: ?>Your plan is active.<?php endif; ?>
    </p>
    <?php if ($current): ?><p class="muted small" style="margin:8px 0 0">People: <b><?= number_format($people) ?></b><?= $current['max_people'] ? ' of ' . number_format($current['max_people']) : ' (no limit)' ?> · Staff logins: <b><?= $logins ?></b><?= $current['max_logins'] ? ' of ' . (int) $current['max_logins'] : ' (no limit)' ?><?= ($t['billing_cycle'] ?? 'monthly') === 'annual' ? ' · Billed yearly' : '' ?></p><?php endif; ?>
  </div>
  <?php if ($t['stripe_customer_id'] && $stripe): ?>
    <form method="post" action="<?= e(url('/admin/billing/portal')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('card') ?> Manage card &amp; plan</button></form>
  <?php endif; ?>
</div>

<?php if ($t['wave_invoice_id']): ?>
  <div class="flash info"><?= icon('mail', 18) ?> An invoice has been emailed to you. Your plan activates as soon as it is paid.</div>
<?php endif; ?>

<?php if ($state !== 'active' || !$t['billing_provider']): ?>
<?php $free = 12 - annual_months(); $popular = array_key_first(array_filter($plans, fn($p) => !empty($p['popular']))) ?? array_key_first($plans); ?>
<div class="row" style="margin:24px 0 16px;justify-content:space-between;flex-wrap:wrap;gap:10px">
  <h2 style="margin:0">Choose your plan</h2>
  <div class="cycle-switch" role="group" aria-label="Billing period">
    <a class="<?= $cycle === 'annual' ? 'on' : '' ?>" href="<?= e(url('/admin/billing')) ?>">Yearly<?= $free > 0 ? '<em>' . $free . ' months free</em>' : '' ?></a>
    <a class="<?= $cycle === 'monthly' ? 'on' : '' ?>" href="<?= e(url('/admin/billing?cycle=monthly')) ?>">Monthly</a>
  </div>
</div>
<div class="grid c3">
  <?php foreach ($plans as $k => $p): $feat = $p['features']; $head = ($feat && str_ends_with($feat[0], ':')) ? array_shift($feat) : null;
      $card = $stripe && setting('stripe_price_' . $k . ($cycle === 'annual' ? '_annual' : ''));
      $invoice = $wave && setting('wave_product_' . $k); ?>
    <div class="card price-card <?= $k === $popular ? 'featured' : '' ?>">
      <?php if ($k === $popular): ?><span class="pop">Most popular</span><?php endif; ?>
      <div class="row" style="justify-content:space-between;align-items:center"><h3 style="margin:0"><?= e($p['name']) ?></h3><?php if ($k === $t['plan']): ?><span class="badge gray">Your current plan</span><?php endif; ?></div>
      <?php if ($p['tagline']): ?><p class="muted small" style="margin:4px 0 0"><?= e($p['tagline']) ?></p><?php endif; ?>
      <div class="price">$<?= e(number_format($cycle === 'annual' ? $p['annual'] : $p['amount'], 0)) ?><span class="muted small">/<?= $cycle === 'annual' ? 'year' : 'month' ?></span></div>
      <?php if ($cycle === 'annual' && $free > 0): ?><span class="save">Save $<?= e(number_format($p['amount'] * 12 - $p['annual'], 0)) ?> · <?= $free ?> months free</span>
      <?php else: ?><span class="muted small">or $<?= e(number_format($p['annual'], 0)) ?>/year with <?= $free ?> months free</span><?php endif; ?>
      <ul class="feat"><?php if ($head): ?><li class="plus"><?= e($head) ?></li><?php endif; ?><li><?= e($p['limit']) ?></li><?php foreach ($feat as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
      <div class="stack">
        <?php if ($card): ?>
          <form method="post" action="<?= e(url('/admin/billing/stripe')) ?>"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($k) ?>"><input type="hidden" name="cycle" value="<?= e($cycle) ?>"><button class="btn <?= $k === $popular ? 'btn-primary' : 'btn-ghost' ?> btn-block"><?= icon('card') ?> Pay by card</button></form>
        <?php endif; ?>
        <?php if ($invoice): ?>
          <form method="post" action="<?= e(url('/admin/billing/wave')) ?>" data-confirm="Email a <?= $cycle === 'annual' ? 'yearly ' : '' ?><?= e($p['name']) ?> plan invoice to <?= e(owner_email($t)) ?>?"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($k) ?>"><input type="hidden" name="cycle" value="<?= e($cycle) ?>"><button class="btn btn-ghost btn-block"><?= icon('mail') ?> Pay by invoice</button></form>
        <?php endif; ?>
        <?php if (!$card && !$invoice): ?>
          <form method="post" action="<?= e(url('/admin/billing/request')) ?>" data-confirm="Choose the <?= e($p['name']) ?> plan (<?= $cycle === 'annual' ? 'yearly' : 'monthly' ?>)? We will email your invoice."><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($k) ?>"><input type="hidden" name="cycle" value="<?= e($cycle) ?>"><button class="btn <?= $k === $popular ? 'btn-primary' : 'btn-ghost' ?> btn-block">Choose <?= e($p['name']) ?></button></form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="margin-top:18px">
  <h3><?= icon('dollar') ?> Payments &amp; invoices</h3>
  <div class="table-wrap">
    <table class="table">
      <tr><th>Date</th><th>Description</th><th>Method</th><th>Amount</th><th>Status</th><th></th></tr>
      <?php foreach ($payments as $p): [$sl, $sc] = $payStatus[$p['status']]; ?>
        <tr>
          <td class="nowrap"><?= e(audit_time($p['paid_at'] ?: $p['created_at'])) ?></td>
          <td><?= e($p['description']) ?><?php if ($p['period_start']): ?><br><span class="muted small"><?= e(date('M j', strtotime($p['period_start']))) ?> – <?= e(date('M j, Y', strtotime((string) $p['period_end']))) ?></span><?php endif; ?></td>
          <td><?= e(ucfirst($p['provider'])) ?></td>
          <td class="tnum"><b><?= e(money((int) $p['amount_cents'], $p['currency'])) ?></b></td>
          <td><span class="badge <?= $sc ?>"><?= e($sl) ?></span></td>
          <td class="right"><?php if ($p['invoice_url']): ?><a class="btn btn-sm btn-ghost" href="<?= e($p['invoice_url']) ?>" target="_blank" rel="noopener"><?= $p['status'] === 'open' ? 'Pay now' : 'Invoice' ?></a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="6" class="empty">No payments yet.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
