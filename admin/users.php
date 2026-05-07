<?php
// @author  Jcode | ObrempongK
// admin/users.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('moderator');

$admin  = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$page   = max(1, (int)($_GET['page']  ?? 1));
$search = sanitize($_GET['q']    ?? '');
$filter = sanitize($_GET['status'] ?? '');
$perPage = 25;

// Build where clause
$where  = '1';
$params = [];
if ($search) {
    $where  .= ' AND (u.username LIKE ? OR u.email LIKE ?)';
    $params  = array_merge($params, ["%{$search}%", "%{$search}%"]);
}
if ($filter) {
    $where  .= ' AND u.status=?';
    $params[] = $filter;
}
$total   = DB::first("SELECT COUNT(*) AS n FROM users u WHERE {$where}", $params)['n'] ?? 0;
$offset  = ($page - 1) * $perPage;
$users   = DB::query(
    "SELECT u.*, up.avatar,
            w.balance, w.points_balance,
            (SELECT COUNT(*) FROM posts WHERE user_id=u.id) AS post_count
     FROM users u
     LEFT JOIN user_profiles up ON up.user_id=u.id
     LEFT JOIN wallets w ON w.user_id=u.id
     WHERE {$where}
     ORDER BY u.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    switch ($action) {
        case 'suspend':
            if (in_array($admin['role'], ['super_admin','admin'])) {
                DB::update('users', ['status'=>'suspended'], ['id'=>$userId]);
                logAdminAction((int)$admin['id'], 'user_suspend', "Suspended user #{$userId}");
            }
            break;
        case 'activate':
            if (in_array($admin['role'], ['super_admin','admin'])) {
                DB::update('users', ['status'=>'active'], ['id'=>$userId]);
                logAdminAction((int)$admin['id'], 'user_activate', "Activated user #{$userId}");
            }
            break;
        case 'ban':
            if ($admin['role'] === 'super_admin') {
                DB::update('users', ['status'=>'banned'], ['id'=>$userId]);
                logAdminAction((int)$admin['id'], 'user_ban', "Banned user #{$userId}");
            }
            break;
        case 'delete':
            if ($admin['role'] === 'super_admin') {
                DB::exec('DELETE FROM users WHERE id=?', [$userId]);
                logAdminAction((int)$admin['id'], 'user_delete', "Deleted user #{$userId}");
            }
            break;
        case 'adjust_wallet':
            if (in_array($admin['role'], ['super_admin','admin'])) {
                $type   = $_POST['adjust_type']   ?? 'credit';
                $amount = (float)($_POST['adjust_amount'] ?? 0);
                $reason = sanitize($_POST['adjust_reason'] ?? 'Admin adjustment');
                if ($amount > 0) {
                    if ($type === 'credit') {
                        addBalance($userId, $amount, 'admin_credit', generateToken(8), $reason);
                    } else {
                        deductBalance($userId, $amount, 'admin_debit', generateToken(8), $reason);
                    }
                    DB::insert('admin_wallet_adjustments', [
                        'admin_id'=>(int)$admin['id'],'user_id'=>$userId,'type'=>$type,
                        'amount'=>$amount,'reason'=>$reason,'created_at'=>date('Y-m-d H:i:s')
                    ]);
                    logAdminAction((int)$admin['id'], 'wallet_adjust', "Adjusted wallet for user #{$userId}: {$type} {$amount}");
                }
            }
            break;
        case 'freeze_wallet':
            if (in_array($admin['role'], ['super_admin','admin'])) {
                DB::update('wallets', ['is_frozen'=>1], ['user_id'=>$userId]);
                logAdminAction((int)$admin['id'], 'wallet_freeze', "Froze wallet for user #{$userId}");
            }
            break;
        case 'unfreeze_wallet':
            if (in_array($admin['role'], ['super_admin','admin'])) {
                DB::update('wallets', ['is_frozen'=>0], ['user_id'=>$userId]);
                logAdminAction((int)$admin['id'], 'wallet_unfreeze', "Unfroze wallet for user #{$userId}");
            }
            break;
    }
    header('Location: users.php' . ($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : ''));
    exit;
}

