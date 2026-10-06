<div class="topbar"><div><h1>Branding &amp; settings</h1><div class="muted">Make the portal your own.</div></div>
  <a class="btn btn-ghost" href="<?= e(url('/')) ?>" target="_blank"><?= icon('kiosk') ?> Preview kiosk</a></div>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="grid c2">
    <div class="card">
      <h3><?= icon('settings') ?> Organization &amp; home page</h3>
      <div class="field"><label>Organization name</label><input type="text" name="name" value="<?= e($t['name']) ?>" required></div>
      <div class="field"><label>Kiosk welcome title</label><input type="text" name="welcome_title" value="<?= e($t['welcome_title']) ?>" placeholder="Welcome!"></div>
      <div class="field"><label>Kiosk welcome message</label><textarea name="welcome_text" rows="2"><?= e($t['welcome_text']) ?></textarea></div>
      <div class="field"><label>Logo</label>
        <?php if ($t['logo_path']): ?>
          <div class="row" style="margin-bottom:8px"><img src="<?= e(upload_url($t['logo_path'])) ?>" alt="" style="max-height:56px;max-width:180px;border-radius:8px;background:var(--soft);padding:4px">
          <label class="check"><input type="checkbox" name="remove_logo" value="1"> Remove</label></div>
        <?php endif; ?>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif">
        <div class="hint">PNG, JPG, WEBP or GIF, up to 2 MB.</div>
      </div>
      <div class="row">
        <div class="field"><label>Main color</label><input type="color" name="primary_color" value="<?= e($t['primary_color']) ?>"></div>
        <div class="field"><label>Accent color</label><input type="color" name="accent_color" value="<?= e($t['accent_color']) ?>"></div>
      </div>
    </div>
    <div class="stack">
      <div class="card">
        <h3><?= icon('kiosk') ?> Kiosk</h3>
        <div class="field"><label>Time zone</label><select name="timezone"><?php foreach (DateTimeZone::listIdentifiers() as $tz): ?><option <?= $t['timezone'] === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Allow entries for past days</label>
          <select name="allow_past_days"><?php foreach ([0 => 'Today only', 1 => 'Up to 1 day back', 3 => 'Up to 3 days back', 7 => 'Up to 7 days back', 14 => 'Up to 14 days back', 30 => 'Up to 30 days back'] as $n => $l): ?><option value="<?= $n ?>" <?= (int) $t['allow_past_days'] === $n ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
          <div class="hint">Controls the "Yesterday / Other day" buttons on the kiosk.</div></div>
        <label class="check"><input type="checkbox" name="kiosk_public" value="1" <?= $t['kiosk_public'] ? 'checked' : '' ?>>
          <span>Open kiosk without login<br><span class="muted small">Anyone with the link can see student names. Leave off unless the kiosk link is only on your own device.</span></span></label>
      </div>
      <div class="card">
        <h3><?= icon('globe') ?> Web address</h3>
        <p class="small">Your portal: <a href="<?= e(tenant_url(array_merge($t, ['custom_domain' => null]), '/')) ?>"><?= e(tenant_url(array_merge($t, ['custom_domain' => null]), '/')) ?></a></p>
        <div class="field"><label>Custom domain (optional)</label><input type="text" name="custom_domain" value="<?= e($t['custom_domain']) ?>" placeholder="checkin.yourschool.org">
          <div class="hint">Point a CNAME record for this name to <b><?= e(cfg('base_domain')) ?></b>, and ask us to add it to the server. Then it opens your portal.</div></div>
      </div>
    </div>
  </div>
  <div style="margin-top:18px"><button class="btn btn-primary btn-lg">Save settings</button></div>
</form>
