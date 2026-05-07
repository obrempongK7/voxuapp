<?php
/**
 * Voxu Admin — User Detail View
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('moderator');

$admin      = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$activeMenu = 'users';
$userId     = (int)($_GET['id'] ?? 0);
$settings   = getPlatformSettings();
$symbol     = $settings['currency_symbol'] ?? '$';
$success    = '';
$error      = '';

if (!$userId) redirect('/admin/users.php');

$viewUser = DB::first(
    "SELECT u.*, up.avatar, up.bio, up.country, up.phone, up.date_of_birth,
            w.balance, w.points_balance, w.is_frozen
     FROM users u
     LEFT JOIN user_profiles up ON up.user_id = u.id
     LEFT JOIN wallets w ON w.user_id = u.id
     WHERE u.id = ?",
    [$userId]
);
if (!$viewUser) redirect('/admin/users.php');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_status':
            $newStatus = sanitize($_POST['status'] ?? 'active');
            if (in_array($newStatus, ['active','suspended','banned'])) {
                DB::update('users', ['status' => $newStatus], ['id' => $userId]);
                logAdminAction((int)$admin['id'], 'user_status', "Set user #{$userId} to {$newStatus}");
                $success = "User status updated to {$newStatus}.";
            }
            break;
        case 'update_verification':
            $verified = (int)($_POST['is_verified'] ?? 0);
            DB::update('users', ['is_verified' => $verified], ['id' => $userId]);
            logAdminAction((int)$admin['id'], 'user_verify', "Set user #{$userId} verified={$verified}");
            $success = 'Verification status updated.';
            break;
        case 'adjust_balance':
            $type   = sanitize($_POST['adj_type']   ?? 'credit');
            $amount = (float)($_POST['adj_amount'] ?? 0);
            $reason = sanitize($_POST['adj_reason'] ?? 'Admin adjustment');
            if ($amount > 0) {
                if ($type === 'credit') {
                    addBalance($userId, $amount, 'admin_credit', generateToken(8), $reason);
                } else {
                    deductBalance($userId, $amount, 'admin_debit', generateToken(8), $reason);
                }
                DB::insert('admin_wallet_adjustments', [
                    'admin_id'   => (int)$admin['id'],
                    'user_id'    => $userId,
                    'type'       => $type,
                    'amount'     => $amount,
                    'reason'     => $reason,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                logAdminAction((int)$admin['id'], 'wallet_adjust', "Wallet {$type} {$amount} for user #{$userId}: {$reason}");
                $success = "Wallet adjusted: {$type} {$symbol}{$amount}.";
            }
            break;
        case 'adjust_points':
            $type   = sanitize($_POST['pts_type']   ?? 'credit');
            $pts    = (int)($_POST['pts_amount']   ?? 0);
            $reason = sanitize($_POST['pts_reason'] ?? 'Admin adjustment');
            if ($pts > 0) {
                if ($type === 'credit') {
                    addPoints($userId, $pts, 'admin_credit', $reason);
                } else {
                    deductPoints($userId, $pts, $reason);
                }
                logAdminAction((int)$admin['id'], 'points_adjust', "Points {$type} {$pts} for user #{$userId}");
                $success = "Points adjusted: {$type} {$pts} pts.";
            }
            break;
        case 'toggle_freeze':
            $frozen = $viewUser['is_frozen'] ? 0 : 1;
            DB::update('wallets', ['is_frozen' => $frozen], ['user_id' => $userId]);
            logAdminAction((int)$admin['id'], 'wallet_freeze', "Wallet for user #{$userId} frozen={$frozen}");
            $success = $frozen ? 'Wallet frozen.' : 'Wallet unfrozen.';
            break;
        case 'send_notification':
            $msg = sanitize($_POST['notif_msg'] ?? '');
            if ($msg) {
                createNotification($userId, 'admin', $msg);
                logAdminAction((int)$admin['id'], 'admin_notif', "Sent notification to user #{$userId}");
                $success = 'Notification sent.';
            }
            break;
        case 'flag_fraud':
            $reason = sanitize($_POST['fraud_reason'] ?? '');
            if ($reason) {
                DB::insert('fraud_flags', [
                    'user_id'    => $userId,
                    'reason'     => $reason,
                    'flagged_by' => 'admin',
                    'status'     => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                logAdminAction((int)$admin['id'], 'fraud_flag', "Fraud flag on user #{$userId}: {$reason}");
                $success = 'Fraud flag added.';
            }
            break;
    }
    // Reload user data
    $viewUser = DB::first(
        "SELECT u.*, up.avatar, up.bio, up.country, up.phone,
                w.balance, w.points_balance, w.is_frozen
         FROM users u LEFT JOIN user_profiles up ON up.user_id=u.id
         LEFT JOIN wallets w ON w.user_id=u.id WHERE u.id=?",
        [$userId]
    );
}

// User stats
$postCount       = DB::count('posts',       'user_id=?', [$userId]);
$statusCount     = DB::count('status_posts','user_id=?', [$userId]);
$followerCount   = DB::count('followers',   'following_id=?', [$userId]);
$totalEarned     = DB::first('SELECT COALESCE(SUM(points),0) AS s FROM points_transactions WHERE user_id=? AND type="credit"', [$userId])['s'] ?? 0;
$totalSpent      = DB::first('SELECT COALESCE(SUM(points),0) AS s FROM points_transactions WHERE user_id=? AND type="debit"',  [$userId])['s'] ?? 0;
$totalDeposited  = DB::first('SELECT COALESCE(SUM(amount),0) AS s FROM transactions WHERE user_id=? AND type="deposit" AND status="completed"', [$userId])['s'] ?? 0;
$totalWithdrawn  = DB::first('SELECT COALESCE(SUM(amount),0) AS s FROM withdrawals WHERE user_id=? AND status="completed"', [$userId])['s'] ?? 0;
$loginHistory    = DB::query('SELECT * FROM users_audit_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 10', [$userId]);
$recentTx        = DB::query('SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 8', [$userId]);
$fraudFlags      = DB::query('SELECT * FROM fraud_flags WHERE user_id=? ORDER BY created_at DESC', [$userId]);
$recentPosts     = DB::query('SELECT * FROM posts WHERE user_id=? ORDER BY created_at DESC LIMIT 5', [$userId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>User: <?= clean($viewUser['username']) ?> — Voxu Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
  <style>
    .user-hero{background:linear-gradient(135deg,#13103a,var(--bg2));border:1px solid var(--border);border-radius:12px;padding:24px;display:flex;align-items:flex-start;gap:20px;margin-bottom:20px}
    .user-avatar{width:72px;height:72px;border-radius:50%;background:var(--purple-l);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:var(--purple);flex-shrink:0;overflow:hidden}
    .user-avatar img{width:100%;height:100%;object-fit:cover}
    .action-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:20px}
    .action-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px}
    .action-card h4{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text2);margin-bottom:14px}
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="admin-page-title">
      <a href="/admin/users.php" style="color:var(--text2);text-decoration:none">Users</a>
      <span style="color:var(--text3);margin:0 6px">/</span>
      @<?= clean($viewUser['username']) ?>
    </div>
  </div>
  <div class="admin-content">
    <?php if ($success): ?><div class="alert alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">⚠ <?= clean($error)   ?></div><?php endif; ?>

    <!-- USER HERO -->
    <div class="user-hero">
      <div class="user-avatar">
        <?php if ($viewUser['avatar']): ?>
          <img src="<?= clean($viewUser['avatar']) ?>" alt="avatar"/>
        <?php else: ?>
          <?= avatarInitials($viewUser['username']) ?>
        <?php endif; ?>
      </div>
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span style="font-size:20px;font-weight:800;color:#fff"><?= clean($viewUser['username']) ?></span>
          <span class="badge <?= $viewUser['status']==='active'?'badge-green':($viewUser['status']==='banned'?'badge-danger':'badge-warning') ?>"><?= ucfirst($viewUser['status']) ?></span>
          <?php if ($viewUser['is_verified']): ?><span class="badge badge-blue">✓ Verified</span><?php endif; ?>
          <?php if ($viewUser['is_frozen']): ?><span class="badge badge-danger">❄ Wallet Frozen</span><?php endif; ?>
          <?php if (!empty($fraudFlags)): ?><span class="badge badge-danger">⚠ Fraud Flag</span><?php endif; ?>
        </div>
        <div style="font-size:13px;color:var(--text2);margin-top:6px"><?= clean($viewUser['email']) ?></div>
        <?php if ($viewUser['country']): ?><div style="font-size:12px;color:var(--text3)">📍 <?= clean($viewUser['country']) ?></div><?php endif; ?>
        <?php if ($viewUser['bio']): ?><div style="font-size:13px;color:var(--text2);margin-top:8px"><?= clean($viewUser['bio']) ?></div><?php endif; ?>
        <div style="font-size:12px;color:var(--text3);margin-top:8px">
          Joined: <?= date('d M Y H:i', strtotime($viewUser['created_at'])) ?> &nbsp;·&nbsp;
          Last IP: <?= clean($viewUser['last_ip'] ?? '—') ?> &nbsp;·&nbsp;
          Last login: <?= $viewUser['last_login'] ? timeAgo($viewUser['last_login']) : 'Never' ?>
        </div>
      </div>
      <a href="/dashboard/profile.php?u=<?= urlencode($viewUser['username']) ?>" target="_blank" class="btn btn-secondary btn-sm">View Profile ↗</a>
    </div>

    <!-- STATS ROW -->
    <div class="admin-stats" style="margin-bottom:20px">
      <div class="admin-stat-card" style="--indicator:var(--purple)"><div class="admin-stat-label">Cash Balance</div><div class="admin-stat-val"><?= $symbol ?><?= number_format((float)($viewUser['balance']??0),2) ?></div></div>
      <div class="admin-stat-card" style="--indicator:var(--green)"><div class="admin-stat-label">Points Balance</div><div class="admin-stat-val"><?= number_format((int)($viewUser['points_balance']??0)) ?></div></div>
      <div class="admin-stat-card" style="--indicator:var(--blue)"><div class="admin-stat-label">Total Earned (pts)</div><div class="admin-stat-val"><?= number_format((int)$totalEarned) ?></div></div>
      <div class="admin-stat-card" style="--indicator:var(--warning)"><div class="admin-stat-label">Total Deposited</div><div class="admin-stat-val"><?= $symbol ?><?= number_format((float)$totalDeposited,2) ?></div></div>
      <div class="admin-stat-card" style="--indicator:var(--purple)"><div class="admin-stat-label">Voice Posts</div><div class="admin-stat-val"><?= number_format($postCount) ?></div></div>
      <div class="admin-stat-card" style="--indicator:var(--blue)"><div class="admin-stat-label">Statuses</div><div class="admin-stat-val"><?= number_format($statusCount) ?></div></div>
      <div class="admin-stat-card" style="--indicator:var(--green)"><div class="admin-stat-label">Followers</div><div class="admin-stat-val"><?= number_format($followerCount) ?></div></div>
      <div class="admin-stat-card" style="--indicator:var(--danger)"><div class="admin-stat-label">Total Withdrawn</div><div class="admin-stat-val"><?= $symbol ?><?= number_format((float)$totalWithdrawn,2) ?></div></div>
    </div>

    <!-- ACTION CARDS -->
    <div class="action-grid">

      <!-- ACCOUNT STATUS -->
      <div class="action-card">
        <h4>Account Status</h4>
        <form method="POST">
          <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
          <input type="hidden" name="action"  value="update_status"/>
          <div class="form-group">
            <select class="form-input" name="status">
              <option value="active"    <?= $viewUser['status']==='active'   ?'selected':'' ?>>Active</option>
              <option value="suspended" <?= $viewUser['status']==='suspended'?'selected':'' ?>>Suspended</option>
              <option value="banned"    <?= $viewUser['status']==='banned'   ?'selected':'' ?>>Banned</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-full">Update Status</button>
        </form>
        <div style="margin-top:10px">
          <form method="POST">
            <input type="hidden" name="_csrf"          value="<?= csrfToken() ?>"/>
            <input type="hidden" name="action"         value="update_verification"/>
            <input type="hidden" name="is_verified"    value="<?= $viewUser['is_verified'] ? '0' : '1' ?>"/>
            <button type="submit" class="btn btn-sm w-full <?= $viewUser['is_verified']?'btn-secondary':'btn-success' ?>">
              <?= $viewUser['is_verified'] ? '✓ Remove Verification' : '✓ Mark as Verified' ?>
            </button>
          </form>
        </div>
      </div>

      <!-- WALLET ADJUSTMENT (CASH) -->
      <div class="action-card">
        <h4>Adjust Cash Balance</h4>
        <form method="POST">
          <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
          <input type="hidden" name="action" value="adjust_balance"/>
          <div class="form-group">
            <select class="form-input" name="adj_type">
              <option value="credit">Credit (Add)</option>
              <option value="debit">Debit (Remove)</option>
            </select>
          </div>
          <div class="form-group"><input class="form-input" type="number" name="adj_amount" placeholder="Amount (<?= $symbol ?>)" min="0.01" step="0.01" required/></div>
          <div class="form-group"><input class="form-input" type="text" name="adj_reason" placeholder="Reason (required)" required/></div>
          <button type="submit" class="btn btn-primary btn-sm w-full">Apply Adjustment</button>
        </form>
      </div>

      <!-- POINTS ADJUSTMENT -->
      <div class="action-card">
        <h4>Adjust Points</h4>
        <form method="POST">
          <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
          <input type="hidden" name="action" value="adjust_points"/>
          <div class="form-group">
            <select class="form-input" name="pts_type">
              <option value="credit">Credit Points</option>
              <option value="debit">Deduct Points</option>
            </select>
          </div>
          <div class="form-group"><input class="form-input" type="number" name="pts_amount" placeholder="Points amount" min="1" required/></div>
          <div class="form-group"><input class="form-input" type="text" name="pts_reason" placeholder="Reason" required/></div>
          <button type="submit" class="btn btn-primary btn-sm w-full">Apply</button>
        </form>
      </div>

      <!-- WALLET FREEZE -->
      <div class="action-card">
        <h4>Wallet Control</h4>
        <p style="font-size:13px;color:var(--text2);margin-bottom:12px">
          Status: <strong style="color:<?= $viewUser['is_frozen']?'var(--danger)':'var(--green)' ?>"><?= $viewUser['is_frozen']?'Frozen':'Active' ?></strong>
        </p>
        <form method="POST">
          <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
          <input type="hidden" name="action" value="toggle_freeze"/>
          <button type="submit" class="btn btn-sm w-full <?= $viewUser['is_frozen']?'btn-success':'btn-danger' ?>">
            <?= $viewUser['is_frozen'] ? '🔥 Unfreeze Wallet' : '❄ Freeze Wallet' ?>
          </button>
        </form>
      </div>

      <!-- SEND NOTIFICATION -->
      <div class="action-card">
        <h4>Send Notification</h4>
        <form method="POST">
          <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
          <input type="hidden" name="action" value="send_notification"/>
          <div class="form-group"><textarea class="form-input" name="notif_msg" rows="3" placeholder="Notification message…" required></textarea></div>
          <button type="submit" class="btn btn-primary btn-sm w-full">Send</button>
        </form>
      </div>

      <!-- FRAUD FLAG -->
      <div class="action-card">
        <h4>Flag for Fraud</h4>
        <?php if (!empty($fraudFlags)): ?>
          <?php foreach ($fraudFlags as $ff): ?>
            <div style="font-size:12px;padding:6px 10px;background:var(--danger-l);border-radius:6px;margin-bottom:8px;color:var(--danger)">
              ⚠ <?= clean($ff['reason']) ?> <span style="color:var(--text3)">(<?= timeAgo($ff['created_at']) ?>)</span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
          <input type="hidden" name="action" value="flag_fraud"/>
          <div class="form-group"><input class="form-input" type="text" name="fraud_reason" placeholder="Fraud reason…" required/></div>
          <button type="submit" class="btn btn-danger btn-sm w-full">Add Flag</button>
        </form>
      </div>
    </div>

    <!-- RECENT POSTS -->
    <?php if (!empty($recentPosts)): ?>
    <div class="table-card" style="margin-bottom:16px">
      <div class="table-header"><span class="table-title">Recent Voice Posts</span><a href="/admin/posts.php?q=<?= urlencode($viewUser['username']) ?>" class="btn btn-secondary btn-sm">All Posts</a></div>
      <table>
        <thead><tr><th>Title</th><th>Replies</th><th>Energy</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($recentPosts as $rp): ?>
          <tr>
            <td style="font-size:13px"><?= clean($rp['title']) ?></td>
            <td><?= (int)$rp['reply_count'] ?></td>
            <td>⚡ <?= (int)$rp['energy_count'] ?></td>
            <td style="font-size:12px"><?= timeAgo($rp['created_at']) ?></td>
            <td><a href="/post/<?= $rp['id'] ?>" target="_blank" style="color:var(--blue);font-size:12px">View ↗</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- RECENT TRANSACTIONS -->
    <?php if (!empty($recentTx)): ?>
    <div class="table-card" style="margin-bottom:16px">
      <div class="table-header"><span class="table-title">Recent Transactions</span><a href="/admin/finance.php?q=<?= urlencode($viewUser['username']) ?>" class="btn btn-secondary btn-sm">All Transactions</a></div>
      <table>
        <thead><tr><th>Type</th><th>Amount</th><th>Description</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recentTx as $tx):
            $isC = in_array($tx['type'],['deposit','transfer_in','reward','admin_credit']);
          ?>
          <tr>
            <td><span class="badge badge-muted" style="font-size:10px"><?= str_replace('_',' ',$tx['type']) ?></span></td>
            <td style="font-weight:600;color:<?= $isC?'var(--green)':'var(--danger)' ?>"><?= $isC?'+':'-' ?><?= $symbol ?><?= number_format((float)$tx['amount'],2) ?></td>
            <td style="font-size:12px;color:var(--text2)"><?= clean(substr($tx['description']??'',0,50)) ?></td>
            <td><span class="badge <?= $tx['status']==='completed'?'badge-green':'badge-muted' ?>" style="font-size:10px"><?= $tx['status'] ?></span></td>
            <td style="font-size:12px"><?= timeAgo($tx['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- USER AUDIT LOGS -->
    <?php if (!empty($loginHistory)): ?>
    <div class="table-card" style="margin-bottom:16px">
      <div class="table-header"><span class="table-title">User Activity Log</span></div>
      <table>
        <thead><tr><th>Action</th><th>Description</th><th>IP Address</th><th>Date & Time</th></tr></thead>
        <tbody>
          <?php foreach ($loginHistory as $log): ?>
          <tr>
            <td style="font-size:12px">
              <span class="badge badge-muted" style="font-size:10px">
                <?= str_replace('_',' ',$log['action']) ?>
              </span>
            </td>
            <td style="font-size:13px;color:var(--text2)"><?= clean($log['description']) ?></td>
            <td style="font-size:12px;font-family:monospace"><?= clean($log['ip_address'] ?? '—') ?></td>
            <td style="font-size:12px;color:var(--text3)"><?= timeAgo($log['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
