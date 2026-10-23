<?php $me = current_user(); ?>
<div class="topbar"><div><h1>Users</h1><div class="muted">Admins manage the school (with an emailed sign-in code). Staff can only open the kiosk and can sign in with a user ID.</div></div></div>
<div class="grid c2">
  <div class="card">
    <table class="table">
      <tr><th>Name</th><th>Role</th><th>Last sign-in</th><th></th></tr>
      <?php foreach ($users as $u): $canReset = $u['role'] !== 'owner' || $u['id'] == $me['id']; ?>
        <tr>
          <td><b><?= e($u['name']) ?></b>
            <?php if ($u['username']): ?><br><span class="small">User ID: <code><?= e($u['username']) ?></code></span><?php endif; ?>
            <?php if ($u['email']): ?><br><span class="muted small"><?= e($u['email']) ?></span><?php endif; ?>
            <?php if ($u['must_change_password']): ?><br><span class="badge warn">must change password</span><?php endif; ?></td>
          <td><span class="badge <?= $u['role'] === 'staff' ? 'gray' : 'student' ?>"><?= $u['role'] === 'staff' ? 'Staff (kiosk only)' : e(ucfirst($u['role'])) ?></span></td>
          <td class="small muted"><?= $u['last_login_at'] ? e(audit_time($u['last_login_at'])) : 'Never' ?></td>
          <td class="right nowrap">
            <?php if ($canReset && $u['id'] != $me['id']): ?>
              <details class="reset"><summary class="btn btn-sm btn-ghost">New password</summary>
                <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/password')) ?>" class="stack" style="margin-top:6px;text-align:left;min-width:240px">
                  <?= csrf_field() ?>
                  <div class="field"><input type="text" name="password" placeholder="Leave empty to generate one" autocomplete="off" aria-label="New password for <?= e($u['name']) ?>"></div>
                  <label class="check small"><input type="checkbox" name="must_change" value="1" checked><span>Must change it at next sign-in</span></label>
                  <label class="check small"><input type="checkbox" name="send_email" value="1" <?= $u['email'] ? 'checked' : 'disabled' ?>><span>Email it to <?= $u['email'] ? e($u['email']) : '(no email on file)' ?></span></label>
                  <button class="btn btn-sm btn-primary">Set password</button>
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
        <div class="field"><label for="u-role">Role</label><select id="u-role" name="role"><option value="staff">Staff: kiosk only, no admin access</option><option value="admin">Admin: everything, with email sign-in code</option></select></div>
        <div class="field"><label for="u-user">User ID <span class="muted">(for signing in)</span></label><input type="text" id="u-user" name="username" placeholder="frontdesk" autocomplete="off" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,39}">
          <div class="hint">3–40 letters, numbers, dots, dashes. Optional if you enter an email.</div></div>
        <div class="field"><label for="u-email">Email <span class="muted">(optional for staff)</span></label><input type="email" id="u-email" name="email" placeholder="name@example.com">
          <div class="hint">Required for admins. Used for notices and to send the sign-in details.</div></div>
        <div class="field"><label for="u-pass">Password</label><input type="text" id="u-pass" name="password" autocomplete="off" placeholder="Leave empty to generate one">
          <div class="hint">Staff: 8+ characters. Admins: 10+. Letters and a number.</div></div>
        <div class="field"><label>&nbsp;</label>
          <label class="check"><input type="checkbox" name="must_change" value="1" checked><span>Must change password at first sign-in</span></label>
          <label class="check"><input type="checkbox" name="send_email" value="1" checked><span>Email the sign-in details (when an email is entered)</span></label></div>
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
