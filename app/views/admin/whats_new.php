<?php $cta = '<a class="btn btn-primary" href="#request">' . icon('plus', 16) . ' Request a feature</a><a class="btn btn-ghost" href="' . e(url('/admin/help')) . '">' . icon('help', 16) . ' Help &amp; FAQ</a>'; ?>
<div class="wn">
  <?php require APP_DIR . '/views/partials/whats_new.php'; ?>

  <section class="wn-block" id="request">
    <div class="wn-request">
      <div class="wn-request-intro">
        <span class="wn-pill"><?= icon('heart', 16) ?> Your idea</span>
        <h2>Request a feature</h2>
        <p>Tell us what would make your day easier. Every request is read by our team, and you can follow its status right here.</p>
        <ul class="wn-points"><li><?= icon('check', 16) ?> Describe the problem, not only the solution</li><li><?= icon('check', 16) ?> Tell us how many people it affects</li><li><?= icon('check', 16) ?> We reply with a status: planned, in progress, released…</li></ul>
      </div>
      <form method="post" action="<?= e(url('/admin/whats-new')) ?>" class="wn-form" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="fr-title">Feature name</label><input type="text" id="fr-title" name="title" maxlength="150" value="<?= e($old['title'] ?? '') ?>" placeholder="e.g. Text parents when their child is signed out" required>
          <?php if (isset($errors['title'])): ?><div class="err"><?= e($errors['title']) ?></div><?php endif; ?></div>
        <div class="grid c2">
          <div class="field"><label for="fr-cat">Area</label><select id="fr-cat" name="category"><option value="">Choose…</option><?php foreach (FEATURE_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>" <?= ($old['category'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <?php if (isset($errors['category'])): ?><div class="err"><?= e($errors['category']) ?></div><?php endif; ?></div>
          <div class="field"><label for="fr-pri">How important is it?</label><select id="fr-pri" name="priority"><option value="">Choose…</option><?php foreach (FEATURE_PRIORITIES as $k => $l): ?><option value="<?= e($k) ?>" <?= ($old['priority'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <?php if (isset($errors['priority'])): ?><div class="err"><?= e($errors['priority']) ?></div><?php endif; ?></div>
        </div>
        <div class="field"><label for="fr-det">What should it do?</label><textarea id="fr-det" name="details" rows="4" maxlength="4000" placeholder="Describe how you would use it, step by step." required><?= e($old['details'] ?? '') ?></textarea>
          <?php if (isset($errors['details'])): ?><div class="err"><?= e($errors['details']) ?></div><?php endif; ?></div>
        <div class="field"><label for="fr-prob">What problem does it solve? <span class="muted">(optional)</span></label><textarea id="fr-prob" name="problem" rows="3" maxlength="2000" placeholder="What happens today without it?"><?= e($old['problem'] ?? '') ?></textarea></div>
        <div class="field"><label for="fr-ppl">Who or how many people would use it? <span class="muted">(optional)</span></label><input type="text" id="fr-ppl" name="people" maxlength="60" value="<?= e($old['people'] ?? '') ?>" placeholder="e.g. 3 front-desk staff, 120 families"></div>
        <button class="btn btn-primary btn-lg"><?= icon('spark', 16) ?> Send request</button>
      </form>
    </div>
  </section>

  <section class="wn-block" id="mine">
    <div class="wn-head"><h2>Your requests</h2></div>
    <?php if (!$mine): ?><div class="card muted">No requests yet. Your ideas will appear here with their status.</div>
    <?php else: ?>
      <div class="card"><div class="table-wrap"><table class="table">
        <tr><th>Request</th><th>Sent</th><th>Status</th><th>Our reply</th></tr>
        <?php foreach ($mine as $r): ?>
          <tr><td><b><?= e($r['title']) ?></b><br><span class="small muted"><?= e(FEATURE_CATEGORIES[$r['category']] ?? '') ?> · <?= e(FEATURE_PRIORITIES[$r['priority']] ?? '') ?> · by <?= e((string) $r['user_name']) ?></span></td>
            <td class="small nowrap"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
            <td><span class="wn-status wn-s-<?= e($r['status']) ?>"><?= e(FEATURE_STATUSES[$r['status']] ?? $r['status']) ?></span></td>
            <td class="small"><?= $r['reply'] ? nl2br(e($r['reply'])) : '<span class="muted">—</span>' ?></td></tr>
        <?php endforeach; ?>
      </table></div></div>
    <?php endif; ?>
  </section>
</div>
