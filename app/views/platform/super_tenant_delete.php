<div style="max-width:560px;margin:20px auto 60px" class="card">
  <h1 style="color:var(--danger)"><?= icon('shield') ?> <?= $permanent ? 'Remove permanently' : 'Delete portal' ?></h1>
  <p><b><?= e($t['name']) ?></b> · <?= e(preg_replace('#^https?://#', '', tenant_url($t, ''))) ?>
    <br><span class="muted small"><?= tenant_db_config($t) ? 'Own database ' . e($t['db_name']) : 'Shared database' ?> ·
    <?= e((string) $stats['people']) ?> people · <?= e((string) $stats['events']) ?> check-ins/outs · <?= e((string) $stats['users']) ?> logins</span></p>
  <?php if ($permanent): ?>
    <div class="flash error"><?= icon('shield', 18) ?> <span>This cannot be undone. All people, check-ins and check-outs, logins and logs of this portal are erased now.
      <?= tenant_db_config($t) ? 'A last copy of its database is saved in backups/deleted/ on the server first.' : '' ?> Payments stay in your records.</span></div>
  <?php else: ?>
    <div class="flash info"><?= icon('shield', 18) ?> <span>The portal closes immediately: nobody can sign in or use the kiosk. Its data is kept for <b><?= DELETE_GRACE_DAYS ?> days</b>
      and you can restore it until then. After that it is removed for good.</span></div>
  <?php endif; ?>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(base_path() . '/super/tenants/' . $t['id'] . '/delete') ?>"
        data-confirm="Last check: <?= $permanent ? 'permanently remove' : 'delete' ?> <?= e($t['name']) ?>? <?= $permanent ? 'This cannot be undone.' : '' ?>">
    <?= csrf_field() ?>
    <div class="field"><label for="d-slug">Type the portal address <code><?= e($t['slug']) ?></code> to confirm</label>
      <input type="text" id="d-slug" name="confirm_slug" autocomplete="off" required autofocus></div>
    <div class="field"><label for="d-pass">Your provider (super admin) password</label>
      <input type="password" id="d-pass" name="password" autocomplete="current-password" required></div>
    <label class="check"><input type="checkbox" name="understand" value="1" required>
      <span>I understand that <?= $permanent ? 'this portal and all its data will be erased permanently' : 'this portal closes now and is erased after ' . DELETE_GRACE_DAYS . ' days unless I restore it' ?>.</span></label>
    <div class="row" style="margin-top:16px"><button class="btn btn-danger btn-lg"><?= $permanent ? 'Remove permanently' : 'Delete portal' ?></button>
      <a class="btn btn-ghost" href="<?= e(base_path() . '/super') ?>">Cancel</a></div>
  </form>
</div>
