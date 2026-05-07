<?php
/**
 * Voxu Admin — Status Posts Management
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('moderator');

$admin      = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$activeMenu = 'statuses';
$settings   = getPlatformSettings();
$symbol     = $settings['currency_symbol'] ?? '$';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action   = $_POST['action']    ?? '';
    $statusId = (int)($_POST['status_id'] ?? 0);
    if ($action === 'remove' && $statusId) {
        DB::update('status_posts', ['status' => 'removed'], ['id' => $statusId]);
        logAdminAction((int)$admin['id'], 'status_remove', "Removed status #{$statusId}");
    } elseif ($action === 'restore' && $statusId) {
        DB::update('status_posts', ['status' => 'active'], ['id' => $statusId]);
        logAdminAction((int)$admin['id'], 'status_restore', "Restored status #{$statusId}");
    } elseif ($action === 'save_settings') {
        setSetting('reward_per_view',  sanitize($_POST['reward_per_view']  ?? '1'));
        setSetting('reward_per_click', sanitize($_POST['reward_per_click'] ?? '3'));
        setSetting('status_expiry_hours', sanitize($_POST['status_expiry_hours'] ?? '24'));
        setSetting('max_status_per_day',  sanitize($_POST['max_status_per_day']  ?? '10'));
        logAdminAction((int)$admin['id'], 'status_settings', 'Updated status system settings');
    }
    header('Location: statuses.php');
    exit;
}

$filter  = sanitize($_GET['status'] ?? 'active');
$search  = sanitize($_GET['q']      ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = 's.status = ?';
$params = [$filter];
if ($search) {
    $where  .= ' AND (s.caption LIKE ? OR u.username LIKE ? OR s.source_label LIKE ?)';
    $params  = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}

$total    = DB::first("SELECT COUNT(*) AS n FROM status_posts s JOIN users u ON u.id=s.user_id WHERE {$where}", $params)['n'] ?? 0;
$statuses = DB::query(
    "SELECT s.*, u.username FROM status_posts s JOIN users u ON u.id=s.user_id
     WHERE {$where} ORDER BY s.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
    $params
);
$totalPages = max(1, ceil($total / $perPage));

// Summary stats
$todayViews  = DB::first("SELECT COALESCE(SUM(views_count),0) AS n FROM status_posts WHERE DATE(created_at)=CURDATE()")['n'] ?? 0;
$todayClicks = DB::first("SELECT COALESCE(SUM(clicks_count),0) AS n FROM status_posts WHERE DATE(created_at)=CURDATE()")['n'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Statuses — Voxu Admin</title>
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
    <div class="admin-page-title">Status Posts</div>
    <div class="topbar-actions">
      <button class="btn btn-secondary btn-sm" onclick="document.getElementById('settingsPanel').classList.toggle('hidden')">⚙ Status Settings</button>
      <form method="GET" style="display:flex;gap:8px">
        <input type="text" class="search-input" name="q" value="<?= clean($search) ?>" placeholder="Search caption, user…"/>
        <select class="search-input" name="status" style="width:auto">
          <option value="active"  <?= $filter==='active'  ?'selected':'' ?>>Active</option>
          <option value="expired" <?= $filter==='expired' ?'selected':'' ?>>Expired</option>
          <option value="removed" <?= $filter==='removed' ?'selected':'' ?>>Removed</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      </form>
    </div>
  </div>
  <div class="admin-content">

    <!-- SETTINGS PANEL -->
    <div id="settingsPanel" class="hidden" style="margin-bottom:20px">
      <div class="table-card">
        <div class="table-header"><span class="table-title">Status System Settings</span></div>
        <form method="POST" style="padding:20px">
          <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
          <input type="hidden" name="action"  value="save_settings"/>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
            <div class="form-group">
              <label class="form-label">Points per View</label>
              <input class="form-input" type="number" name="reward_per_view" value="<?= (int)($settings['reward_per_view']??1) ?>" min="0"/>
            </div>
            <div class="form-group">
              <label class="form-label">Points per Click</label>
              <input class="form-input" type="number" name="reward_per_click" value="<?= (int)($settings['reward_per_click']??3) ?>" min="0"/>
            </div>
            <div class="form-group">
              <label class="form-label">Expiry (hours)</label>
              <input class="form-input" type="number" name="status_expiry_hours" value="<?= (int)($settings['status_expiry_hours']??24) ?>" min="1"/>
            </div>
            <div class="form-group">
              <label class="form-label">Max per Day / User</label>
              <input class="form-input" type="number" name="max_status_per_day" value="<?= (int)($settings['max_status_per_day']??10) ?>" min="1"/>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px">Save Settings</button>
        </form>
      </div>
    </div>

    <!-- STATS ROW -->
    <div class="admin-stats" style="margin-bottom:20px">
      <div class="admin-stat-card" style="--indicator:var(--blue)">
        <div class="admin-stat-label">Today Views</div>
        <div class="admin-stat-val"><?= number_format((int)$todayViews) ?></div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--green)">
        <div class="admin-stat-label">Today Clicks</div>
        <div class="admin-stat-val"><?= number_format((int)$todayClicks) ?></div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--purple)">
        <div class="admin-stat-label">Total Active</div>
        <div class="admin-stat-val"><?= number_format(DB::count('status_posts','status="active"')) ?></div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--warning)">
        <div class="admin-stat-label">Total All Time</div>
        <div class="admin-stat-val"><?= number_format(DB::count('status_posts')) ?></div>
      </div>
    </div>

    <div class="table-card">
      <div class="table-header"><span class="table-title"><?= ucfirst($filter) ?> Statuses (<?= number_format($total) ?>)</span></div>
      <table>
        <thead><tr><th>User</th><th>Type</th><th>Caption</th><th>Views</th><th>Clicks</th><th>Pts Earned</th><th>Expires</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($statuses as $s): ?>
          <tr>
            <td><a href="/admin/user-view.php?id=<?= $s['user_id'] ?>" style="color:var(--blue);font-size:13px">@<?= clean($s['username']) ?></a></td>
            <td><span class="badge badge-muted"><?= strtoupper($s['type']) ?></span></td>
            <td style="max-width:180px">
              <div class="truncate" style="font-size:13px"><?= clean($s['caption'] ?: '—') ?></div>
              <?php if ($s['source_label']): ?><div style="font-size:11px;color:var(--text3)">📌 <?= clean($s['source_label']) ?></div><?php endif; ?>
              <?php if ($s['contact_link']): ?><div style="font-size:11px;color:var(--blue)">🔗 Has contact link</div><?php endif; ?>
            </td>
            <td>👁 <?= number_format((int)$s['views_count']) ?></td>
            <td>📞 <?= number_format((int)$s['clicks_count']) ?></td>
            <td style="color:var(--green)">⚡ <?= number_format((int)$s['earnings_points']) ?></td>
            <td style="font-size:11px;color:var(--text3)"><?= date('d M H:i', strtotime($s['expires_at'])) ?></td>
            <td>
              <?php if ($s['status'] === 'active'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf"      value="<?= csrfToken() ?>"/>
                <input type="hidden" name="action"     value="remove"/>
                <input type="hidden" name="status_id"  value="<?= $s['id'] ?>"/>
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remove this status?')">Remove</button>
              </form>
              <?php elseif ($s['status'] === 'removed'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf"      value="<?= csrfToken() ?>"/>
                <input type="hidden" name="action"     value="restore"/>
                <input type="hidden" name="status_id"  value="<?= $s['id'] ?>"/>
                <button type="submit" class="btn btn-success btn-sm">Restore</button>
              </form>
              <?php else: ?>
              <span style="font-size:11px;color:var(--text3)">Expired</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($statuses)): ?><tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text3)">No statuses found</td></tr><?php endif; ?>
        </tbody>
      </table>
      <div class="table-footer">
        <span>Page <?= $page ?> of <?= $totalPages ?></span>
        <div class="pagination">
          <?php if ($page>1): ?><a href="?page=<?=$page-1?>&status=<?=urlencode($filter)?>&q=<?=urlencode($search)?>" class="page-btn">←</a><?php endif; ?>
          <?php for($p2=max(1,$page-2);$p2<=min($totalPages,$page+2);$p2++): ?>
            <a href="?page=<?=$p2?>&status=<?=urlencode($filter)?>&q=<?=urlencode($search)?>" class="page-btn <?=$p2===$page?'active':''?>"><?=$p2?></a>
          <?php endfor; ?>
          <?php if ($page<$totalPages): ?><a href="?page=<?=$page+1?>&status=<?=urlencode($filter)?>&q=<?=urlencode($search)?>" class="page-btn">→</a><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
