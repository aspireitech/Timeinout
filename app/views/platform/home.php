<?php
$demoAdmin = $demo ? tenant_url($demo, '/login') : null;
$feed = [ // initial live-feed rows (the script keeps adding more)
    ['AJ', 'Ava Johnson', 'Signed in · with Mom', '8:02', 'in', 's'],
    ['HL', 'Ms. Hannah Lee', 'Clocked in · Teacher', '7:41', 'in', 't'],
    ['MG', 'Mia Garcia', 'Picked up Books · Grandma', '7:58', 'pk', 'p'],
    ['LP', 'Liam Patel', 'Signed in · with Dad', '8:05', 'in', 's'],
];
?>
<link rel="stylesheet" href="<?= asset('css/home.css') ?>">

<!-- ===== Hero ===== -->
<section class="h-hero bleed">
  <div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
  <div class="h-hero-in">
    <div class="h-copy">
      <span class="h-pill"><span class="dot"></span> Live check-in for schools, daycares &amp; after-school programs</span>
      <h1>Happy mornings for <span class="rotator" aria-live="polite"><span class="rw on">students</span><span class="rw">teachers</span><span class="rw">parents</span><span class="rw">front desks</span></span><br>and safe pick-ups, every day.</h1>
      <p class="h-lead">Kids sign in with the parent who drops them off, teachers clock in, families collect homework and books. All on one cheerful tablet screen, with reports in your inbox every morning.</p>
      <div class="h-cta">
        <a class="btn btn-primary btn-lg h-glow" href="<?= e(base_path() . '/signup') ?>">Start free trial</a>
        <?php if ($demo): ?><a class="btn btn-ghost btn-lg" href="#demo"><?= icon('kiosk') ?> Try the live demo</a>
        <?php else: ?><a class="btn btn-ghost btn-lg" href="#how">See how it works</a><?php endif; ?>
      </div>
      <div class="h-trust">
        <span><?= icon('check', 16) ?> Ready in 60 seconds</span>
        <span><?= icon('check', 16) ?> Any tablet or phone</span>
        <span><?= icon('check', 16) ?> No credit card</span>
      </div>
    </div>

    <div class="h-scene" aria-hidden="true">
      <!-- Student girl with backpack, waving -->
      <svg class="char kid-a" viewBox="0 0 160 260">
        <ellipse cx="80" cy="252" rx="46" ry="7" fill="#1F2340" opacity=".12"/>
        <rect x="34" y="110" width="44" height="66" rx="15" fill="#FF7A59"/>
        <rect x="40" y="128" width="30" height="20" rx="6" fill="#FDB44B"/>
        <rect x="58" y="188" width="16" height="56" rx="8" fill="#2D3561"/>
        <rect x="86" y="188" width="16" height="56" rx="8" fill="#2D3561"/>
        <rect x="50" y="238" width="28" height="12" rx="6" fill="#1F2340"/>
        <rect x="84" y="238" width="28" height="12" rx="6" fill="#1F2340"/>
        <path d="M52 112 Q80 100 108 112 L118 200 Q80 210 42 200 Z" fill="#6C5CE7"/>
        <path d="M64 110 L60 168" stroke="#E8673F" stroke-width="7" stroke-linecap="round"/>
        <rect x="40" y="120" width="14" height="58" rx="7" fill="#C68642" transform="rotate(14 47 122)"/>
        <g class="wave"><rect x="100" y="72" width="14" height="54" rx="7" fill="#C68642"/><circle cx="107" cy="70" r="10" fill="#C68642"/></g>
        <circle cx="80" cy="70" r="34" fill="#C68642"/>
        <path d="M45 72 Q44 32 80 32 Q116 32 115 72 Q110 50 80 48 Q54 50 45 72Z" fill="#2B1B17"/>
        <circle cx="42" cy="62" r="13" fill="#2B1B17"/><circle cx="118" cy="62" r="13" fill="#2B1B17"/>
        <circle cx="42" cy="62" r="4" fill="#FF7A59"/><circle cx="118" cy="62" r="4" fill="#FF7A59"/>
        <g class="blink"><circle cx="68" cy="72" r="3.6" fill="#1F2340"/><circle cx="92" cy="72" r="3.6" fill="#1F2340"/></g>
        <circle cx="60" cy="84" r="5" fill="#FF6B81" opacity=".35"/><circle cx="100" cy="84" r="5" fill="#FF6B81" opacity=".35"/>
        <path d="M70 86 Q80 95 90 86" stroke="#1F2340" stroke-width="3" fill="none" stroke-linecap="round"/>
      </svg>

      <!-- Tablet kiosk with a live feed -->
      <div class="device">
        <div class="device-top">
          <span class="d-logo">BF</span><b>Bright Future Academy</b><span class="d-clock" id="h-clock">8:05 AM</span>
        </div>
        <div class="d-roles">
          <span class="r-s"><?= icon('users', 18) ?> Student</span>
          <span class="r-t"><?= icon('teacher', 18) ?> Teacher</span>
          <span class="r-p"><?= icon('box', 18) ?> Pickup</span>
        </div>
        <div class="d-feed-head"><span class="live"></span> Live check-ins</div>
        <ul class="d-feed" id="h-feed">
          <?php foreach ($feed as [$ini, $name, $sub, $time, $cls, $av]): ?>
            <li><span class="av av-<?= $av ?>"><?= $ini ?></span><span class="fw"><b><?= e($name) ?></b><small><?= e($sub) ?></small></span><span class="tm <?= $cls ?>"><?= $time ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Teacher with glasses and clipboard -->
      <svg class="char teach" viewBox="0 0 170 300">
        <ellipse cx="85" cy="292" rx="50" ry="7" fill="#1F2340" opacity=".12"/>
        <rect x="62" y="210" width="18" height="74" rx="9" fill="#3A3F6B"/>
        <rect x="92" y="210" width="18" height="74" rx="9" fill="#3A3F6B"/>
        <rect x="54" y="276" width="30" height="12" rx="6" fill="#1F2340"/>
        <rect x="88" y="276" width="30" height="12" rx="6" fill="#1F2340"/>
        <path d="M50 126 Q86 110 122 126 L128 222 Q86 232 44 222 Z" fill="#00B894"/>
        <path d="M76 120 L86 160 L96 120 Z" fill="#fff"/>
        <path d="M86 160 L86 222" stroke="#00977A" stroke-width="3"/>
        <circle cx="94" cy="178" r="3" fill="#00977A"/><circle cx="94" cy="196" r="3" fill="#00977A"/>
        <rect x="40" y="132" width="15" height="70" rx="7.5" fill="#F1C27D" transform="rotate(8 47 134)"/>
        <g class="clip"><rect x="108" y="150" width="40" height="52" rx="5" fill="#FDB44B"/><rect x="114" y="158" width="28" height="38" rx="3" fill="#fff"/>
          <path d="M118 168h20M118 176h20M118 184h14" stroke="#B8C0E0" stroke-width="3" stroke-linecap="round"/>
          <path d="M118 168l3 3 6-6" stroke="#00B894" stroke-width="3" fill="none" stroke-linecap="round"/></g>
        <rect x="112" y="128" width="15" height="52" rx="7.5" fill="#F1C27D" transform="rotate(-28 119 130)"/>
        <rect x="76" y="100" width="20" height="22" rx="8" fill="#F1C27D"/>
        <circle cx="86" cy="76" r="34" fill="#F1C27D"/>
        <path d="M51 80 Q48 38 86 38 Q124 38 121 80 Q118 58 92 56 Q70 58 60 66 Q54 72 51 80Z" fill="#6B3E26"/>
        <circle cx="86" cy="36" r="15" fill="#6B3E26"/>
        <g class="blink"><circle cx="74" cy="80" r="3.4" fill="#1F2340"/><circle cx="98" cy="80" r="3.4" fill="#1F2340"/></g>
        <circle cx="74" cy="80" r="9" fill="none" stroke="#1F2340" stroke-width="2.5"/><circle cx="98" cy="80" r="9" fill="none" stroke="#1F2340" stroke-width="2.5"/>
        <path d="M83 80h6" stroke="#1F2340" stroke-width="2.5"/>
        <path d="M76 94 Q86 101 96 94" stroke="#1F2340" stroke-width="3" fill="none" stroke-linecap="round"/>
      </svg>

      <!-- Student boy with cap -->
      <svg class="char kid-b" viewBox="0 0 150 230">
        <ellipse cx="75" cy="222" rx="40" ry="6" fill="#1F2340" opacity=".12"/>
        <rect x="56" y="166" width="15" height="50" rx="7.5" fill="#2D3561"/>
        <rect x="80" y="166" width="15" height="50" rx="7.5" fill="#2D3561"/>
        <rect x="48" y="210" width="26" height="11" rx="5.5" fill="#E8673F"/>
        <rect x="78" y="210" width="26" height="11" rx="5.5" fill="#E8673F"/>
        <rect x="98" y="104" width="34" height="50" rx="12" fill="#6C5CE7"/>
        <path d="M46 102 Q75 92 104 102 L108 176 Q75 184 42 176 Z" fill="#0984E3"/>
        <path d="M60 120 Q75 128 90 120" stroke="#fff" stroke-width="4" fill="none" opacity=".6"/>
        <rect x="34" y="108" width="13" height="52" rx="6.5" fill="#8D5524" transform="rotate(10 40 110)"/>
        <rect x="102" y="108" width="13" height="52" rx="6.5" fill="#8D5524" transform="rotate(-10 108 110)"/>
        <circle cx="75" cy="64" r="31" fill="#8D5524"/>
        <path d="M44 58 Q46 28 75 28 Q104 28 106 58 Z" fill="#FF7A59"/>
        <path d="M100 54 Q122 54 124 62 L100 62 Z" fill="#E8673F"/>
        <g class="blink"><circle cx="64" cy="68" r="3.4" fill="#1F2340"/><circle cx="86" cy="68" r="3.4" fill="#1F2340"/></g>
        <path d="M65 80 Q75 88 85 80" stroke="#1F2340" stroke-width="3" fill="none" stroke-linecap="round"/>
      </svg>

      <div class="float-badge fb1"><span class="fb-ic in"><?= icon('check', 16) ?></span><span><b>Ava signed in</b><small>with Sarah (Mom) · 8:02</small></span></div>
      <div class="float-badge fb2"><span class="fb-ic t"><?= icon('clock', 16) ?></span><span><b>Ms. Lee clocked in</b><small>7:41 AM</small></span></div>
      <div class="float-badge fb3"><span class="fb-ic m"><?= icon('mail', 16) ?></span><span><b>Daily report sent</b><small>to the owner</small></span></div>
    </div>
  </div>
