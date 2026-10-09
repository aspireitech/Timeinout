<?php $qs = http_build_query(array_filter(['tenant' => $tenant ?: '', 'provider' => input('provider')])); $st = ['paid' => 'in', 'open' => 'warn', 'failed' => 'danger', 'refunded' => 'gray']; ?>
<div class="stack" style="margin-bottom:60px">
  <div class="grid c4">
    <div class="tile t1"><?= icon('dollar', 30) ?><div class="num"><?= e(money($stats['month'])) ?></div><div class="lbl">Collected this month</div></div>
    <div class="tile t2"><?= icon('chart', 30) ?><div class="num"><?= e(money((int) round($stats['mrr'] * 100))) ?></div><div class="lbl">Monthly recurring · <?= $stats['paying'] ?> paying</div></div>
    <div class="tile t3"><?= icon('check', 30) ?><div class="num"><?= e(money($stats['all'])) ?></div><div class="lbl">Collected all time</div></div>
    <div class="tile t4"><?= icon('mail', 30) ?><div class="num"><?= e(money($stats['open'])) ?></div><div class="lbl">Open invoices</div></div>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('card') ?> Payment history</h2>
      <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/payments?' . $qs . '&export=csv') ?>"><?= icon('download', 16) ?> Download CSV</a></div>
    <form class="inline-form" method="get" style="margin-bottom:14px">
      <div class="field"><label for="pf-t">Subscriber</label><select id="pf-t" name="tenant"><option value="">All</option><?php foreach ($tenants as $tn): ?><option value="<?= (int) $tn['id'] ?>" <?= $tenant === (int) $tn['id'] ? 'selected' : '' ?>><?= e($tn['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="pf-p">Method</label><select id="pf-p" name="provider"><option value="">All</option><?php foreach (['stripe' => 'Stripe (card)', 'wave' => 'Wave (invoice)', 'manual' => 'Manual'] as $k => $l): ?><option value="<?= $k ?>" <?= input('provider') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-primary">Filter</button>
    </form>
    <div class="table-wrap">
      <table class="table">
        <tr><th>Date (UTC)</th><th>Subscriber</th><th>Method</th><th>Description</th><th>Amount</th><th>Status</th><th></th></tr>
        <?php foreach ($payments as $x): ?>
          <tr>
            <td class="nowrap small tnum"><?= e($x['paid_at'] ?: $x['created_at']) ?></td>
            <td><b><?= e($x['school'] ?? '—') ?></b></td>
            <td><?= e(ucfirst($x['provider'])) ?></td>
            <td class="small"><?= e($x['description']) ?><?php if ($x['period_end']): ?><br><span class="muted">through <?= e($x['period_end']) ?></span><?php endif; ?></td>
            <td class="tnum"><b><?= e(money((int) $x['amount_cents'], $x['currency'])) ?></b></td>
            <td><span class="badge <?= $st[$x['status']] ?>"><?= e(ucfirst($x['status'])) ?></span></td>
            <td><?php if ($x['invoice_url']): ?><a class="small" href="<?= e($x['invoice_url']) ?>" target="_blank" rel="noopener">Invoice</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="7" class="empty">No payments yet.</td></tr><?php endif; ?>
      </table>
    </div>
  </div>
  <div class="card">
    <h3><?= icon('plus') ?> Record a payment received another way</h3>
    <p class="muted small">For a cheque, bank transfer or cash. The subscriber becomes active for the months you enter.</p>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <div class="field"><label for="mp-t">Subscriber</label><select id="mp-t" name="tenant_id" required><option value="">Choose…</option><?php foreach ($tenants as $tn): ?><option value="<?= (int) $tn['id'] ?>"><?= e($tn['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field" style="flex:0 1 130px"><label for="mp-a">Amount ($)</label><input type="number" id="mp-a" name="amount" step="0.01" min="0.01" required></div>
      <div class="field" style="flex:0 1 110px"><label for="mp-m">Months</label><input type="number" id="mp-m" name="months" value="1" min="1" max="24"></div>
      <div class="field"><label for="mp-n">Note</label><input type="text" id="mp-n" name="note" placeholder="Cheque #1042"></div>
      <button class="btn btn-primary">Record payment</button>
    </form>
  </div>
</div>
