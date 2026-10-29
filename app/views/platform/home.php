<?php // Homepage (approved concept: docs/mockup/home-v2.html). Uses its own layout: layouts/home.php ?>
<div class="wrap">
  <nav class="top" aria-label="Main">
    <a class="logo" href="<?= e(base_path()) ?>/"><span class="logo-mark"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#F2A93B" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg></span><?= e(cfg('app_name')) ?></a>
    <div class="nav-links"><a href="#industries">Industries</a><a href="#how">How it works</a><a href="#demo">Live demo</a><a href="#security">Security</a><a href="#pricing">Pricing</a><a href="<?= e(base_path()) ?>/whats-new">What's new</a></div>
    <div class="nav-cta"><a class="btn btn-ghost" href="<?= e(base_path()) ?>/login">Sign in</a><a class="btn btn-primary" href="<?= e(base_path()) ?>/signup">Start free trial</a></div>
  </nav>

  <section class="hero">
    <div>
      <span class="eyebrow">Time &amp; attendance for any front door</span>
      <h1>Know who's in, who's out, and <span class="tick">exactly when.</span></h1>
      <p class="lead">A simple sign-in and sign-out kiosk for employees, members, students and visitors. Put a tablet at the door, and get live attendance, hours and reports in your inbox.</p>
      <div class="hero-cta"><a class="btn btn-primary" href="<?= e(base_path()) ?>/signup">Start free trial</a><a class="btn btn-ghost" href="<?= e(base_path()) ?>/demo">Book a live demo</a></div>
      <div class="checks"><span>Ready in 60 seconds</span><span>Any tablet or phone</span><span>No credit card</span></div>
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

  <section class="block" id="industries">
    <div class="head">
      <span class="eyebrow">Industries</span>
      <h2>One check-in app for every kind of front door</h2>
      <p>Rename the buttons to fit your world: employees, members, students, patients, volunteers or visitors.</p>
    </div>
    <div class="industries">
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=office"><span class="ic" style="background:#EEEBFD;color:#5B4CE0"><svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M11 21v-3h2v3"/></svg></span><b>Offices</b><small>Employee time clock</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=school"><span class="ic" style="background:#FFF1DC;color:#B66A00"><svg viewBox="0 0 24 24"><path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c0 1.5 3 3 6 3s6-1.5 6-3v-5M22 9v6"/></svg></span><b>Schools &amp; daycare</b><small>Drop-off and pick-up</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=clinic"><span class="ic" style="background:#E3F7F0;color:#0E8A66"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><path d="M12 8v8M8 12h8"/></svg></span><b>Clinics &amp; care homes</b><small>Staff and visitor log</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=construction"><span class="ic" style="background:#FFE9E2;color:#C2421E"><svg viewBox="0 0 24 24"><path d="M3 18h18M5 18v-3a7 7 0 0 1 14 0v3M10 8.5V12M14 8.5V12"/><path d="M12 8V6"/></svg></span><b>Construction</b><small>Crew on site</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=retail"><span class="ic" style="background:#E6F1FD;color:#1A6FC4"><svg viewBox="0 0 24 24"><path d="M6 7h12l-1 13H7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg></span><b>Retail</b><small>Shift start and end</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=restaurant"><span class="ic" style="background:#FCEBF3;color:#B22B6B"><svg viewBox="0 0 24 24"><path d="M4 9h12v4a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z"/><path d="M16 10h2a2 2 0 0 1 0 4h-2M7 4v2M11 4v2"/></svg></span><b>Restaurants &amp; cafés</b><small>Staff hours</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=gym"><span class="ic" style="background:#EEEBFD;color:#5B4CE0"><svg viewBox="0 0 24 24"><path d="M6 7v10M18 7v10M3 10v4M21 10v4M6 12h12"/></svg></span><b>Gyms &amp; studios</b><small>Member check-in</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=warehouse"><span class="ic" style="background:#FFF1DC;color:#B66A00"><svg viewBox="0 0 24 24"><path d="M2 7h11v9H2zM13 10h4l4 3.5V16h-8"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg></span><b>Warehouses</b><small>Shift and gate log</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=events"><span class="ic" style="background:#E3F7F0;color:#0E8A66"><svg viewBox="0 0 24 24"><path d="M3 5h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4z"/><path d="M14 5v14" stroke-dasharray="2 2.5"/></svg></span><b>Events &amp; venues</b><small>Volunteer and guest sign-in</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=tutoring"><span class="ic" style="background:#FFE9E2;color:#C2421E"><svg viewBox="0 0 24 24"><path d="M12 6c-2-1.5-5-2-8-1.5V19c3-.5 6 0 8 1.5 2-1.5 5-2 8-1.5V4.5C17 4 14 4.5 12 6z"/><path d="M12 6v14.5"/></svg></span><b>Tutoring centers</b><small>Sessions and pickups</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=nonprofit"><span class="ic" style="background:#E6F1FD;color:#1A6FC4"><svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg></span><b>Nonprofits</b><small>Volunteer hours</small></a>
      <a class="ind" href="<?= e(base_path()) ?>/signup?industry=field"><span class="ic" style="background:#FCEBF3;color:#B22B6B"><svg viewBox="0 0 24 24"><path d="M12 21s-6-5.7-6-11a6 6 0 0 1 12 0c0 5.3-6 11-6 11z"/><circle cx="12" cy="10" r="2.2"/></svg></span><b>Field services</b><small>Clock in at each site</small></a>
    </div>
  </section>

  <section class="block" id="how">
    <div class="head"><span class="eyebrow">How it works</span><h2>Up and running before the next shift</h2></div>
    <div class="steps">
      <div class="step"><h3>Create your space</h3><p>Pick your industry and your own address, like <span class="mono"><?= e(cfg('tenant_url_mode') === 'subdomain' ? 'utsjkumon.' . cfg('base_domain') : cfg('base_domain') . '/s/utsjkumon') ?></span>. It's ready in under a minute.</p></div>
      <div class="step"><h3>Add your people</h3><p>Upload a spreadsheet of employees, members or students, with their contacts.</p></div>
      <div class="step"><h3>Put a tablet at the door</h3><p>People tap their name to sign in or out. You get live attendance and emailed reports.</p></div>
    </div>
  </section>

  <section class="block">
    <div class="head"><span class="eyebrow">One kiosk, three jobs</span><h2>Built for everyone who walks through your door</h2></div>
    <div class="flows">
      <div class="flow fl1"><h3>Team &amp; members</h3><p>Type a few letters, tap your name, clock in. Hours are totalled for payroll.</p><span class="chip">Maria Gomez <span class="mono">· 38h 20m this week</span></span></div>
      <div class="flow fl2"><h3>Visitors &amp; drop-offs</h3><p>Record who arrived, who they came with, and who left with whom.</p><span class="chip">J. Lee <span class="mono">· in 09:15 · out 11:02</span></span></div>
      <div class="flow fl3"><h3>Pickups &amp; handovers</h3><p>Log packages, keys, equipment or materials, and who collected them.</p><span class="chip">Laptop #14 <span class="mono">· by Sam Ortiz</span></span></div>
    </div>
  </section>

  <section class="demo-band" id="demo">
    <div><span class="eyebrow" style="color:#FDE7B5">Live demo</span><h2>Try a real example, right now</h2>
      <p>A sample portal loaded with people, contacts, visitors and six weeks of history. Tell us a little about your team and open it instantly: sign in, open the kiosk and clock someone in yourself.</p>
      <a class="btn btn-primary" href="<?= e(base_path()) ?>/demo">Get demo access</a></div>
    <div class="creds">
      <div class="cred"><b>What you'll see</b><div><span>Kiosk</span><code>clock in &amp; out in seconds</code></div><div><span>Admin</span><code>people, reports, audit log</code></div></div>
      <div class="cred"><b>Takes 30 seconds</b><div><span>Form</span><code>name, email, team size</code></div><div><span>Access</span><code>instant</code></div></div>
    </div>
  </section>

  <section class="secure" id="security">
    <div><span class="eyebrow" style="color:var(--amber)">Security</span><h2>Your people's data, locked down</h2>
      <p>Names, contacts and hours are personal. <?= e(cfg('app_name')) ?> protects them like a bank protects logins.</p></div>
    <div class="sec-list">
      <div class="sec-item"><b>Emailed sign-in codes</b><span>Admins confirm every sign-in with a one-time code.</span></div>
      <div class="sec-item"><b>Full audit log</b><span>Every change, sign-in and report: who, when and the result.</span></div>
      <div class="sec-item"><b>Encrypted contacts</b><span>Phone numbers and emails encrypted with AES-256.</span></div>
      <div class="sec-item"><b>Kiosk-only staff</b><span>Front-desk logins can't open settings or records.</span></div>
    </div>
  </section>

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
          <a class="btn <?= $p['popular'] ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(base_path() . '/signup?plan=' . urlencode($key)) ?>">Start free trial</a></div>
      <?php endforeach; ?>
    </div>
    <p class="pay">Start with a <?= (int) cfg('trial_days', 30) ?>-day free trial on any plan. Pay by card with Stripe, or by invoice through Wave. Need more than 500 people or several sites? <a href="<?= e(base_path()) ?>/demo?src=pricing">Talk to us</a>.</p>
  </section>

  <section class="final">
    <h2>Your front door, on the clock.</h2>
    <p>Create your space in a minute and put it on a tablet today.</p>
    <a class="btn btn-primary" href="<?= e(base_path()) ?>/signup">Start free trial</a>
  </section>
  <footer><div class="camp-more">Solutions: <?php foreach (CAMPAIGNS as $k => $x): ?><a href="<?= e(base_path() . '/for/' . $k) ?>"><?= e($x['nav']) ?></a><?php endforeach; ?></div>
    © <?= date('Y') ?> <?= e(cfg('app_name')) ?> · <a href="<?= e(base_path()) ?>/login">Sign in to your space</a></footer>
</div>

