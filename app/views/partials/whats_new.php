<?php // Shared "What's new" content: hero, latest releases, roadmap. Expects $cta (HTML) for the hero buttons. ?>
<section class="wn-hero">
  <span class="wn-pill"><?= icon('spark', 16) ?> What's new in <?= e(cfg('app_name')) ?></span>
  <h1>Always getting <span>better</span>.</h1>
  <p>New features land every few weeks, many of them requested by customers like you. Here is what just shipped and what is coming next.</p>
  <div class="wn-cta"><?= $cta ?></div>
</section>

<section class="wn-block">
  <div class="wn-head"><h2>Just released</h2><span class="muted small">Newest first</span></div>
  <div class="wn-grid">
    <?php foreach (WHATS_NEW as $n => [$date, $title, $text, $ico, $tag]): ?>
      <article class="wn-card <?= $n === 0 ? 'wn-latest' : '' ?>">
        <div class="wn-card-top"><span class="wn-ic"><?= icon($ico, 20) ?></span><span class="wn-tag"><?= e($tag) ?></span>
          <?php if ($n === 0): ?><span class="wn-new">NEW</span><?php endif; ?></div>
        <h3><?= e($title) ?></h3>
        <p><?= e($text) ?></p>
        <time class="small muted" datetime="<?= e($date) ?>"><?= e(date('M j, Y', strtotime($date))) ?></time>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="wn-block">
  <div class="wn-head"><h2>On the roadmap</h2><span class="muted small">Plans can change; timing is not guaranteed.</span></div>
  <div class="wn-road">
    <?php foreach (ROADMAP_STAGES as $stage => $stageName): ?>
      <div class="wn-lane wn-<?= e($stage) ?>">
        <h3><span class="wn-dot"></span><?= e($stageName) ?></h3>
        <?php foreach (ROADMAP as [$title, $text, $ico, $st]): if ($st !== $stage) { continue; } ?>
          <div class="wn-item"><span class="wn-ic"><?= icon($ico, 18) ?></span><div><b><?= e($title) ?></b><p><?= e($text) ?></p></div></div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
