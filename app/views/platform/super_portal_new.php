<div style="max-width:780px;margin:10px auto 60px">
  <div class="card">
    <h1><?= icon('plus') ?> New customer portal</h1>
    <p class="muted">Creates the portal, its owner login and its own database. The owner's first password is shown once on the next screen.</p>
    <?php if (isset($errors['db'])): ?><div class="flash error"><?= e($errors['db']) ?></div><?php endif; ?>
    <form method="post" action="<?= e(base_path() . '/super/portals/new') ?>" novalidate>
      <?= csrf_field() ?>
      <h3>Customer</h3>
      <div class="grid c2">
        <div class="field"><label for="p-org">Business name</label>
          <input type="text" id="p-org" name="org_name" value="<?= e($old['org_name']) ?>" placeholder="South Jordan Kumon" required autofocus>
          <?php if (isset($errors['org_name'])): ?><div class="err"><?= e($errors['org_name']) ?></div><?php endif; ?></div>
        <div class="field"><label for="p-slug">Web address</label>
          <div class="slug-input"><input type="text" id="p-slug" name="slug" value="<?= e($old['slug']) ?>" placeholder="utsjkumon" pattern="[a-z0-9-]{3,30}" required><span>.<?= e(cfg('base_domain')) ?></span></div>
          <?php if (isset($errors['slug'])): ?><div class="err"><?= e($errors['slug']) ?></div><?php else: ?><div class="hint">3–30 lowercase letters, numbers or dashes.</div><?php endif; ?></div>
      </div>
      <div class="grid c2">
        <div class="field"><label for="p-ind">Industry</label>
          <select id="p-ind" name="industry"><?php foreach (industries() as $k => $i): ?><option value="<?= e($k) ?>" <?= $old['industry'] === $k ? 'selected' : '' ?>><?= e($i['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="p-tz">Time zone</label>
          <select id="p-tz" name="timezone"><option value="">Automatic (each device's time)</option>
            <?php foreach (['America/New_York', 'America/Chicago', 'America/Denver', 'America/Phoenix', 'America/Los_Angeles', 'America/Anchorage', 'Pacific/Honolulu'] as $tz): ?><option <?= $old['timezone'] === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="grid c2">
        <div class="field"><label for="p-state">State</label>
          <select id="p-state" name="state"><option value="">—</option><?php foreach (US_STATES as $code => $name): ?><option value="<?= $code ?>" <?= $old['state'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="p-city">City</label><input type="text" id="p-city" name="city" value="<?= e($old['city']) ?>" maxlength="40"></div>
      </div>
      <h3>Owner login</h3>
      <div class="grid c2">
        <div class="field"><label for="p-an">Owner name</label>
          <input type="text" id="p-an" name="admin_name" value="<?= e($old['admin_name']) ?>" required>
          <?php if (isset($errors['admin_name'])): ?><div class="err"><?= e($errors['admin_name']) ?></div><?php endif; ?></div>
        <div class="field"><label for="p-em">Owner email (their login)</label>
          <input type="email" id="p-em" name="email" value="<?= e($old['email']) ?>" required>
          <?php if (isset($errors['email'])): ?><div class="err"><?= e($errors['email']) ?></div><?php endif; ?></div>
      </div>
      <h3>Plan</h3>
      <div class="grid c2">
        <div class="field"><label for="p-plan">Plan</label>
          <select id="p-plan" name="plan"><?php foreach ($plans as $k => $p): ?><option value="<?= e($k) ?>" <?= $old['plan'] === $k ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e($p['price'] ?? '') ?>)</option><?php endforeach; ?></select></div>
        <div class="field"><label for="p-status">Start as</label>
          <select id="p-status" name="status"><option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>Active (billed by you, no trial)</option>
            <option value="trial" <?= $old['status'] === 'trial' ? 'selected' : '' ?>>Free trial</option></select>
          <div class="hint">Trial length: <input type="number" name="trial_days" value="<?= e($old['trial_days']) ?>" min="1" max="365" style="width:80px;display:inline-block"> days</div></div>
      </div>
      <label class="check"><input type="checkbox" name="demo" value="1" <?= $old['demo'] ? 'checked' : '' ?>><span>Add sample people <span class="muted small">(leave off for a real customer)</span></span></label>

      <h3 style="margin-top:18px">Database</h3>
      <label class="check"><input type="radio" name="db_mode" value="dedicated" <?= $old['db_mode'] === 'dedicated' ? 'checked' : '' ?>>
        <span><b>Own database</b> (recommended): this customer's data is stored only in its own database.<br>
        <span class="muted small">First create a new, empty MySQL database and user in your hosting panel (Hostinger: Websites → Databases → MySQL Databases), then enter them here.</span></span></label>
      <div class="grid c2" style="margin-top:8px">
        <div class="field"><label for="p-dbn">Database name</label><input type="text" id="p-dbn" name="db_name" value="<?= e($old['db_name']) ?>" placeholder="<?= e($dbPrefix) ?>utsjkumon" data-db-prefix="<?= e($dbPrefix) ?>" maxlength="64">
          <div class="hint">Filled in from the address. In hPanel type the part after your account prefix (e.g. <code>cio_utsjkumon</code>).</div></div>
        <div class="field"><label for="p-dbu">Database user</label><input type="text" id="p-dbu" name="db_user" value="<?= e($old['db_user']) ?>" placeholder="<?= e($dbPrefix) ?>utsjkumon" maxlength="64">
          <div class="hint" id="p-dbu-hint">Usually the same as the database name. At most 32 characters.</div></div>
      </div>
      <div class="grid c2">
        <div class="field"><label for="p-dbp">Database password</label><input type="password" id="p-dbp" name="db_pass" autocomplete="new-password"><div class="hint">Stored encrypted.</div></div>
        <div class="field"><label for="p-dbh">Host and port</label>
          <div class="row"><input type="text" id="p-dbh" name="db_host" value="<?= e($old['db_host']) ?>" style="flex:3"><input type="number" name="db_port" value="<?= e($old['db_port']) ?>" style="flex:1"></div></div>
      </div>
      <label class="check" style="margin-top:6px"><input type="radio" name="db_mode" value="shared" <?= $old['db_mode'] === 'shared' ? 'checked' : '' ?>>
        <span><b>Shared database</b>: stored in the main database, kept apart by portal ID.</span></label>
      <div style="margin-top:18px" class="row"><button class="btn btn-primary btn-lg">Create portal</button><a class="btn btn-ghost" href="<?= e(base_path() . '/super') ?>">Cancel</a></div>
    </form>
  </div>
</div>