</section>

<!-- ===== Numbers ===== -->
<section class="h-stats">
  <div><b data-count="3">3</b><span>taps to sign in</span></div>
  <div><b data-count="60" data-suffix="s">60s</b><span>to create your portal</span></div>
  <div><b data-count="3">3</b><span>flows: student, teacher, pickup</span></div>
  <div><b>24/7</b><span>reports, daily · weekly · monthly</span></div>
</section>

<!-- ===== How it works ===== -->
<section id="how" class="h-section">
  <span class="h-eyebrow">How it works</span>
  <h2 class="h-title">Up and running before the first bell</h2>
  <div class="h-steps">
    <div class="h-step reveal"><span class="n">1</span><div class="si si-1"><?= icon('globe', 28) ?></div><h3>Create your portal</h3><p>Enter your school name. Your own address like <b>yourschool.<?= e(cfg('base_domain')) ?></b> is ready instantly.</p></div>
    <div class="h-step reveal"><span class="n">2</span><div class="si si-2"><?= icon('upload', 28) ?></div><h3>Upload your list</h3><p>Import students with their parents, and your teachers, from an Excel or CSV file.</p></div>
    <div class="h-step reveal"><span class="n">3</span><div class="si si-3"><?= icon('kiosk', 28) ?></div><h3>Put a tablet at the door</h3><p>Families tap their name, pick Mom or Dad, and they're signed in. Reports arrive by email.</p></div>
  </div>