$totalPages = ceil($total / $perPage);
$settings   = getPlatformSettings();
$symbol     = $settings['currency_symbol'] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Users — Voxu Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
</head>
<body>
<?php $activeMenu = 'users'; require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <div class="admin-page-title">User Management</div>
    <div class="topbar-actions">
      <form method="GET" style="display:flex;gap:8px">
        <input type="text" class="search-input" name="q" value="<?= clean($search) ?>" placeholder="Search username or email…"/>
        <select class="search-input" name="status" style="width:auto">
          <option value="">All Status</option>
          <option value="active" <?= $filter==='active'?'selected':'' ?>>Active</option>
          <option value="suspended" <?= $filter==='suspended'?'selected':'' ?>>Suspended</option>
          <option value="banned" <?= $filter==='banned'?'selected':'' ?>>Banned</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($search || $filter): ?><a href="/admin/users.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="admin-content">
    <div class="table-card">
      <div class="table-header">
        <span class="table-title">Users (<?= number_format($total) ?> total)</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Status</th>
            <th>Balance</th>
            <th>Points</th>
            <th>Posts</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:34px;height:34px;border-radius:50%;background:var(--purple-l);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--purple);flex-shrink:0">
                  <?= avatarInitials($u['username']) ?>
                </div>
                <div>
                  <div style="font-weight:600;color:var(--text)"><?= clean($u['username']) ?></div>
                  <div style="font-size:11px;color:var(--text3)"><?= clean($u['email']) ?></div>
                  <?php if ($u['is_verified']): ?><span class="badge badge-blue" style="margin-top:3px">✓ Verified</span><?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <span class="badge <?= $u['status']==='active'?'badge-green':($u['status']==='banned'?'badge-danger':'badge-warning') ?>">
                <?= ucfirst($u['status']) ?>
              </span>
            </td>
            <td style="font-weight:600"><?= $symbol ?><?= number_format((float)($u['balance']??0),2) ?></td>
            <td><?= number_format((int)($u['points_balance']??0)) ?></td>
            <td><?= number_format((int)($u['post_count']??0)) ?></td>
            <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="dropdown" id="udrop-<?= $u['id'] ?>">
                <button class="btn btn-secondary btn-sm" onclick="document.getElementById('udrop-<?=$u['id']?>').classList.toggle('open')">Actions ▾</button>
                <div class="dropdown-menu">
                  <div class="dropdown-item" onclick="viewUser(<?= $u['id'] ?>)">👤 View Profile</div>
                  <?php if ($u['status'] !== 'active'): ?>
                    <div class="dropdown-item" onclick="userAction('activate',<?=$u['id']?>)">✅ Activate</div>
                  <?php endif; ?>
                  <?php if ($u['status'] === 'active'): ?>
                    <div class="dropdown-item" onclick="userAction('suspend',<?=$u['id']?>)">⏸ Suspend</div>
                  <?php endif; ?>
                  <div class="dropdown-item danger" onclick="userAction('ban',<?=$u['id']?>)">🚫 Ban</div>
                  <div class="dropdown-divider"></div>
                  <div class="dropdown-item" onclick="adjustWallet(<?=$u['id']?>, '<?= clean($u['username']) ?>')">💰 Adjust Wallet</div>
                  <div class="dropdown-item" onclick="userAction('freeze_wallet',<?=$u['id']?>)">❄ Freeze Wallet</div>
                  <div class="dropdown-item" onclick="userAction('unfreeze_wallet',<?=$u['id']?>)">🔥 Unfreeze Wallet</div>
                  <?php if ($admin['role'] === 'super_admin'): ?>
                  <div class="dropdown-divider"></div>
                  <div class="dropdown-item danger" onclick="if(confirm('Permanently delete user?')) userAction('delete',<?=$u['id']?>)">🗑 Delete User</div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
            <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text3)">No users found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <!-- PAGINATION -->
      <div class="table-footer">
        <span>Showing <?= min($offset+1,$total) ?>–<?= min($offset+$perPage,$total) ?> of <?= number_format($total) ?></span>
        <div class="pagination">
          <?php if ($page > 1): ?><a href="?page=<?=$page-1?><?= $search?"&q={$search}":'' ?><?= $filter?"&status={$filter}":'' ?>" class="page-btn">←</a><?php endif; ?>
          <?php
          $start = max(1, $page-2); $end = min($totalPages, $page+2);
          for ($p = $start; $p <= $end; $p++):
          ?>
            <a href="?page=<?=$p?><?= $search?"&q={$search}":'' ?><?= $filter?"&status={$filter}":'' ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?><a href="?page=<?=$page+1?><?= $search?"&q={$search}":'' ?><?= $filter?"&status={$filter}":'' ?>" class="page-btn">→</a><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- WALLET ADJUSTMENT MODAL -->
<div class="modal-backdrop" id="walletModal">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <div class="admin-modal-title" id="walletModalTitle">Adjust Wallet</div>
      <button onclick="document.getElementById('walletModal').classList.remove('open')" style="color:var(--text2)">✕</button>
    </div>
    <form method="POST" id="walletForm">
      <div class="admin-modal-body">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action" value="adjust_wallet"/>
        <input type="hidden" name="user_id" id="walletUserId"/>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select class="form-input" name="adjust_type">
            <option value="credit">Credit (Add)</option>
            <option value="debit">Debit (Remove)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Amount (<?= $symbol ?>)</label>
          <input class="form-input" type="number" name="adjust_amount" min="0.01" step="0.01" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Reason</label>
          <input class="form-input" type="text" name="adjust_reason" placeholder="Admin adjustment reason" required/>
        </div>
        <div class="alert alert-warning">⚠ This action is logged and cannot be reversed easily.</div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('walletModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Apply Adjustment</button>
      </div>
    </form>
  </div>
</div>

<script>
function userAction(action, userId) {
  const labels = { suspend:'Suspend this user?', activate:'Activate this user?', ban:'PERMANENTLY BAN this user?', delete:'PERMANENTLY DELETE this user? This cannot be undone!', freeze_wallet:'Freeze this user\'s wallet?', unfreeze_wallet:'Unfreeze this user\'s wallet?' };
  if (!confirm(labels[action] || 'Continue?')) return;
  const form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = `<input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/><input name="action" value="${action}"/><input name="user_id" value="${userId}"/>`;
  document.body.appendChild(form);
  form.submit();
}
function adjustWallet(userId, username) {
  document.getElementById('walletUserId').value = userId;
  document.getElementById('walletModalTitle').textContent = 'Adjust Wallet: @' + username;
  document.getElementById('walletModal').classList.add('open');
}
function viewUser(userId) {
  window.open('/admin/user-view.php?id=' + userId, '_blank');
}
// Close dropdowns on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown')) {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
  }
});
</script>
</body>
</html>
