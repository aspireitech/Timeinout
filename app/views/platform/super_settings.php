<?php $mail = ($m['driver'] ?? 'mail') === 'mail'; ?>
<div style="max-width:900px;margin:0 auto 60px" class="stack">
  <div class="card">
    <h2><?= icon('mail') ?> Email delivery (SMTP)</h2>
    <p class="muted">Used for sign-in codes and report emails for every school. Get these details from your hosting (cPanel → Email Accounts → Connect Devices) or an email service such as Google Workspace, Microsoft 365, SendGrid or Amazon SES.</p>
    <form method="post">
      <?= csrf_field() ?>
      <div class="grid c2">
        <div class="field"><label for="s-driver">Send with</label>
          <select id="s-driver" name="driver"><option value="smtp" <?= $mail ? '' : 'selected' ?>>SMTP server (recommended)</option><option value="mail" <?= $mail ? 'selected' : '' ?>>PHP mail() (often lands in spam)</option></select></div>
        <div class="field"><label for="s-host">SMTP host</label><input type="text" id="s-host" name="host" value="<?= e($m['host'] ?? '') ?>" placeholder="mail.yourdomain.com"></div>
        <div class="field"><label for="s-port">Port</label><input type="number" id="s-port" name="port" value="<?= e($m['port'] ?? 587) ?>"><div class="hint">587 with TLS, or 465 with SSL.</div></div>
        <div class="field"><label for="s-secure">Encryption</label>
          <select id="s-secure" name="secure"><?php foreach (['tls' => 'TLS (STARTTLS, port 587)', 'ssl' => 'SSL (port 465)', '' => 'None (not recommended)'] as $k => $l): ?><option value="<?= $k ?>" <?= ($m['secure'] ?? 'tls') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="s-user">Username</label><input type="text" id="s-user" name="user" value="<?= e($m['user'] ?? '') ?>" autocomplete="off"></div>
        <div class="field"><label for="s-pass">Password</label><input type="password" id="s-pass" name="pass" autocomplete="new-password" placeholder="<?= $hasPass ? 'Saved. Leave empty to keep it' : '' ?>"><div class="hint">Stored encrypted.</div></div>
        <div class="field"><label for="s-from">From email</label><input type="email" id="s-from" name="from_email" value="<?= e($m['from_email'] ?? '') ?>"></div>
        <div class="field"><label for="s-fromname">From name</label><input type="text" id="s-fromname" name="from_name" value="<?= e($m['from_name'] ?? '') ?>"></div>
      </div>
      <h3 style="margin-top:10px"><?= icon('shield') ?> Sign-in security</h3>
      <label class="check"><input type="checkbox" name="admin_otp" value="1" <?= setting('admin_otp', '1') === '1' ? 'checked' : '' ?>>
        <span><b>School admins need an emailed code</b> to sign in (recommended)<br><span class="muted small">Turn off only if email delivery is not working yet, otherwise admins cannot sign in.</span></span></label>
      <label class="check" style="margin-top:10px"><input type="checkbox" name="super_otp" value="1" <?= setting('super_otp', '0') === '1' ? 'checked' : '' ?>>
        <span><b>Provider console needs an emailed code</b> too<br><span class="muted small">Send a test email first. The code goes to the super_admin email in config.php.</span></span></label>
      <div style="margin-top:16px"><button class="btn btn-primary">Save settings</button></div>
    </form>
  </div>
  <div class="card">
    <h3><?= icon('check') ?> Send a test email</h3>
    <form method="post" class="inline-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="test">
      <div class="field"><label for="s-test">Send to</label><input type="email" id="s-test" name="test_to" value="<?= e(cfg('super_admin')['email'] ?? '') ?>"></div>
      <button class="btn btn-accent">Send test email</button>
    </form>
  </div>
</div>
