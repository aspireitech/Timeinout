<?php $me = current_user(); ?>
<div class="topbar"><div><h1>Users</h1><div class="muted">Admins manage the school (with an emailed sign-in code). Staff can only open the kiosk.</div></div></div>
<div class="grid c2">
  <div class="card">
    <table class="table">
      <tr><th>Name</th><th>Role</th><th>Last sign-in</th><th></th></tr>
      <?php foreach ($users as $u): $canReset = $u['role'] !== 'owner' || $u['id'] == $me['id']; ?>
        <tr>
          <td><b><?= e($u['name']) ?></b><br><span class="muted small"><?= e($u['email']) ?></span></td>
          <td><span class="badge <?= $u['role'] === 'staff' ? 'gray' : 'student' ?>"><?= $u['role'] === 'staff' ? 'Staff (kiosk only)' : e(ucfirst($u['role'])) ?></span></td>
          <td class="small muted"><?= $u['last_login_at'] ? e(audit_time($u['last_login_at'])) : 'Never' ?></td>
          <td class="right nowrap">
            <?php if ($canReset && $u['id'] != $me['id']): ?>
              <details class="reset"><summary class="btn btn-sm btn-ghost">New password</summary>
                <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/password')) ?>" class="inline-form" style="margin-top:6px">
                  <?= csrf_field() ?><div class="field"><input type="text" name="password" placeholder="New password" required aria-label="New password for <?= e($u['name']) ?>"></div><button class="btn btn-sm btn-primary">Set</button>
                </form></details>
            <?php endif; ?>
            <?php if ($u['role'] !== 'owner' && $u['id'] != $me['id']): ?>
              <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/delete')) ?>" data-confirm="Remove <?= e($u['name']) ?>?" style="display:inline"><?= csrf_field() ?><button class="link-btn" title="Remove" aria-label="Remove <?= e($u['name']) ?>"><?= icon('trash', 16) ?></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <h3 style="margin-top:20px">Add a user</h3>
    <form method="post" action="<?= e(url('/admin/users')) ?>">
      <?= csrf_field() ?>
      <div class="grid c2">
        <div class="field"><label for="u-name">Name</label><input type="text" id="u-name" name="name" required></div>
        <div class="field"><label for="u-email">Email</label><input type="email" id="u-email" name="email" required></div>
        <div class="field"><label for="u-role">Role</label><select id="u-role" name="role"><option value="staff">Staff: kiosk only, no admin access</option><option value="admin">Admin: everything, with email sign-in code</option></select></div>
        <div class="field"><label for="u-pass">Password</label><input type="text" id="u-pass" name="password" minlength="8" required>
          <div class="hint">Staff: 8+ characters. Admins: 10+. Letters and a number.</div></div>
      </div>
      <button class="btn btn-primary"><?= icon('plus', 16) ?> Add user</button>
    </form>
  </div>
  <div class="card" style="align-self:start">
    <h3><?= icon('shield') ?> Change my password</h3>
    <form method="post" action="<?= e(url('/admin/password')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label for="p-cur">Current password</label><input type="password" id="p-cur" name="current_password" required></div>
      <div class="field"><label for="p-new">New password</label><input type="password" id="p-new" name="new_password" minlength="10" required><div class="hint">At least 10 characters, with letters and a number.</div></div>
      <button class="btn btn-primary">Change password</button>
    </form>
  </div>
</div>
