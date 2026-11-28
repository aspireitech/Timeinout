<?php $sq = lead_source() ? 'src=' . lead_source() : ''; ?>
<?php // Homepage (approved concept: docs/mockup/home-v2.html). Uses its own layout: layouts/home.php ?>
<div class="wrap">
  <nav class="top" aria-label="Main">
    <a class="logo" href="<?= e(base_path()) ?>/"><span class="logo-mark"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#F2A93B" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg></span><?= e(cfg('app_name')) ?></a>
    <div class="nav-links"><a href="#video">Demo video</a><a href="#how">How it works</a><a href="#pricing">Pricing</a><a href="<?= e(base_path()) ?>/demo<?= $sq ? '?' . $sq : '' ?>">Book a demo</a><a href="<?= e(base_path()) ?>/whats-new">What's new</a></div>
    <div class="nav-cta"><a class="btn btn-ghost" href="<?= e(base_path()) ?>/login">Sign in</a><a class="btn btn-primary" href="<?= e(base_path()) ?>/signup<?= $sq ? '?' . $sq : '' ?>">Start free trial</a></div>
  </nav>

  <section class="hero">
    <div>
      <span class="eyebrow">Replace the paper sign-in sheet</span>
      <h1>Sign in and out at the door. <span class="tick">Know who's here.</span></h1>
      <p class="lead">One tablet by the door: staff clock in, parents sign children out, visitors sign in. You see who is here right now, and hours and reports arrive in your inbox.</p>
      <?php if ($fl = founding_left()): ?><p class="offer-pill"><b>Founding offer:</b> first 3 months free, then 50% off for life · <b><?= e((string) $fl) ?> of <?= FOUNDING_SLOTS ?> places left</b></p><?php endif; ?>
      <div class="hero-cta"><a class="btn btn-primary" href="<?= e(base_path()) ?>/signup<?= $sq ? '?' . $sq : '' ?>">Start free, no card</a><a class="btn btn-ghost" href="#video">Watch the 40-second demo</a></div>
      <div class="checks"><span><?= founding_left() ? FOUNDING_TRIAL_DAYS : trial_length() ?> days free</span><span>We set it up for you</span><?php if (free_plan_on()): ?><span>Free plan up to <?= FREE_PLAN['max_people'] ?> people</span><?php endif; ?></div>
    </div>

    <div class="scene" aria-hidden="true">
      <div class="halo"></div>
      <div class="machine">
        <svg viewBox="0 0 250 300">
          <rect x="20" y="10" width="210" height="280" rx="26" fill="#161B36"/>
          <rect x="34" y="24" width="182" height="164" rx="18" fill="#232A52"/>
          <circle cx="125" cy="105" r="66" fill="#FFFDF6"/>
          <circle cx="125" cy="105" r="66" fill="none" stroke="#F2A93B" stroke-width="5"/>
          <g stroke="#161B36" stroke-width="3" stroke-linecap="round">
            <path d="M125 47v8M125 155v8M67 105h8M175 105h8"/>
            <path d="M154 55l-3 5M175 76l-5 3M175 134l-5-3M154 155l-3-5M96 155l3-5M75 134l5-3M75 76l5 3M96 55l3 5" stroke-width="2"/>
          </g>
          <line class="hand hr" x1="125" y1="105" x2="125" y2="72" stroke="#161B36" stroke-width="6" stroke-linecap="round"/>
          <line class="hand min" x1="125" y1="105" x2="125" y2="56" stroke="#161B36" stroke-width="4" stroke-linecap="round"/>
          <line class="hand sec" x1="125" y1="112" x2="125" y2="52" stroke="#E8613C" stroke-width="2" stroke-linecap="round"/>
          <circle cx="125" cy="105" r="5" fill="#E8613C"/>
          <rect x="62" y="266" width="126" height="12" rx="6" fill="#0B0F24"/>
          <circle cx="70" cy="222" r="11" fill="#0FA97A"/><circle cx="180" cy="222" r="11" fill="#E8613C"/>
          <text x="70" y="250" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="10" font-weight="700" fill="#C4C7E0">IN</text>
          <text x="180" y="250" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="10" font-weight="700" fill="#C4C7E0">OUT</text>
        </svg>
      </div>
      <div class="card-slot">
        <div class="punch">
          <div class="name" id="punch-name">Maria Gomez · Front desk</div>
          <div class="rows"><i></i><i></i><i></i><i></i><i></i><i></i></div>
          <div class="stamp in" id="punch-stamp">IN 08:02</div>
        </div>
      </div>
      <div class="float f1"><span class="dot" style="background:var(--in)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><path d="M5 12l5 5L20 7"/></svg></span><span><b>Maria clocked in</b><small class="mono">08:02 · Warehouse A</small></span></div>
      <div class="float f2"><span class="dot" style="background:var(--brand)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/></svg></span><span><b>Visitor signed in</b><small class="mono">09:15 · J. Lee, contractor</small></span></div>
      <div class="float f3"><span class="dot" style="background:var(--amber)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/></svg></span><span><b>Package picked up</b><small class="mono">11:40 · by Sam Ortiz</small></span></div>
      <div class="float f4"><span class="dot" style="background:var(--ink)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><path d="M4 20V10M10 20V4M16 20v-7"/></svg></span><span><b>Weekly hours report</b><small class="mono">Mon 07:00 · emailed</small></span></div>
    </div>
  </section>

  <section class="block" id="video">
    <div class="head"><span class="eyebrow">See it in 40 seconds</span><h2>What it looks like at your front door</h2></div>
    <div class="videos">
      <figure><video controls preload="none" playsinline poster="<?= e(asset('video/safe-pickup.jpg')) ?>" src="<?= e(asset('video/safe-pickup.mp4')) ?>"></video><figcaption>Childcare, schools, tutoring: safe pickups</figcaption></figure>
      <figure><video controls preload="none" playsinline poster="<?= e(asset('video/staff-hours.jpg')) ?>" src="<?= e(asset('video/staff-hours.mp4')) ?>"></video><figcaption>Offices, clinics, shops: staff hours for payroll</figcaption></figure>
    </div>
  </section>

  <section class="block" id="how">
    <div class="head"><span class="eyebrow">What it does</span><h2>Three jobs, one tablet</h2></div>
    <div class="flows">
      <div class="flow fl1"><h3>Safe pickups</h3><p>Parents tap their child's name and choose who is picking up. Only adults on file, or a named relative. Every drop-off and pickup is on record.</p></div>
      <div class="flow fl2"><h3>Staff hours</h3><p>Staff tap their name or scan a QR badge. Hours add themselves up; download them for payroll in one click.</p></div>
      <div class="flow fl3"><h3>Who is here now</h3><p>A live list on any phone or computer, an emergency roll call in one tap, and missed sign-outs caught the next morning.</p></div>
    </div>
    <div class="built">Built for: <?php foreach (['schools' => 'Childcare & after-school', 'tutoring' => 'Tutoring centers', 'dental' => 'Dental offices', 'healthcare' => 'Clinics', 'offices' => 'Small offices'] as $k => $l): ?><a href="<?= e(base_path() . '/for/' . $k . ($sq ? '?' . $sq : '')) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
    <div class="steps" style="margin-top:28px">
      <div class="step"><h3>Sign up in a minute</h3><p>Your own address, like <span class="mono"><?= e(cfg('tenant_url_mode') === 'subdomain' ? 'yourcenter.' . cfg('base_domain') : cfg('base_domain') . '/s/yourcenter') ?></span>. No card.</p></div>
      <div class="step"><h3>Send us your list</h3><p>Excel, CSV or a photo of your roster. We set it up for you, free.</p></div>
      <div class="step"><h3>Put a tablet at the door</h3><p>Any iPad, Android tablet or phone. That's it.</p></div>
    </div>
  </section>

  <?php require APP_DIR . '/views/partials/founder.php'; ?>

  <section class="block" id="pricing">
    <div class="head"><span class="eyebrow">Pricing</span><h2>Simple plans that grow with you.</h2><p>Every plan has the full kiosk, safe pickups and reports. Start free, no credit card.</p></div>
    <?php $free = 12 - annual_months(); ?>
    <input type="radio" name="cycle" id="cyc-m" class="cyc" checked><input type="radio" name="cycle" id="cyc-y" class="cyc">
    <div class="cycle" role="group" aria-label="Billing period"><label for="cyc-m">Monthly</label><label for="cyc-y">Yearly<?php if ($free > 0): ?> <em><?= $free ?> months free</em><?php endif; ?></label></div>
    <div class="prices">
      <?php foreach ($plans as $key => $p): $feat = $p['features']; $head = ($feat && str_ends_with($feat[0], ':')) ? array_shift($feat) : null; ?>
        <div class="price <?= $p['popular'] ? 'pop' : '' ?>"><?php if ($p['popular']): ?><span class="badge-pop">Most popular</span><?php endif; ?><b><?= e($p['name']) ?></b>
          <?php if ($p['tagline']): ?><p class="tagline"><?= e($p['tagline']) ?></p><?php endif; ?>
          <div class="amt mo">$<?= e(number_format($p['amount'], 0)) ?><small>/month</small></div>
          <div class="amt yr">$<?= e(number_format($p['annual'], 0)) ?><small>/year</small></div>
          <div class="note mo">Billed monthly. Cancel anytime.</div>
          <div class="note yr">That's $<?= e(number_format($p['annual'] / 12, fmod($p['annual'], 12) == 0 ? 0 : 2)) ?>/month<?php if ($free > 0): ?>, you save $<?= e(number_format($p['amount'] * 12 - $p['annual'], 0)) ?><?php endif; ?>.</div>
          <ul><?php if ($head): ?><li class="plus"><?= e($head) ?></li><?php endif; ?><li><?= e($p['limit']) ?></li>
            <?php foreach ($feat ?: ['Every feature included', 'PDF & CSV reports, audit log'] as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
          <a class="btn <?= $p['popular'] ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(base_path() . '/signup?plan=' . urlencode($key) . ($sq ? '&' . $sq : '')) ?>">Start free trial</a></div>
      <?php endforeach; ?>
    </div>
    <?php if (free_plan_on()): ?><p class="free-strip"><b>Free plan:</b> up to <?= FREE_PLAN['max_people'] ?> people and <?= FREE_PLAN['max_logins'] ?> admin login, free for as long as you like. <a href="<?= e(base_path()) ?>/signup<?= $sq ? '?' . $sq : '' ?>">Start free</a></p><?php endif; ?>
    <p class="secure-line">Private by design: emailed sign-in codes, encrypted contacts, full audit log.</p>
    <p class="pay">Start with a <?= founding_left() ? FOUNDING_TRIAL_DAYS . "-day free trial (founding offer)" : trial_length() . "-day free trial" ?> on any plan. Pay by card with Stripe, or by invoice through Wave. Need more than 500 people or several sites? <a href="<?= e(base_path()) ?>/demo?src=pricing">Talk to us</a>.</p>
  </section>

  <section class="final">
    <h2>Ditch the paper sign-in sheet this week.</h2>
    <p>Start free in a minute. Send us your list and we'll do the setup.</p>
    <a class="btn btn-primary" href="<?= e(base_path()) ?>/signup<?= $sq ? '?' . $sq : '' ?>">Start free trial</a>
  </section>
  <footer><div class="camp-more">Solutions: <?php foreach (CAMPAIGNS as $k => $x): ?><a href="<?= e(base_path() . '/for/' . $k) ?>"><?= e($x['nav']) ?></a><?php endforeach; ?></div>
    © <?= date('Y') ?> <?= e(cfg('app_name')) ?> · <a href="<?= e(base_path()) ?>/login">Sign in to your space</a></footer>
</div>

