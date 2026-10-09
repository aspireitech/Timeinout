<?php
$t = tenant();
$min = tenant_now()->modify('-' . (int) $t['allow_past_days'] . ' days')->format('Y-m-d');
$yesterday = tenant_now()->modify('-1 day')->format('Y-m-d');
?>
<div id="kiosk" data-api="<?= e(url('/api')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-today="<?= e($today) ?>" data-tz="<?= e(tenant_tz()) ?>">
  <header class="k-top">
    <div class="k-org">
      <?php if ($t['logo_path']): ?><img src="<?= e(upload_url($t['logo_path'])) ?>" alt=""><?php else: ?><span class="logo-mark"><?= e(initials($t['name'])) ?></span><?php endif; ?>
      <span><?= e($t['name']) ?></span>
    </div>
    <div class="k-clock"><div class="time" id="k-time">--:--</div><div class="date" id="k-date"></div></div>
  </header>

  <main class="k-main">
    <!-- Day picker (shown on every step) -->
    <div class="daybar" id="daybar">
      <button type="button" class="on" data-day="<?= e($today) ?>"><?= icon('calendar', 16) ?> Today</button>
      <?php if ($t['allow_past_days'] > 0): ?>
        <button type="button" data-day="<?= e($yesterday) ?>">Yesterday</button>
        <label class="daypick">Other day <input type="date" id="day-input" min="<?= e($min) ?>" max="<?= e($today) ?>" value="<?= e($today) ?>"></label>
      <?php endif; ?>
    </div>

    <!-- Step 1: who are you? -->
    <section data-step="role">
      <h1 class="k-title"><?= e($t['welcome_title'] ?: 'Welcome!') ?></h1>
      <p class="k-sub"><?= e($t['welcome_text'] ?: 'Who are you?') ?></p>
      <div class="role-grid">
        <?php foreach ($tiles as $tl): ?>
          <button class="role-card" style="background:<?= e(tile_gradient($tl['color'])) ?>" data-tile="<?= (int) $tl['id'] ?>"
                  data-type="<?= e($tl['type']) ?>" data-contact="<?= (int) $tl['needs_contact'] ?>" data-label="<?= e($tl['label']) ?>">
            <span class="ri"><?= icon($tl['icon'], 34) ?></span>
            <span><span class="rt"><?= e($tl['label']) ?></span><span class="rd"><?= e($tl['subtitle']) ?></span></span>
          </button>
        <?php endforeach; ?>
        <?php if (!$tiles): ?><p class="k-hint">No buttons are set up yet. An admin can add them in Admin → Kiosk tiles.</p><?php endif; ?>
      </div>
    </section>

    <!-- Step 2: find your name -->
    <section data-step="search" class="hidden">
      <div class="k-panel">
        <button class="k-back" data-back="role"><?= icon('back', 18) ?> Back</button>
        <h2 id="search-title">Find your name</h2>
        <div class="k-search">
          <?= icon('search', 24) ?>
          <input type="search" id="search" placeholder="Type the first few letters…" autocomplete="off" autocapitalize="words" spellcheck="false">
        </div>
        <div class="pick-grid" id="results"></div>
        <div class="k-hint" id="search-hint">Start typing a first or last name.</div>
      </div>
    </section>

    <!-- Step 3: which parent / guardian? -->
    <section data-step="guardian" class="hidden">
      <div class="k-panel">
        <button class="k-back" data-back="search"><?= icon('back', 18) ?> Back</button>
        <div class="who" id="who-g"></div>
        <h2 id="guardian-title">Who is with them?</h2>
        <div class="pick-grid" id="guardians"></div>
      </div>
    </section>

    <!-- Step 4a: sign in / sign out -->
    <section data-step="action" class="hidden">
      <div class="k-panel">
        <button class="k-back" id="action-back"><?= icon('back', 18) ?> Back</button>
        <div class="who" id="who-a"></div>
        <div class="status-note hidden" id="status-note"></div>
        <div class="field hidden time-field"><label>Time</label><input type="time" class="past-time"></div>
        <div class="action-grid">
          <button class="action in" data-action="sign_in"><?= icon('in', 40) ?> Sign In<small id="in-sub">Arriving / drop-off</small></button>
          <button class="action out" data-action="sign_out"><?= icon('out', 40) ?> Sign Out<small id="out-sub">Leaving / pick-up</small></button>
        </div>
        <div class="k-error hidden"></div>
      </div>
    </section>

    <!-- Step 4b: material pickup -->
    <section data-step="material" class="hidden">
      <div class="k-panel">
        <button class="k-back" id="mat-back"><?= icon('back', 18) ?> Back</button>
        <div class="who" id="who-m"></div>
        <h2>What are you picking up?</h2>
        <div class="mat-grid">
          <?php foreach ($materials as $m): ?>
            <label class="mat"><input type="checkbox" name="mat" value="<?= (int) $m['id'] ?>"> <?= e($m['name']) ?></label>
          <?php endforeach; ?>
        </div>
        <div class="field"><label for="mat-other">Something else?</label><input type="text" id="mat-other" maxlength="120" placeholder="Optional — type the item"></div>
        <div class="field hidden time-field"><label>Time</label><input type="time" class="past-time"></div>
        <button class="btn btn-lg btn-block" id="mat-submit" style="background:linear-gradient(135deg,var(--material-a),var(--material-b));color:#fff"><?= icon('check') ?> Confirm pickup</button>
        <div class="k-error hidden"></div>
      </div>
    </section>

    <!-- Visitors: type your details, or tap your name to sign out -->
    <section data-step="visitor" class="hidden">
      <div class="k-panel">
        <button class="k-back" data-back="role"><?= icon('back', 18) ?> Back</button>
        <h2 id="visitor-title">Visitor</h2>
        <div class="visitor-grid">
          <div>
            <h3>Arriving? Sign in</h3>
            <div class="field"><label for="v-name">Your full name</label><input type="text" id="v-name" maxlength="120" autocomplete="off" autocapitalize="words"></div>
            <div class="field"><label for="v-company">Company (optional)</label><input type="text" id="v-company" maxlength="120" autocomplete="off"></div>
            <div class="field"><label for="v-host">Who are you visiting? (optional)</label><input type="text" id="v-host" maxlength="120" autocomplete="off"></div>
            <div class="field hidden time-field"><label>Time</label><input type="time" class="past-time"></div>
            <button class="btn btn-lg btn-block btn-accent" id="v-submit"><?= icon('in') ?> Sign in</button>
          </div>
          <div>
            <h3>Leaving? Tap your name</h3>
            <div class="pick-grid one" id="v-list"><div class="k-hint">No visitors are signed in.</div></div>
          </div>
        </div>
        <div class="k-error hidden"></div>
      </div>
    </section>

    <!-- Step 5: done -->
    <section data-step="done" class="hidden">
      <div class="k-panel success">
        <div class="tick"><?= icon('check', 56) ?></div>
        <h2 id="done-title">All set!</h2>
        <p class="muted" id="done-detail"></p>
        <button class="btn btn-primary btn-lg" id="done-btn">Done</button>
        <div class="bar-timer"><i></i></div>
      </div>
    </section>
  </main>

  <footer class="k-foot">
    <?php if (is_admin()): ?><a href="<?= e(url('/admin')) ?>"><?= icon('settings', 14) ?> Admin</a>
    <?php elseif ($ku = current_user()): ?><span class="muted">Kiosk signed in as <?= e($ku['name']) ?></span> · <a href="<?= e(url('/logout')) ?>">Sign out</a>
    <?php else: ?><a href="<?= e(url('/login')) ?>">Staff login</a><?php endif; ?>
  </footer>
</div>
