<?php $mail = ($m['driver'] ?? 'mail') === 'mail'; ?>
<div style="max-width:900px;margin:0 auto 60px" class="stack">
  <div class="card">
    <h2><?= icon('settings') ?> Email, payments &amp; security</h2>
    <p style="margin:0 0 14px"><a class="btn btn-primary btn-sm" href="<?= e(base_path() . '/super/check') ?>"><?= icon('shield', 16) ?> Run the restore check</a> <span class="muted small">Tests every credential and dependency (database, encryption key, Microsoft 365, Stripe, DNS, cron) and says where to fix anything that fails.</span></p>
    <h3><?= icon('mail') ?> Email delivery (SMTP)</h3>
    <p class="muted">Used for sign-in codes and report emails for every school. Get these details from your hosting (cPanel → Email Accounts → Connect Devices) or an email service such as Google Workspace, Microsoft 365, SendGrid or Amazon SES.</p>
    <form method="post">
      <?= csrf_field() ?>
      <div class="grid c2">
        <div class="field"><label for="s-driver">Send with</label>
          <select id="s-driver" name="driver"><option value="graph" <?= ($m['driver'] ?? '') === 'graph' ? 'selected' : '' ?>>Microsoft 365 (Graph API, set up in config.php)</option><option value="smtp" <?= ($m['driver'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP server</option><option value="mail" <?= $mail ? 'selected' : '' ?>>PHP mail() (often lands in spam)</option></select></div>
        <div class="field"><label for="s-host">SMTP host</label><input type="text" id="s-host" name="host" value="<?= e($m['host'] ?? '') ?>" placeholder="mail.yourdomain.com"></div>
        <div class="field"><label for="s-port">Port</label><input type="number" id="s-port" name="port" value="<?= e($m['port'] ?? 587) ?>"><div class="hint">587 with TLS, or 465 with SSL.</div></div>
        <div class="field"><label for="s-secure">Encryption</label>
          <select id="s-secure" name="secure"><?php foreach (['tls' => 'TLS (STARTTLS, port 587)', 'ssl' => 'SSL (port 465)', '' => 'None (not recommended)'] as $k => $l): ?><option value="<?= $k ?>" <?= ($m['secure'] ?? 'tls') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="s-user">Username</label><input type="text" id="s-user" name="user" value="<?= e($m['user'] ?? '') ?>" autocomplete="off"></div>
        <div class="field"><label for="s-pass">Password</label><input type="password" id="s-pass" name="pass" autocomplete="new-password" placeholder="<?= $hasPass ? 'Saved. Leave empty to keep it' : '' ?>"><div class="hint">Stored encrypted.</div></div>
        <div class="field"><label for="s-from">From email</label><input type="email" id="s-from" name="from_email" value="<?= e($m['from_email'] ?? '') ?>"></div>
        <div class="field"><label for="s-fromname">From name</label><input type="text" id="s-fromname" name="from_name" value="<?= e($m['from_name'] ?? '') ?>"></div>
      </div>
      <h3 style="margin-top:14px"><?= icon('mail') ?> Alerts to you</h3>
      <p class="muted small">Alerts go to <b><?= e(contact_email()) ?></b>: new sign-ups, payments received or failed, cancellations, demo requests, invoice requests, subdomain to-dos, trials that need a call, sign-in warnings and the Monday scorecard. Add more addresses to get a copy of every one.</p>
      <div class="field"><label for="s-alerts">Also send alerts to (up to 5, separated by commas)</label><input type="text" id="s-alerts" name="alert_emails" value="<?= e(setting('alert_emails', '')) ?>" placeholder="you@example.com"></div>
      <h3 style="margin-top:14px"><?= icon('mail') ?> Trial follow-up</h3>
      <p class="muted small">The hourly cron emails trial owners who are stuck (no people imported, no check-ins yet, 2 days and 1 day left), alerts you about trials that need a call, and sends you a growth scorecard every Monday morning.</p>
      <div class="grid c2">
        <label class="check" style="grid-column:1 / -1"><input type="checkbox" name="nurture_on" value="1" <?= setting('nurture_on', '1') === '1' ? 'checked' : '' ?>>
          <span>Send trial follow-up emails, alerts and the Monday scorecard</span></label>
        <div class="field" style="grid-column:1 / -1"><label for="s-promo">Promotion line for the last trial emails (optional)</label><input type="text" id="s-promo" name="trial_promo" maxlength="200" value="<?= e(setting('trial_promo', '')) ?>" placeholder="e.g. Use code FOUNDING25 at checkout for 25% off your first year"></div>
      </div>
      <h3 style="margin-top:14px"><?= icon('card') ?> Stripe (card subscriptions)</h3>
      <p class="muted small">From Stripe Dashboard → Developers. Create a webhook pointing to <code><?= e(scheme() . '://' . cfg('base_domain') . base_path() . '/webhooks/stripe') ?></code> with the events
        <code>checkout.session.completed</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code>, <code>invoice.paid</code> and <code>invoice.payment_failed</code>.
        Turn on the Customer Portal in Stripe → Settings → Billing.</p>
      <div class="grid c2">
        <div class="field"><label for="s-sk">Secret key</label><input type="password" id="s-sk" name="stripe_secret" autocomplete="off" placeholder="<?= secret('stripe_secret') ? 'Saved. Leave empty to keep it' : 'sk_live_…' ?>"></div>
        <label class="check" style="grid-column:1 / -1"><input type="checkbox" name="stripe_tax" value="1" <?= setting('stripe_tax', '0') === '1' ? 'checked' : '' ?>>
          <span>Collect sales tax with <b>Stripe Tax</b><br><span class="muted small">Turn on Stripe Tax in your Stripe dashboard first (add your Utah registration) and set the product tax code to <code>txcd_10103001</code> (SaaS, business use). Checkout then asks for the billing address and adds the tax on top of the price.</span></span></label>
        <div class="field"><label for="s-wh">Webhook signing secret</label><input type="password" id="s-wh" name="stripe_webhook_secret" autocomplete="off" placeholder="<?= secret('stripe_webhook_secret') ? 'Saved. Leave empty to keep it' : 'whsec_…' ?>"></div>
        <?php foreach (plans() as $pk => $pl): ?>
          <div class="field"><label for="sp-<?= e($pk) ?>">Monthly price ID for <?= e($pl['name']) ?> ($<?= e(number_format($pl['amount'], 0)) ?>/month)</label><input type="text" id="sp-<?= e($pk) ?>" name="stripe_price_<?= e($pk) ?>" value="<?= e(setting('stripe_price_' . $pk, '')) ?>" placeholder="price_… (not the prod_… product ID)"></div>
          <div class="field"><label for="spa-<?= e($pk) ?>">Yearly price ID for <?= e($pl['name']) ?> ($<?= e(number_format($pl['annual'], 0)) ?>/year)</label><input type="text" id="spa-<?= e($pk) ?>" name="stripe_price_<?= e($pk) ?>_annual" value="<?= e(setting('stripe_price_' . $pk . '_annual', '')) ?>" placeholder="price_… (optional)"></div>
        <?php endforeach; ?>
      </div>
      <h3 style="margin-top:14px"><?= icon('mail') ?> Wave (invoices)</h3>
      <p class="muted small">Needs a Wave plan with API access. Create an app in the Wave Developer Portal, then paste a full-access token for your business. Create one product per plan in Wave (Sales → Products) and paste its ID.</p>
      <div class="grid c2">
        <div class="field"><label for="w-tok">Access token</label><input type="password" id="w-tok" name="wave_token" autocomplete="off" placeholder="<?= secret('wave_token') ? 'Saved. Leave empty to keep it' : '' ?>"></div>
        <div class="field"><label for="w-biz">Business ID</label><input type="text" id="w-biz" name="wave_business_id" value="<?= e(setting('wave_business_id', '')) ?>"></div>
        <?php foreach (plans() as $pk => $pl): ?>
          <div class="field"><label for="wp-<?= e($pk) ?>">Product ID for <?= e($pl['name']) ?></label><input type="text" id="wp-<?= e($pk) ?>" name="wave_product_<?= e($pk) ?>" value="<?= e(setting('wave_product_' . $pk, '')) ?>"></div>
        <?php endforeach; ?>
      </div>
      <h3 style="margin-top:10px"><?= icon('shield') ?> Sign-in security</h3>
      <label class="check"><input type="checkbox" name="admin_otp" value="1" <?= setting('admin_otp', '1') === '1' ? 'checked' : '' ?>>
        <span><b>School admins need an emailed code</b> to sign in (recommended)<br><span class="muted small">Turn off only if email delivery is not working yet, otherwise admins cannot sign in.</span></span></label>
      <div class="flash info" style="margin-top:10px"><?= icon('shield', 18) ?> <span><b>Provider console:</b> every sign-in, with a password or with Google, is confirmed by a code emailed to <?= e(cfg('super_admin')['email'] ?? '') ?>.
        <?= google_ready() ? 'Sign in with Google is on.' : 'Sign in with Google is off: add your Google keys in /super → Sign-in.' ?></span></div>
      <div style="margin-top:16px"><button class="btn btn-primary">Save settings</button></div>
      <h3 style="margin-top:14px"><?= icon('search') ?> Google Search Console &amp; Analytics</h3>
      <p class="muted small">Only on the public website (never in customer portals or here). Search Console: Add property → URL prefix <code>https://<?= e(cfg('base_domain')) ?>/</code> → HTML tag → paste the tag. Then submit the sitemap <code>https://<?= e(cfg('base_domain')) ?>/sitemap.xml</code>.</p>
      <div class="grid c2">
        <div class="field"><label for="g-sv">Search Console HTML tag (or its content value)</label><input type="text" id="g-sv" name="google_site_verification" value="<?= e(setting('google_site_verification', '')) ?>" placeholder='<meta name="google-site-verification" content="…">'></div>
        <div class="field"><label for="g-ga">Google Analytics 4 measurement ID (optional)</label><input type="text" id="g-ga" name="ga4_id" value="<?= e(setting('ga4_id', '')) ?>" placeholder="G-XXXXXXXXXX"></div>
      </div>
      <?php if (setting('google_site_verification', '') !== ''): ?><p class="small" style="margin:0 0 8px"><span class="badge in">Search Console tag is on the website</span> Now press <b>Verify</b> in Search Console.</p><?php endif; ?>
      <h3 style="margin-top:14px"><?= icon('chart') ?> Google Ads conversion tracking</h3>
      <p class="muted small">Google Ads → Goals → Conversions → New conversion action → Website → set up manually with code. Create two: <b>Sign-up</b> (counts a new free trial) and <b>Lead</b> (demo request, call-back request or checklist download). From each "Install the tag yourself" page copy the ID <code>AW-…</code> (the same for both) and the label after the slash in <code>send_to</code>.</p>
      <div class="grid c3">
        <div class="field"><label for="g-aw">Conversion ID</label><input type="text" id="g-aw" name="gads_id" value="<?= e(setting('gads_id', '')) ?>" placeholder="AW-123456789"></div>
        <div class="field"><label for="g-ls">Sign-up label</label><input type="text" id="g-ls" name="gads_label_signup" value="<?= e(setting('gads_label_signup', '')) ?>" placeholder="AbCdEfGhIjK"></div>
        <div class="field"><label for="g-ll">Lead label</label><input type="text" id="g-ll" name="gads_label_lead" value="<?= e(setting('gads_label_lead', '')) ?>" placeholder="LmNoPqRsTuV"></div>
      </div>
      <h3 style="margin-top:14px"><?= icon('dollar') ?> Trial, Free plan and founding offer</h3>
      <div class="grid c3">
        <div class="field"><label for="g-td">Free trial length (days)</label><input type="number" min="1" max="365" id="g-td" name="trial_days" value="<?= trial_length() ?>"></div>
        <div class="field"><label for="g-fc">Founding promotion code (Stripe)</label><input type="text" id="g-fc" name="founding_code" value="<?= e(founding_code()) ?>"></div>
        <div class="field"><label>Founding places left</label><div style="padding:10px 0"><b><?= founding_left() ?></b> of <?= FOUNDING_SLOTS ?> <span class="muted small">(first <?= FOUNDING_SLOTS ?> sign-ups get <?= FOUNDING_TRIAL_DAYS ?> days free; on/off with the outreach offer switch)</span></div></div>
      </div>
      <label class="check"><input type="checkbox" name="free_plan_on" value="1" <?= free_plan_on() ? 'checked' : '' ?>>
        <span><b>Free plan</b>: up to <?= FREE_PLAN['max_people'] ?> people and <?= FREE_PLAN['max_logins'] ?> login, offered in Admin → Billing and on the pricing page</span></label>
      <label class="check"><input type="checkbox" name="popup_on" value="1" <?= setting('popup_on', '1') === '1' ? 'checked' : '' ?>>
        <span><b>Free checklist slide-in</b> on the home and landing pages (after 40 seconds or 60% scroll, at most once a month per visitor)</span></label>
      <button class="btn btn-primary">Save settings</button>
    </form>
  </div>
  <div class="card">
    <h3><?= icon('check') ?> Send a test email</h3>
    <form method="post" class="inline-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="test">
      <div class="field"><label for="s-test">Send to</label><input type="email" id="s-test" name="test_to" value="<?= e(cfg('super_admin')['email'] ?? '') ?>"></div>
      <div class="field"><label for="s-sender">Send from</label><select id="s-sender" name="sender">
        <option value="contact"><?= e(mail_config('contact')['from_email'] ?? '') ?> (sign-in codes, notices)</option>
        <option value="reports"><?= e(mail_config('reports')['from_email'] ?? '') ?> (reports)</option></select></div>
      <button class="btn btn-accent">Send test email</button>
    </form>
  </div>
</div>