</section>

<!-- ===== Three flows ===== -->
<section class="h-section">
  <span class="h-eyebrow">One screen, three jobs</span>
  <h2 class="h-title">Built for every person who walks through the door</h2>
  <div class="h-flows">
    <div class="flow f-s reveal">
      <div class="flow-ic"><?= icon('users', 30) ?></div>
      <h3>Students &amp; parents</h3>
      <p>Type a few letters, tap the name. Only <b>that child's</b> father, mother or guardian appear, so the right person is recorded every time.</p>
      <div class="mini-chip">Ava Johnson <span>→ Sarah (Mom)</span></div>
    </div>
    <div class="flow f-t reveal">
      <div class="flow-ic"><?= icon('teacher', 30) ?></div>
      <h3>Teachers &amp; staff</h3>
      <p>Start and end the day in two taps. Hours are totalled automatically in the weekly and monthly reports.</p>
      <div class="mini-chip">Ms. Hannah Lee <span>· 38h 20m this week</span></div>
    </div>
    <div class="flow f-p reveal">
      <div class="flow-ic"><?= icon('box', 30) ?></div>
      <h3>Material pickup</h3>
      <p>Parents collect homework folders, books, uniforms or report cards, and you know exactly who took what, and when.</p>
      <div class="mini-chip">Homework folder <span>+ Library books</span></div>
    </div>
  </div>
