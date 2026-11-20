<div class="card" style="max-width:520px;margin:40px auto 80px;text-align:center">
  <?php if (!$p): ?>
    <h2>Link not recognized</h2>
    <p class="muted">This unsubscribe link is not valid. Reply to the email you received and we'll remove you by hand.</p>
  <?php elseif ($done): ?>
    <h2>You're unsubscribed</h2>
    <p class="muted"><b><?= e($p['email']) ?></b> won't get any more emails from us. Sorry for the interruption.</p>
  <?php else: ?>
    <h2>Unsubscribe</h2>
    <p class="muted">Stop all emails from <?= e(cfg('app_name')) ?> to <b><?= e($p['email']) ?></b>?</p>
    <form method="post" action="<?= e(base_path() . '/unsubscribe?t=' . $token) ?>">
      <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
      <button class="btn btn-primary">Unsubscribe</button>
    </form>
  <?php endif; ?>
</div>
