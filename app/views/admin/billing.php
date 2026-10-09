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
      <?php elseif ($state === 'grace'): ?>Your trial has ended. The portal stays open until <b><?= e(date('M j', strtotime((string) $t['trial_ends_at']) + GRACE_DAYS * 86400)) ?></b>. Choose a plan to avoid interruption.
      <?php elseif ($state === 'expired'): ?>The kiosk is paused until a plan is chosen. Your people and history are safe.
      <?php elseif ($t['billing_provider'] === 'stripe'): ?>Paid by card through Stripe<?= $t['current_period_end'] ? '. Renews on <b>' . e(date('M j, Y', strtotime($t['current_period_end']))) . '</b>' : '' ?><?= $t['subscription_status'] === 'past_due' ? '. <b style="color:var(--danger)">The last payment failed; please update your card.</b>' : '.' ?>
      <?php elseif ($t['billing_provider'] === 'wave'): ?>Paid by invoice through Wave<?= $t['current_period_end'] ? '. Paid through <b>' . e(date('M j, Y', strtotime($t['current_period_end']))) . '</b>; the next invoice is emailed a week before.' : '.' ?>
      <?php else: ?>Your plan is active.<?php endif; ?>
    </p>
  </div>
  <?php if ($t['stripe_customer_id'] && $stripe): ?>
    <form method="post" action="<?= e(url('/admin/billing/portal')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('card') ?> Manage card &amp; plan</button></form>
  <?php endif; ?>
</div>

<?php if ($t['wave_invoice_id']): ?>
  <div class="flash info"><?= icon('mail', 18) ?> An invoice has been emailed to you. Your plan activates as soon as it is paid.</div>
<?php endif; ?>

<?php if ($state !== 'active' || !$t['billing_provider']): ?>
<h2 style="margin:24px 0 12px">Choose a plan</h2>
<?php if (!$stripe && !$wave): ?><div class="flash info">Online payment is being set up. Please contact your service provider to subscribe.</div><?php endif; ?>
<div class="grid c3">
  <?php foreach ($plans as $k => $p): ?>
    <div class="card price-card <?= $k === $t['plan'] ? 'featured' : '' ?>">
      <h3><?= e($p['name']) ?></h3>
      <div class="price"><?= e($p['price']) ?></div>
      <p class="muted"><?= e($p['limit']) ?></p>
      <div class="stack">
        <?php if ($stripe && setting('stripe_price_' . $k)): ?>
          <form method="post" action="<?= e(url('/admin/billing/stripe')) ?>"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($k) ?>"><button class="btn btn-primary btn-block"><?= icon('card') ?> Pay by card</button></form>
        <?php endif; ?>
        <?php if ($wave && setting('wave_product_' . $k)): ?>
          <form method="post" action="<?= e(url('/admin/billing/wave')) ?>" data-confirm="Email a <?= e($p['name']) ?> plan invoice to <?= e(owner_email($t)) ?>?"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($k) ?>"><button class="btn btn-ghost btn-block"><?= icon('mail') ?> Pay by invoice (Wave)</button></form>
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
