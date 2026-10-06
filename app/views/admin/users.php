<div class="topbar"><div><h1><?= is_admin() ? 'Users' : 'My password' ?></h1><div class="muted"><?= is_admin() ? 'People who can log in to the kiosk and admin portal.' : '' ?></div></div></div>
<div class="grid c2">
  <?php if (is_admin()): ?>
  <div class="card">
    <table class="table">
      <tr><th>Name</th><th>Role</th><th>Last login</th><th></th></tr>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><b><?= e($u['name']) ?></b><br><span class="muted small"><?= e($u['email']) ?></span></td>
          <td><span class="badge <?= $u['role'] === 'staff' ? 'gray' : 'student' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
          <td class="small muted"><?= $u['last_login_at'] ? e(date('M j, g:i A', strtotime($u['last_login_at']))) : 'Never' ?></td>
          <td class="right"><?php if ($u['role'] !== 'owner' && $u['id'] != current_user()['id']): ?>
            <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/delete')) ?>" data-confirm="Remove <?= e($u['name']) ?>?"><?= csrf_field() ?><button class="link-btn"><?= icon('trash', 16) ?></button></form>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <h3 style="margin-top:20px">Add a user</h3>
    <form method="post" action="<?= e(url('/admin/users')) ?>">
      <?= csrf_field() ?>
      <div class="grid c2">
        <div class="field"><label>Name</label><input type="text" name="name" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" required></div>
        <div class="field"><label>Role</label><select name="role"><option value="staff">Staff — kiosk, dashboard, log</option><option value="admin">Admin — everything</option></select></div>
        <div class="field"><label>Password</label><input type="text" name="password" minlength="8" required></div>
      </div>
      <button class="btn btn-primary"><?= icon('plus', 16) ?> Add user</button>
    </form>
  </div>
  <?php endif; ?>
  <div class="card" style="align-self:start">
    <h3><?= icon('shield') ?> Change my password</h3>
    <form method="post" action="<?= e(url('/admin/password')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label>Current password</label><input type="password" name="current_password" required></div>
      <div class="field"><label>New password</label><input type="password" name="new_password" minlength="8" required></div>
      <button class="btn btn-primary">Change password</button>
    </form>
  </div>
</div>
