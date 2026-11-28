<?php $badge = ['ok' => 'in', 'warn' => 'warn', 'fail' => 'danger', 'info' => 'gray']; $word = ['ok' => 'OK', 'warn' => 'Check', 'fail' => 'Failed', 'info' => 'Info']; ?>
<div class="stack" style="margin-bottom:60px">
  <div class="card">
    <div class="card-head"><h2><?= icon('shield') ?> Restore check</h2></div>
    <p class="muted small">Tests every credential and dependency for real: encryption key, databases, folders, scheduled jobs and backups, email (Microsoft 365 sign-in and inbox), Stripe, Wave, Google sign-in, DNS and SSL. Run it after a restore, after moving to new hosting, or any time something seems wrong. No secret is ever shown. The same check runs on the server with <code>php cron/verify_restore.php</code>.</p>
    <form method="post" action="<?= e(base_path() . '/super/check') ?>" class="row" style="gap:10px;align-items:flex-end;flex-wrap:wrap">
      <?= csrf_field() ?>
      <div class="field" style="margin:0;min-width:280px"><label for="c-to">Also send a real test email to (optional)</label><input type="email" id="c-to" name="send_test" value="<?= e((string) $to) ?>" placeholder="you@example.com"></div>
      <button class="btn btn-primary">Run the restore check</button>
    </form>
  </div>
  <?php if ($rows !== null): $c = restore_check_counts($rows); ?>
    <div class="card">
      <div class="card-head">
        <h2>Result</h2>
        <span><span class="badge in"><?= $c['ok'] ?> OK</span> <span class="badge warn"><?= $c['warn'] ?> to check</span> <span class="badge danger"><?= $c['fail'] ?> failed</span></span>
      </div>
      <div class="table-wrap"><table class="table">
        <tr><th>Area</th><th>Check</th><th>Result</th><th>Where to fix</th></tr>
        <?php foreach ($rows as $r): ?>
          <tr class="small">
            <td class="nowrap"><b><?= e($r['area']) ?></b></td>
            <td><?= e($r['check']) ?><br><span class="muted"><?= e($r['detail']) ?></span></td>
            <td><span class="badge <?= $badge[$r['status']] ?>"><?= $word[$r['status']] ?></span></td>
            <td class="muted"><?= $r['status'] === 'ok' ? '' : e($r['fix']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table></div>
    </div>
  <?php endif; ?>
</div>
