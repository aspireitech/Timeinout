<div class="card" style="max-width:520px;margin:40px auto 80px;text-align:center">
  <?php if (!$s): ?>
    <h2>Link not recognized</h2>
    <p class="muted">This unsubscribe link is not valid. Reply to the email you received and we'll remove you by hand.</p>
  <?php elseif ($s['unsubscribed_at']): ?>
    <h2>You're unsubscribed</h2>
    <p class="muted"><b><?= e($s['email']) ?></b> won't get any more emails from us.</p>
  <?php else: ?>
    <h2>Unsubscribe</h2>
    <p class="muted">Stop checklist and news emails from <?= e(cfg('app_name')) ?> to <b><?= e($s['email']) ?></b>?</p>
    <form method="post" action="<?= e(base_path() . '/subscribe/stop?t=' . $token) ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary">Unsubscribe</button>
    </form>
  <?php endif; ?>
</div>
