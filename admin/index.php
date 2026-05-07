<?php
// @author  Jcode | ObrempongK
// admin/index.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('moderator');

try { $admin = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]); } catch (Throwable) { $admin = ['id'=>0,'name'=>'Admin','role'=>'admin','email'=>'']; }

// Dashboard stats
$stats = [
    'total_users'    => DB::count('users'),
    'active_users'   => DB::count('users','status="active"'),
    'total_posts'    => DB::count('posts'),
    'total_statuses' => DB::count('status_posts'),
    'total_balance'  => DB::first('SELECT COALESCE(SUM(balance),0) AS s FROM wallets')['s'] ?? 0,
    'total_points'   => DB::first('SELECT COALESCE(SUM(points_balance),0) AS s FROM wallets')['s'] ?? 0,
    'pending_withdrawals' => DB::count('withdrawals','status="pending"'),
    'open_reports'   => DB::count('reports','status="open"'),
];

// New users last 7 days
$newUsers7d = DB::first("SELECT COUNT(*) AS n FROM users WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")['n'] ?? 0;
// Today's activity
$todayPosts = DB::first("SELECT COUNT(*) AS n FROM posts WHERE DATE(created_at)=CURDATE()")['n'] ?? 0;

// Recent users
$recentUsers = DB::query(
    "SELECT u.*, up.avatar FROM users u LEFT JOIN user_profiles up ON up.user_id=u.id ORDER BY u.created_at DESC LIMIT 10"
);
// Recent transactions
$recentTx = DB::query(
    "SELECT t.*, u.username FROM transactions t JOIN users u ON u.id=t.user_id ORDER BY t.created_at DESC LIMIT 10"
);
// Recent notifications
$recentNotifs = DB::query(
    "SELECT n.*, u.username FROM notifications n JOIN users u ON u.id=n.user_id WHERE n.user_id != 0 ORDER BY n.created_at DESC LIMIT 10"
);

$settings = getPlatformSettings();
$symbol   = $settings['currency_symbol'] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Admin Dashboard — Voxu</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
</head>
<body>

