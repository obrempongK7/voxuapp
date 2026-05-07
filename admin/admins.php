<?php
/**
 * Voxu Admin — Admins & Roles Management
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('super_admin');

try { $admin = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]); } catch (Throwable) { $admin = ['id'=>0,'name'=>'Admin','role'=>'admin','email'=>'']; }
if ($admin['role'] !== 'super_admin') {
    redirect('/admin/');
}
$activeMenu = 'admins';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $name  = sanitize($_POST['name']  ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $role  = sanitize($_POST['role']  ?? 'moderator');
            $pass  = sanitize($_POST['password'] ?? '');
            if (!$name || !$email || !$pass || strlen($pass) < 8) {
                $error = 'All fields required. Password min 8 characters.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (DB::count('admins', 'email=?', [$email])) {
                $error = 'That email is already registered as an admin.';
            } else {
                DB::insert('admins', [
                    'name'       => $name,
                    'email'      => $email,
                    'password'   => hashPassword($pass),
                    'role'       => in_array($role, ['super_admin','admin','moderator']) ? $role : 'moderator',
                    'api_token'  => generateToken(16),
                    'status'     => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                logAdminAction((int)$admin['id'], 'admin_created', "Created new admin: {$email}");
                $success = "Admin account created for {$email}.";
            }
            break;

        case 'update_role':
            $targetId = (int)($_POST['target_id'] ?? 0);
            $newRole  = sanitize($_POST['new_role'] ?? '');
            if ($targetId === (int)$admin['id']) {
                $error = 'You cannot change your own role.';
            } elseif (!in_array($newRole, ['super_admin','admin','moderator'])) {
                $error = 'Invalid role.';
            } else {
                DB::update('admins', ['role' => $newRole], ['id' => $targetId]);
                logAdminAction((int)$admin['id'], 'role_updated', "Changed role for admin #{$targetId} to {$newRole}");
                $success = 'Role updated.';
            }
            break;

        case 'toggle_status':
            $targetId = (int)($_POST['target_id'] ?? 0);
            if ($targetId === (int)$admin['id']) {
                $error = 'You cannot deactivate your own account.';
            } else {
                $target    = DB::first('SELECT * FROM admins WHERE id=?', [$targetId]);
                $newStatus = ($target['status'] === 'active') ? 'inactive' : 'active';
                DB::update('admins', ['status' => $newStatus], ['id' => $targetId]);
                logAdminAction((int)$admin['id'], 'admin_status_toggle', "Admin #{$targetId} set to {$newStatus}");
                $success = "Admin account set to {$newStatus}.";
            }
            break;

        case 'reset_password':
            $targetId = (int)($_POST['target_id'] ?? 0);
            $newPass  = sanitize($_POST['new_password'] ?? '');
            if (strlen($newPass) < 8) {
                $error = 'New password must be at least 8 characters.';
            } else {
                DB::update('admins', ['password' => hashPassword($newPass)], ['id' => $targetId]);
                logAdminAction((int)$admin['id'], 'admin_pw_reset', "Reset password for admin #{$targetId}");
                $success = 'Password reset successfully.';
            }
            break;

        case 'delete':
            $targetId = (int)($_POST['target_id'] ?? 0);
            if ($targetId === (int)$admin['id']) {
                $error = 'You cannot delete your own account.';
            } else {
                DB::exec('DELETE FROM admins WHERE id=?', [$targetId]);
                logAdminAction((int)$admin['id'], 'admin_deleted', "Deleted admin #{$targetId}");
                $success = 'Admin account deleted.';
            }
            break;
    }
}

$admins = DB::query('SELECT * FROM admins ORDER BY role ASC, name ASC');
$roleColors = ['super_admin'=>'badge-danger','admin'=>'badge-purple','moderator'=>'badge-blue'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Admins & Roles — Voxu Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="admin-page-title">Admins &amp; Roles</div>
    <div class="topbar-actions">
      <button class="btn btn-primary btn-sm" onclick="openModal('createModal')">+ New Admin</button>
    </div>
  </div>

  <div class="admin-content">
    <?php if ($success): ?><div class="alert alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">⚠ <?= clean($error)   ?></div><?php endif; ?>

    <!-- ROLE PERMISSIONS TABLE -->
    <div class="table-card" style="margin-bottom:24px">
      <div class="table-header">
        <span class="table-title">Role Permissions</span>
      </div>
      <table>
        <thead><tr><th>Permission</th><th>Super Admin</th><th>Admin</th><th>Moderator</th></tr></thead>
        <tbody>
          <?php
          $perms = [
            'Manage Users (view, suspend, ban)' => [1,1,1],
            'Delete Users'                      => [1,1,0],
            'Manage Admins'                     => [1,0,0],
            'Approve/Reject Withdrawals'        => [1,1,0],
            'Adjust User Wallets'               => [1,1,0],
            'Manage Content (posts, statuses)'  => [1,1,1],
            'Manage Campaigns'                  => [1,1,0],
            'View Transactions'                 => [1,1,0],
            'Send Announcements'                => [1,1,0],
            'Manage Platform Settings'          => [1,1,0],
            'Manage Payment Gateways'           => [1,0,0],
            'View Audit Logs'                   => [1,1,1],
          ];
          foreach ($perms as $perm => $access):
          ?>
          <tr>
            <td style="font-size:13px"><?= $perm ?></td>
            <?php foreach ($access as $a): ?>
            <td style="text-align:center"><?= $a ? '<span style="color:var(--green);font-size:16px">✓</span>' : '<span style="color:var(--text3);font-size:16px">—</span>' ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ADMINS TABLE -->
    <div class="table-card">
      <div class="table-header">
        <span class="table-title">Admin Accounts (<?= count($admins) ?>)</span>
      </div>
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($admins as $a):
            $isMe = $a['id'] == $admin['id'];
          ?>
          <tr>
            <td>
              <div class="flex items-center gap-2">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--purple-l);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--purple)">
                  <?= avatarInitials($a['name']) ?>
                </div>
                <span style="font-weight:500;color:var(--text)"><?= clean($a['name']) ?> <?= $isMe ? '<span style="font-size:10px;color:var(--text3)">(you)</span>' : '' ?></span>
              </div>
            </td>
            <td style="font-size:13px"><?= clean($a['email']) ?></td>
            <td><span class="badge <?= $roleColors[$a['role']] ?? 'badge-muted' ?>"><?= ucfirst(str_replace('_',' ',$a['role'])) ?></span></td>
            <td><span class="badge <?= $a['status']==='active'?'badge-green':'badge-muted' ?>"><?= ucfirst($a['status']) ?></span></td>
            <td style="font-size:12px"><?= $a['last_login'] ? timeAgo($a['last_login']) : 'Never' ?></td>
            <td>
              <?php if (!$isMe): ?>
              <div class="flex gap-2">
                <button class="btn btn-secondary btn-sm" onclick="openRoleModal(<?= $a['id'] ?>,'<?= clean($a['role']) ?>','<?= clean($a['name']) ?>')">Role</button>
                <button class="btn btn-secondary btn-sm" onclick="openResetPwModal(<?= $a['id'] ?>,'<?= clean($a['name']) ?>')">Reset PW</button>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
                  <input type="hidden" name="action" value="toggle_status"/>
                  <input type="hidden" name="target_id" value="<?= $a['id'] ?>"/>
                  <button type="submit" class="btn btn-sm <?= $a['status']==='active'?'btn-secondary':'btn-success' ?>">
                    <?= $a['status']==='active' ? 'Deactivate' : 'Activate' ?>
                  </button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this admin permanently?')">
                  <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
                  <input type="hidden" name="action" value="delete"/>
                  <input type="hidden" name="target_id" value="<?= $a['id'] ?>"/>
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </div>
              <?php else: ?>
              <span style="font-size:12px;color:var(--text3)">Current session</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- CREATE ADMIN MODAL -->
<div class="modal-backdrop" id="createModal">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <div class="admin-modal-title">Create Admin Account</div>
      <button onclick="closeModal('createModal')" style="color:var(--text2)">✕</button>
    </div>
    <form method="POST">
      <div class="admin-modal-body">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action" value="create"/>
        <div class="form-group"><label class="form-label">Full Name</label><input class="form-input" type="text" name="name" required/></div>
        <div class="form-group"><label class="form-label">Email Address</label><input class="form-input" type="email" name="email" required/></div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select class="form-input" name="role">
            <option value="moderator">Moderator</option>
            <option value="admin">Admin</option>
            <option value="super_admin">Super Admin</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Password (min 8 chars)</label><input class="form-input" type="password" name="password" minlength="8" required/></div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Admin</button>
      </div>
    </form>
  </div>
</div>

<!-- CHANGE ROLE MODAL -->
<div class="modal-backdrop" id="roleModal">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <div class="admin-modal-title" id="roleModalTitle">Change Role</div>
      <button onclick="closeModal('roleModal')" style="color:var(--text2)">✕</button>
    </div>
    <form method="POST">
      <div class="admin-modal-body">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action" value="update_role"/>
        <input type="hidden" name="target_id" id="roleTargetId"/>
        <div class="form-group">
          <label class="form-label">New Role</label>
          <select class="form-input" name="new_role" id="roleSelect">
            <option value="moderator">Moderator</option>
            <option value="admin">Admin</option>
            <option value="super_admin">Super Admin</option>
          </select>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('roleModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Role</button>
      </div>
    </form>
  </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-backdrop" id="resetPwModal">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <div class="admin-modal-title" id="resetPwTitle">Reset Password</div>
      <button onclick="closeModal('resetPwModal')" style="color:var(--text2)">✕</button>
    </div>
    <form method="POST">
      <div class="admin-modal-body">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action" value="reset_password"/>
        <input type="hidden" name="target_id" id="resetPwTargetId"/>
        <div class="form-group"><label class="form-label">New Password</label><input class="form-input" type="password" name="new_password" minlength="8" required placeholder="Min 8 characters"/></div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('resetPwModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openRoleModal(id, role, name) {
  document.getElementById('roleTargetId').value  = id;
  document.getElementById('roleModalTitle').textContent = 'Change Role: ' + name;
  document.getElementById('roleSelect').value    = role;
  openModal('roleModal');
}
function openResetPwModal(id, name) {
  document.getElementById('resetPwTargetId').value = id;
  document.getElementById('resetPwTitle').textContent = 'Reset Password: ' + name;
  openModal('resetPwModal');
}
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-backdrop')) closeModal(e.target.id);
});
</script>
</body>
</html>