</section>

<!-- ===== Features ===== -->
<section class="h-section">
  <span class="h-eyebrow">Everything included</span>
  <h2 class="h-title">Simple for families, powerful for the office</h2>
  <div class="feature-grid">
    <?php foreach ([
        ['chart', 'linear-gradient(135deg,#6C5CE7,#A66CFF)', 'Live dashboard', 'See which students and teachers are on site right now.'],
        ['mail', 'linear-gradient(135deg,#E84393,#FD79A8)', 'Reports by email', 'Daily, weekly and monthly summaries to the owner, plus CSV export.'],
        ['upload', 'linear-gradient(135deg,#0984E3,#3EB4FF)', 'Spreadsheet import', 'Upload students with up to four guardians each, and your staff.'],
        ['shield', 'linear-gradient(135deg,#00B894,#00CEC9)', 'Never-signed-out alerts', 'Reports flag anyone who signed in but never signed out.'],
        ['calendar', 'linear-gradient(135deg,#FF7A59,#FDB44B)', 'Any day', 'Forgot yesterday? Record an earlier day with the right time.'],
        ['globe', 'linear-gradient(135deg,#2D3436,#636E72)', 'Your brand', 'Your logo, colors, welcome message and your own domain.'],
    ] as [$ico, $bg, $title, $text]): ?>
      <div class="card feature reveal">
        <div class="fi" style="background:<?= $bg ?>"><?= icon($ico, 22) ?></div>
        <h3><?= e($title) ?></h3>
        <p class="muted" style="margin:0"><?= e($text) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===== Live demo ===== -->
<?php if ($demo): ?>
<section id="demo" class="h-demo bleed">
  <div class="h-demo-in">
    <div>
      <span class="h-eyebrow light">Live demo</span>
      <h2>Try a real school, right now</h2>
      <p>Bright Future Academy is loaded with 32 students, their parents, 8 teachers and six weeks of check-ins. Log in, open the kiosk and sign a student in yourself.</p>
      <a class="btn btn-lg demo-go" href="<?= e($demoAdmin) ?>"><?= icon('kiosk') ?> Open the demo</a>
    </div>
    <div class="cred-cards">
      <?php foreach (DEMO_LOGINS as [$role, , $dEmail, $dPass]): ?>
        <div class="cred">
          <span class="cred-role"><?= $role === 'owner' ? 'Admin login' : 'Front desk (staff) login' ?></span>
          <div class="cred-row"><span>Email</span><code><?= e($dEmail) ?></code></div>
          <div class="cred-row"><span>Password</span><code><?= e($dPass) ?></code></div>
        </div>
      <?php endforeach; ?>
      <div class="cred-url">Portal: <code><?= e($demoAdmin) ?></code></div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ===== Pricing ===== -->
<section id="pricing" class="h-section">
  <span class="h-eyebrow">Pricing</span>
  <h2 class="h-title">Every plan includes every feature</h2>
  <p class="section-sub">Start with a <?= (int) cfg('trial_days', 30) ?>-day free trial. Cancel anytime.</p>
  <div class="pricing">
    <?php $i = 0; foreach ($plans as $key => $p): $i++; ?>
      <div class="card price-card reveal <?= $i === 2 ? 'featured' : '' ?>">
        <?php if ($i === 2): ?><span class="badge student">Most popular</span><?php endif; ?>
        <h3 style="margin-top:8px"><?= e($p['name']) ?></h3>
        <div class="price"><?= e($p['price']) ?></div>
        <p class="muted"><?= e($p['limit']) ?></p>
        <a class="btn <?= $i === 2 ? 'btn-primary' : 'btn-ghost' ?> btn-block" href="<?= e(base_path() . '/signup?plan=' . urlencode($key)) ?>">Choose <?= e($p['name']) ?></a>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===== Final call to action ===== -->
<section class="h-final bleed">
  <h2>Make tomorrow's drop-off the easiest one yet</h2>
  <p>Create your portal in a minute and put it on a tablet at your front door.</p>
  <a class="btn btn-lg demo-go" href="<?= e(base_path() . '/signup') ?>">Start your free trial</a>
</section>

<script src="<?= asset('js/home.js') ?>"></script>