<!-- SIDEBAR -->
<?php $activeMenu = 'dashboard'; require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="admin-main">
  <!-- TOP BAR -->
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="admin-page-title">Dashboard</div>
    <div class="topbar-actions">
      <input type="text" class="search-input" placeholder="Search users…" id="globalSearch"/>
      <div class="admin-avatar"><?= avatarInitials($admin['name']) ?></div>
    </div>
  </div>

  <div class="admin-content">
    <!-- STATS -->
    <div class="admin-stats">
      <div class="admin-stat-card" style="--indicator:var(--purple)">
        <div class="admin-stat-label">Total Users</div>
        <div class="admin-stat-val"><?= number_format($stats['total_users']) ?></div>
        <div class="admin-stat-sub up">+<?= number_format($newUsers7d) ?> this week</div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--blue)">
        <div class="admin-stat-label">Active Users</div>
        <div class="admin-stat-val"><?= number_format($stats['active_users']) ?></div>
        <div class="admin-stat-sub"><?= $stats['total_users'] > 0 ? round($stats['active_users']/$stats['total_users']*100) : 0 ?>% of total</div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--green)">
        <div class="admin-stat-label">Platform Cash</div>
        <div class="admin-stat-val"><?= $symbol ?><?= number_format((float)$stats['total_balance'],2) ?></div>
        <div class="admin-stat-sub">Across all wallets</div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--warning)">
        <div class="admin-stat-label">Total Points</div>
        <div class="admin-stat-val"><?= number_format((int)$stats['total_points']) ?></div>
        <div class="admin-stat-sub">Platform-wide</div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--purple)">
        <div class="admin-stat-label">Voice Posts</div>
        <div class="admin-stat-val"><?= number_format($stats['total_posts']) ?></div>
        <div class="admin-stat-sub up">+<?= number_format($todayPosts) ?> today</div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--blue)">
        <div class="admin-stat-label">Statuses</div>
        <div class="admin-stat-val"><?= number_format($stats['total_statuses']) ?></div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--danger)">
        <div class="admin-stat-label">Pending Withdrawals</div>
        <div class="admin-stat-val" style="color:var(--warning)"><?= number_format($stats['pending_withdrawals']) ?></div>
        <a href="/admin/withdrawals.php" style="font-size:11px;color:var(--purple)">Review →</a>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--danger)">
        <div class="admin-stat-label">Open Reports</div>
        <div class="admin-stat-val" style="color:var(--danger)"><?= number_format($stats['open_reports']) ?></div>
        <a href="/admin/reports.php" style="font-size:11px;color:var(--purple)">Review →</a>
      </div>
    </div>

    <!-- CHARTS ROW -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
      <div class="chart-card">
        <div class="chart-title">User Registrations (Last 7 Days)</div>
        <div class="chart-area" id="regChart">
          <?php
          for ($i = 6; $i >= 0; $i--) {
              $date = date('Y-m-d', strtotime("-{$i} days"));
              $cnt  = DB::first("SELECT COUNT(*) AS n FROM users WHERE DATE(created_at)=?", [$date])['n'] ?? 0;
              $pct  = max(4, min(100, $cnt * 10 + 4));
              echo "<div class='chart-bar' style='height:{$pct}%' title='{$date}: {$cnt}'></div>";
          }
          ?>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-title">Withdrawals Status</div>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:16px">
          <?php
          $wStatuses = DB::query("SELECT status, COUNT(*) AS n FROM withdrawals GROUP BY status");
          $wColors   = ['pending'=>'var(--warning)','approved'=>'var(--blue)','completed'=>'var(--green)','rejected'=>'var(--danger)','processing'=>'var(--purple)'];
          foreach ($wStatuses as $ws): $c = $wColors[$ws['status']] ?? 'var(--text3)'; ?>
          <div class="flex items-center gap-3">
            <div style="width:10px;height:10px;border-radius:50%;background:<?=$c?>;flex-shrink:0"></div>
            <span style="font-size:13px;flex:1;color:var(--text2)"><?= ucfirst($ws['status']) ?></span>
            <span style="font-size:13px;font-weight:600;color:<?=$c?>"><?= $ws['n'] ?></span>
          </div>
          <?php endforeach; if (empty($wStatuses)): echo '<p style="color:var(--text3);font-size:13px">No withdrawal data yet</p>'; endif; ?>
        </div>
      </div>
    </div>

    <!-- TABLES ROW -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px">
      <!-- RECENT USERS -->
      <div class="table-card">
        <div class="table-header">
          <span class="table-title">Recent Users</span>
          <a href="/admin/users.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <table>
          <thead><tr><th>User</th><th>Status</th><th>Joined</th></tr></thead>
          <tbody>
            <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td>
                <div class="flex items-center gap-2">
                  <div style="width:28px;height:28px;border-radius:50%;background:var(--purple-l);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--purple);flex-shrink:0">
                    <?= avatarInitials($u['username']) ?>
                  </div>
                  <div>
                    <div style="font-size:13px;font-weight:500;color:var(--text)"><?= clean($u['username']) ?></div>
                    <div style="font-size:11px;color:var(--text3)"><?= clean($u['email']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="badge <?= $u['status']==='active'?'badge-green':'badge-danger' ?>"><?= $u['status'] ?></span></td>
              <td><?= date('d M', strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentUsers)): ?><tr><td colspan="3" style="text-align:center;color:var(--text3)">No users yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- RECENT TRANSACTIONS -->
      <div class="table-card">
        <div class="table-header">
          <span class="table-title">Recent Transactions</span>
          <a href="/admin/finance.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <table>
          <thead><tr><th>User</th><th>Type</th><th>Amount</th></tr></thead>
          <tbody>
            <?php foreach ($recentTx as $tx): $isC = in_array($tx['type'],['deposit','transfer_in','reward']); ?>
            <tr>
              <td style="font-size:13px"><?= clean($tx['username']) ?></td>
              <td><span class="badge badge-muted" style="font-size:10px"><?= str_replace('_',' ',$tx['type']) ?></span></td>
              <td style="font-weight:600;color:<?= $isC?'var(--green)':'var(--danger)' ?>"><?= $isC?'+':'-' ?><?= $symbol ?><?= number_format((float)$tx['amount'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentTx)): ?><tr><td colspan="3" style="text-align:center;color:var(--text3)">No transactions yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- RECENT NOTIFICATIONS -->
      <div class="table-card">
        <div class="table-header">
          <span class="table-title">Recent Notifications</span>
          <a href="/admin/notifications.php" class="btn btn-secondary btn-sm">Send New</a>
        </div>
        <table>
          <thead><tr><th>User</th><th>Type</th><th>Message</th><th>Sent</th></tr></thead>
          <tbody>
            <?php foreach ($recentNotifs as $n): ?>
            <tr>
              <td style="font-size:13px">
                <div class="flex items-center gap-2">
                  <div style="width:24px;height:24px;border-radius:50%;background:var(--blue-l);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:var(--blue);flex-shrink:0">
                    <?= substr(clean($n['username']), 0, 2) ?>
                  </div>
                  <span style="font-size:12px;color:var(--text2)"><?= clean($n['username']) ?></span>
                </div>
              </td>
              <td><span class="badge badge-muted" style="font-size:10px"><?= ucfirst($n['type']) ?></span></td>
              <td style="font-size:12px;color:var(--text2);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= clean($n['message']) ?>"><?= clean(substr($n['message'], 0, 50)) ?>…</td>
              <td style="font-size:11px;color:var(--text3)"><?= timeAgo($n['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentNotifs)): ?><tr><td colspan="4" style="text-align:center;color:var(--text3)">No notifications sent yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /admin-content -->
</div><!-- /admin-main -->

<script>
// Live search
let sTimer;
document.getElementById('globalSearch')?.addEventListener('input', function() {
  clearTimeout(sTimer);
  const q = this.value.trim();
  if (q.length < 2) return;
  sTimer = setTimeout(async () => {
    const res = await fetch('/api/v1/admin/search?q=' + encodeURIComponent(q));
    // handle results
  }, 400);
});
</script>
</body>
</html>
