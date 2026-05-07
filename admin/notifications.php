<?php
/**
 * Voxu Admin — Announcements & Notifications
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('admin');

$admin      = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$activeMenu = 'notifications';
$success    = '';
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_broadcast') {
        $message    = sanitize($_POST['message']     ?? '');
        $type       = sanitize($_POST['notif_type']  ?? 'announcement');
        $targetGroup= sanitize($_POST['target_group']?? 'all');
        if (!$message) {
            $error = 'Message is required.';
        } else {
            // Get target users
            if ($targetGroup === 'all') {
                $users = DB::query('SELECT id FROM users WHERE status="active"');
            } elseif ($targetGroup === 'new') {
                $users = DB::query('SELECT id FROM users WHERE status="active" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
            } elseif ($targetGroup === 'active_week') {
                $users = DB::query('SELECT id FROM users WHERE status="active" AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
            } else {
                $users = [];
            }
            $count = 0;
            foreach ($users as $u) {
                createNotification((int)$u['id'], $type, $message);
                $count++;
            }
            logAdminAction((int)$admin['id'], 'broadcast_sent', "Sent broadcast to {$count} users: {$message}");
            $success = "Announcement sent to {$count} users.";
        }
    } elseif ($action === 'send_single') {
        $username = sanitize($_POST['username'] ?? '');
        $message  = sanitize($_POST['single_message'] ?? '');
        if (!$username || !$message) {
            $error = 'Username and message required.';
        } else {
            $target = DB::first('SELECT id FROM users WHERE username=?', [strtolower($username)]);
            if (!$target) {
                $error = "User @{$username} not found.";
            } else {
                createNotification((int)$target['id'], 'admin', $message);
                logAdminAction((int)$admin['id'], 'single_notif', "Sent notification to @{$username}");
                $success = "Notification sent to @{$username}.";
            }
        }
    } elseif ($action === 'delete_notif') {
        $notifId = (int)($_POST['notif_id'] ?? 0);
        if ($notifId) {
            DB::exec('DELETE FROM notifications WHERE id=? AND user_id=0', [$notifId]);
            $success = 'Notification deleted.';
        }
    }
}

$recentBroadcasts = DB::query(
    "SELECT l.*, a.name AS sender FROM admin_activity_logs l
     LEFT JOIN admins a ON a.id=l.admin_id
     WHERE l.action='broadcast_sent'
     ORDER BY l.created_at DESC LIMIT 20"
);
$totalUsers  = DB::count('users', 'status="active"');
$newUsers7d  = DB::count('users', 'status="active" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$activeWeek  = DB::count('users', 'status="active" AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Notifications — Voxu Admin</title>
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
    <div class="admin-page-title">Announcements &amp; Notifications</div>
  </div>
  <div class="admin-content">
    <?php if ($success): ?><div class="alert alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">⚠ <?= clean($error)   ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

      <!-- BROADCAST ANNOUNCEMENT -->
      <div class="table-card">
        <div class="table-header"><span class="table-title">📢 Send Broadcast Announcement</span></div>
        <form method="POST" style="padding:20px">
          <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
          <input type="hidden" name="action" value="send_broadcast"/>
          <div class="form-group">
            <label class="form-label">Notification Type</label>
            <select class="form-input" name="notif_type">
              <option value="announcement">📢 Announcement</option>
              <option value="system">⚙ System Update</option>
              <option value="reward">🎁 Reward</option>
              <option value="campaign">🎯 Campaign</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Target Audience</label>
            <select class="form-input" name="target_group">
              <option value="all">All Users (<?= number_format($totalUsers) ?>)</option>
              <option value="active_week">Active this Week (<?= number_format($activeWeek) ?>)</option>
              <option value="new">New Users – Last 7 Days (<?= number_format($newUsers7d) ?>)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Message</label>
            <textarea class="form-input" name="message" rows="4" required
              placeholder="Type your announcement here…" maxlength="500"></textarea>
            <div class="form-hint">Max 500 characters. Will appear in users' in-app notification feed.</div>
          </div>
          <button type="submit" class="btn btn-primary" onclick="return confirm('Send this announcement?')">
            📢 Send Announcement
          </button>
        </form>
      </div>

      <!-- SINGLE USER NOTIFICATION -->
      <div>
        <div class="table-card" style="margin-bottom:16px">
          <div class="table-header"><span class="table-title">🔔 Send to Specific User</span></div>
          <form method="POST" style="padding:20px">
            <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
            <input type="hidden" name="action" value="send_single"/>
            <div class="form-group">
              <label class="form-label">Username</label>
              <input class="form-input" type="text" name="username" placeholder="@username (without @)" required/>
            </div>
            <div class="form-group">
              <label class="form-label">Message</label>
              <textarea class="form-input" name="single_message" rows="3" required placeholder="Your message to this user…"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Send to User</button>
          </form>
        </div>

        <!-- STATS -->
        <div class="table-card">
          <div class="table-header"><span class="table-title">📊 Audience Stats</span></div>
          <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
            <div class="flex justify-between" style="font-size:13px"><span style="color:var(--text2)">Total active users</span><strong><?= number_format($totalUsers) ?></strong></div>
            <div class="flex justify-between" style="font-size:13px"><span style="color:var(--text2)">Active this week</span><strong style="color:var(--green)"><?= number_format($activeWeek) ?></strong></div>
            <div class="flex justify-between" style="font-size:13px"><span style="color:var(--text2)">New (last 7 days)</span><strong style="color:var(--blue)"><?= number_format($newUsers7d) ?></strong></div>
          </div>
        </div>
      </div>
    </div>

    <!-- BROADCAST HISTORY -->
    <div class="table-card">
      <div class="table-header"><span class="table-title">Broadcast History</span></div>
      <table>
        <thead><tr><th>Sent By</th><th>Message</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recentBroadcasts as $b): ?>
          <tr>
            <td style="font-size:13px;font-weight:500"><?= clean($b['sender'] ?? 'Admin') ?></td>
            <td style="font-size:13px;color:var(--text2)"><?= clean($b['description']) ?></td>
            <td style="font-size:12px"><?= timeAgo($b['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentBroadcasts)): ?><tr><td colspan="3" style="text-align:center;padding:24px;color:var(--text3)">No broadcasts sent yet</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
