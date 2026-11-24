<?php $other = $kind === 'care' ? 'work' : 'care'; $q = ($_s = lead_source()) ? '&src=' . rawurlencode($_s) : ''; ?>
<style>
.ck { max-width: 760px; margin: 10px auto 60px; }
.ck-head { text-align: center; margin-bottom: 18px; }
.ck-head h1 { margin: 6px 0 8px; }
.ck-list { list-style: none; padding: 0; margin: 0; counter-reset: ck; }
.ck-list li { counter-increment: ck; display: grid; grid-template-columns: 28px 1fr; gap: 12px; align-items: start; padding: 12px 4px; border-bottom: 1px solid #E6E8F2; }
.ck-list li::before { content: ""; width: 22px; height: 22px; border: 2px solid #5B4CE0; border-radius: 6px; margin-top: 1px; }
.ck-cta { background: #F3F1FF; border-radius: 18px; padding: 18px; margin-top: 22px; }
.ck-form { display: grid; grid-template-columns: 1fr auto; gap: 8px; margin-top: 10px; }
@media (max-width: 600px) { .ck-form { grid-template-columns: 1fr; } }
@media print { .no-print, header, footer, nav, .sticky-cta { display: none !important; } .ck { margin: 0; } body { background: #fff; } }
</style>
<div class="ck">
  <?php if ($thanks): $GLOBALS['gads_conv'] = 'lead'; ?><div class="flash success no-print">Thank you! We've also emailed you a copy of this checklist.</div><?php endif; ?>
  <div class="ck-head">
    <span class="lead-pill"><?= icon('check', 16) ?> Free checklist</span>
    <h1><?= e($c['title']) ?></h1>
    <p class="muted">For <?= e(strtolower($c['for'])) ?>. <?= e($c['intro']) ?></p>
  </div>
  <div class="card">
    <ol class="ck-list">
      <?php foreach ($c['items'] as $it): ?><li><span><?= e($it) ?></span></li><?php endforeach; ?>
    </ol>
    <p class="muted small" style="margin-top:12px">Score: ____ / 12. Fewer than 9? <?= e(cfg('app_name')) ?> covers the rest with a tablet at the door.</p>
    <p class="no-print" style="margin-top:12px"><button type="button" class="btn btn-ghost" onclick="window.print()">Print this checklist</button>
      <a class="btn btn-ghost" href="<?= e(base_path() . '/checklist/' . $other) ?>"><?= e(CHECKLISTS[$other]['title']) ?></a></p>
  </div>
  <div class="ck-cta no-print">
    <h3 style="margin:0 0 6px">Tick it all off in a week</h3>
    <p class="muted" style="margin:0 0 10px">Try <?= e(cfg('app_name')) ?> free for <?= trial_length() ?> days. No credit card.</p>
    <a class="btn btn-primary" href="<?= e(base_path() . '/signup?industry=' . ($kind === 'care' ? 'school' : 'office') . $q) ?>">Start my free trial</a>
    <a class="btn btn-ghost" href="<?= e(base_path() . '/demo' . ($q ? '?' . ltrim($q, '&') : '')) ?>">Book a demo</a>
    <?php if (!$thanks): ?>
      <form class="ck-form" method="post" action="<?= e(base_path() . '/subscribe') ?>">
        <?= csrf_field() ?><input type="hidden" name="kind" value="<?= e($kind) ?>">
        <div class="lead-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <input class="lead-in" type="email" name="email" placeholder="you@yourbusiness.com" required aria-label="Your email" autocomplete="email">
        <button class="btn btn-primary">Email me a copy</button>
      </form>
      <p class="muted small" style="margin:6px 0 0">We'll send the checklist and occasional tips. Unsubscribe any time.</p>
    <?php endif; ?>
  </div>
</div>
