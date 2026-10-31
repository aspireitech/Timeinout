<?php // Campaign landing page (/for/<key>), content in app/lib/campaigns.php. Uses the homepage look (layouts/home.php). ?>
<?php
// ?src=linkedin on the link becomes "linkedin-schools", so Leads and Subscribers show both the channel and the page
$src = substr((lead_source() ? lead_source() . '-' : '') . $key, 0, 40);
$demo = e(base_path() . '/demo?src=' . $src);
$trial = e(base_path() . '/signup?industry=' . $c['industry'] . '&src=' . $src);
?>
<div class="wrap camp" style="--seg:<?= e($c['color']) ?>;--seg-bg:<?= e($c['bg']) ?>">
  <nav class="top" aria-label="Main">
    <a class="logo" href="<?= e(base_path()) ?>/"><span class="logo-mark"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#F2A93B" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg></span><?= e(cfg('app_name')) ?></a>
    <div class="nav-links"><a href="#features">Features</a><a href="#how">How it works</a><a href="<?= e(base_path()) ?>/whats-new">What's new</a><a href="<?= e(base_path()) ?>/#pricing">Pricing</a></div>
    <div class="nav-cta"><a class="btn btn-ghost" href="<?= e(base_path()) ?>/login">Sign in</a><a class="btn btn-primary" href="<?= $trial ?>">Start free trial</a></div>
  </nav>

  <section class="hero">
    <div>
      <span class="eyebrow"><?= e($c['eyebrow']) ?></span>
      <h1><?= $c['title'] ?></h1>
      <p class="lead"><?= e($c['lead']) ?></p>
      <div class="hero-cta"><a class="btn btn-primary" href="<?= $trial ?>">Start free trial</a><a class="btn btn-ghost" href="<?= $demo ?>">Book a live demo</a></div>
      <div class="checks"><span>Free <?= (int) cfg('trial_days', 7) ?>-day trial</span><span>No credit card</span><span>Any tablet or phone</span></div>
    </div>
    <div class="camp-visual" aria-hidden="true">
      <div class="camp-device">
        <div class="camp-bar"><span></span><span></span><span></span><b>Live activity</b></div>
        <?php foreach ($c['events'] as $n => [$kind, $what, $when]): ?>
          <div class="camp-ev" style="animation-delay:<?= $n * .35 ?>s"><i class="k-<?= e($kind) ?>"><?= $kind === 'out' ? 'OUT' : ($kind === 'pickup' ? 'PICK' : 'IN') ?></i><div><b><?= e($what) ?></b><span><?= e($when) ?></span></div></div>
        <?php endforeach; ?>
        <div class="camp-report"><span>Daily report</span><b>Emailed 07:00</b></div>
      </div>
    </div>
  </section>

  <section class="block">
    <div class="head"><span class="eyebrow">Sound familiar?</span><h2>The front door is where the paperwork piles up</h2></div>
    <div class="camp-pains">
      <?php foreach ($c['pains'] as $p): ?><div class="camp-pain"><span>✕</span><?= e($p) ?></div><?php endforeach; ?>
    </div>
  </section>

  <section class="block" id="features">
    <div class="head"><span class="eyebrow">What you get</span><h2>Everything you need at the door, nothing you don't</h2></div>
    <div class="camp-feats">
      <?php foreach ($c['features'] as $n => [$t, $d]): ?><div class="camp-feat"><span class="camp-num"><?= sprintf('%02d', $n + 1) ?></span><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div><?php endforeach; ?>
    </div>
  </section>

  <section class="block" id="how">
    <div class="head"><span class="eyebrow">How it works</span><h2>Live before your next shift</h2></div>
    <div class="steps">
      <div class="step"><h3>We set up your portal</h3><p>Your own address and private database, branded with your logo and colors.</p></div>
      <div class="step"><h3>Upload your people</h3><p>Send your spreadsheet as it is. Match the columns once and everyone is in.</p></div>
      <div class="step"><h3>Put a tablet at the door</h3><p>People tap their name to sign in or out. You get live attendance and reports by email.</p></div>
    </div>
  </section>

  <section class="final">
    <h2>See it with your own front door in mind.</h2>
    <p>A 20-minute live demo, tailored to <?= e(strtolower($c['nav'])) ?>.</p>
    <div class="hero-cta" style="justify-content:center"><a class="btn btn-primary" href="<?= $trial ?>">Start free trial</a><a class="btn btn-ghost" href="<?= $demo ?>">Book a live demo</a></div>
  </section>
  <footer>
    <div class="camp-more">Also for: <?php foreach (CAMPAIGNS as $k => $x): if ($k === $key) { continue; } ?><a href="<?= e(base_path() . '/for/' . $k) ?>"><?= e($x['nav']) ?></a><?php endforeach; ?></div>
    © <?= date('Y') ?> <?= e(cfg('app_name')) ?> · <a href="<?= e(base_path()) ?>/">Home</a> · <a href="<?= e(base_path()) ?>/login">Sign in to your space</a>
  </footer>
</div>
