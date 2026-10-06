<section class="hero">
  <div>
    <span class="badge student">For schools · daycares · after-school programs</span>
    <h1 style="margin-top:14px">Sign in &amp; sign out, <em>beautifully simple.</em></h1>
    <p class="lead">Students sign in with the parent who drops them off, teachers clock in and out, and families pick up materials — all from one friendly screen. Reports land in your inbox every day.</p>
    <div class="row" style="margin-top:22px">
      <a class="btn btn-primary btn-lg" href="<?= e(base_path() . '/signup') ?>">Start your free trial</a>
      <a class="btn btn-ghost btn-lg" href="#how">See how it works</a>
    </div>
    <p class="muted small" style="margin-top:12px">Your own portal at <b>yourschool.<?= e(cfg('base_domain')) ?></b> in under a minute. No credit card needed.</p>
  </div>
  <div class="hero-art">
    <div class="screen">
      <div class="row between"><b>Sunrise Academy</b><span class="muted small"><?= date('g:i A') ?></span></div>
      <h3 style="margin:14px 0 2px">Welcome! Who are you?</h3>
      <div class="mini-roles">
        <div style="background:linear-gradient(135deg,#6C5CE7,#A66CFF)">🎒<br>Student</div>
        <div style="background:linear-gradient(135deg,#00B894,#00CEC9)">🍎<br>Teacher</div>
        <div style="background:linear-gradient(135deg,#FF7A59,#FDB44B)">📦<br>Pickup</div>
      </div>
      <div class="person" style="margin-top:14px;padding:10px;border:2px solid #E6E8F2;border-radius:14px">
        <span class="avatar">AJ</span><div><b>Ava Johnson</b><div class="muted small">Grade 2 · dropped off by Mom</div></div>
        <span class="badge in" style="margin-left:auto">In 8:02</span>
      </div>
    </div>
  </div>
</section>

<h2 class="section-title" id="how">Everything you need, nothing you don't</h2>
<p class="section-sub">Set up in minutes. Works on any tablet, laptop or phone browser.</p>
<div class="feature-grid">
  <?php foreach ([
      ['users', 'linear-gradient(135deg,#6C5CE7,#A66CFF)', 'Student sign in / out', 'Type a few letters, tap the name, choose Mom or Dad — only that child\'s guardians are shown.'],
      ['teacher', 'linear-gradient(135deg,#00B894,#00CEC9)', 'Teacher time clock', 'Teachers and staff sign in and out. Hours are totalled for you.'],
      ['box', 'linear-gradient(135deg,#FF7A59,#FDB44B)', 'Material pickup', 'Parents record picking up homework, books, uniforms or anything you list.'],
      ['upload', 'linear-gradient(135deg,#0984E3,#3EB4FF)', 'CSV upload', 'Import students with their parents, and staff, from a spreadsheet.'],
      ['mail', 'linear-gradient(135deg,#E84393,#FD79A8)', 'Daily, weekly, monthly reports', 'Summaries emailed to the owner automatically, plus CSV export.'],
      ['globe', 'linear-gradient(135deg,#2D3436,#636E72)', 'Your brand, your address', 'Your logo, colors and welcome message at yourname.' . cfg('base_domain') . ' or your own domain.'],
  ] as [$ico, $bg, $title, $text]): ?>
    <div class="card feature">
      <div class="fi" style="background:<?= $bg ?>"><?= icon($ico, 22) ?></div>
      <h3><?= e($title) ?></h3>
      <p class="muted" style="margin:0"><?= e($text) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<h2 class="section-title" id="pricing">Simple pricing</h2>
<p class="section-sub">Every plan includes every feature. Start with a <?= (int) cfg('trial_days', 30) ?>-day free trial.</p>
<div class="pricing">
  <?php $i = 0; foreach ($plans as $key => $p): $i++; ?>
    <div class="card price-card <?= $i === 2 ? 'featured' : '' ?>">
      <?php if ($i === 2): ?><span class="badge student">Most popular</span><?php endif; ?>
      <h3 style="margin-top:8px"><?= e($p['name']) ?></h3>
      <div class="price"><?= e($p['price']) ?></div>
      <p class="muted"><?= e($p['limit']) ?></p>
      <a class="btn <?= $i === 2 ? 'btn-primary' : 'btn-ghost' ?> btn-block" href="<?= e(base_path() . '/signup?plan=' . urlencode($key)) ?>">Choose <?= e($p['name']) ?></a>
    </div>
  <?php endforeach; ?>
</div>
