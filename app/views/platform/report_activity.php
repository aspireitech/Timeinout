<div style="max-width:520px;margin:30px auto 60px" class="card">
  <?php if (!$d): ?>
    <h2><?= icon('shield') ?> Link not valid</h2>
    <p class="muted">This report link has expired or was changed. Please email <a href="mailto:<?= e(contact_email()) ?>"><?= e(contact_email()) ?></a> and tell us what happened.</p>
  <?php elseif ($done): ?>
    <div class="lead-capsule ok"><div class="lead-capsule-ic"><?= icon('check', 26) ?></div>
      <div><b>Thank you, we have been told.</b><span>Our team will look into it.</span></div></div>
    <p style="margin-top:16px">Please change your password now. Without the emailed code nobody can finish signing in to your account.</p>
  <?php else: ?>
    <h2><?= icon('shield') ?> Report a sign-in you did not request</h2>
    <p class="muted">A sign-in code was sent to <b><?= e($d['e']) ?></b> for <b><?= e($d['p']) ?></b> on <?= e(gmdate('M j, Y H:i', (int) $d['t'])) ?> UTC.</p>
    <form method="post" action="<?= e(base_path() . '/report-activity') ?>">
      <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
      <button class="btn btn-danger btn-lg btn-block">Yes, report this. It was not me.</button>
    </form>
    <p class="muted small" style="margin-top:10px">If it was you, you can close this page.</p>
  <?php endif; ?>
</div>
