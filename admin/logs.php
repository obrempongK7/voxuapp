<?php
/**
 * Voxu Admin — Audit Logs
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('moderator');

$admin      = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$activeMenu = 'logs';
$tab        = sanitize($_GET['tab']  ?? 'admin');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 30;
$offset     = ($page - 1) * $perPage;

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if ($tab === 'user') {
        $rows = DB::query("SELECT l.*, u.username FROM users_audit_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 10000");
        $cols = ['id','user_id','username','action','description','ip_address','created_at'];
        $fn   = 'voxu_user_audit_' . date('Ymd_His') . '.csv';
    } else {
        $rows = DB::query("SELECT l.*, a.name AS admin_name FROM admin_activity_logs l LEFT JOIN admins a ON a.id=l.admin_id ORDER BY l.created_at DESC LIMIT 10000");
        $cols = ['id','admin_id','admin_name','action','description','ip_address','created_at'];
        $fn   = 'voxu_admin_logs_' . date('Ymd_His') . '.csv';
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    header('Pragma: no-cache'); header('Expires: 0');
    $out = fopen('php://output','w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel
    fputcsv($out, $cols);
    foreach ($rows as $r) fputcsv($out, array_map(fn($k) => $r[$k] ?? '', $cols));
    fclose($out);
    exit;
}

try {
    if ($tab === 'user') {
        $total = DB::count('users_audit_logs');
        $logs  = DB::query("SELECT l.*, u.username FROM users_audit_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
    } else {
        $total = DB::count('admin_activity_logs');
        $logs  = DB::query("SELECT l.*, a.name AS admin_name, a.role AS admin_role FROM admin_activity_logs l LEFT JOIN admins a ON a.id=l.admin_id ORDER BY l.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
    }
} catch (Throwable $e) {
    $total = 0; $logs = [];
    error_log('Audit logs query error: ' . $e->getMessage());
}
$totalPages = max(1, ceil($total / $perPage));
try { $adminCount = DB::count('admin_activity_logs'); } catch (Throwable) { $adminCount = 0; }
try { $userCount  = DB::count('users_audit_logs');    } catch (Throwable) { $userCount  = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Audit Logs — Voxu Admin</title>
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
    <div class="admin-page-title">Audit Logs</div>
    <div class="topbar-actions">
      <span style="font-size:11px;color:var(--text3)">🔒 Read-only — cannot be altered</span>
    </div>
  </div>
  <div class="admin-content">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap">
      <a href="?tab=admin" class="btn btn-sm <?= $tab==='admin'?'btn-primary':'btn-secondary' ?>">🛡 Admin Activity (<?= number_format($adminCount) ?>)</a>
      <a href="?tab=user"  class="btn btn-sm <?= $tab==='user' ?'btn-primary':'btn-secondary' ?>">👤 User Audit (<?= number_format($userCount) ?>)</a>
      <a href="?tab=<?= $tab ?>&export=csv" class="btn btn-secondary btn-sm" style="margin-left:auto">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Download CSV
      </a>
    </div>

    <div class="table-card">
      <div class="table-header">
        <span class="table-title"><?= $tab==='admin'?'Admin Activity Log':'User Audit Log' ?> — Page <?= $page ?>/<?= $totalPages ?></span>
      </div>
      <table>
        <thead>
          <?php if ($tab === 'admin'): ?>
          <tr><th>#</th><th>Admin</th><th>Action</th><th>Description</th><th>IP</th><th>Date &amp; Time</th></tr>
          <?php else: ?>
          <tr><th>#</th><th>User</th><th>Action</th><th>Description</th><th>IP</th><th>Date &amp; Time</th></tr>
          <?php endif; ?>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td style="font-size:11px;color:var(--text3)"><?= $log['id'] ?></td>
            <td>
              <?php if ($tab === 'admin'): ?>
                <div style="font-size:13px;font-weight:500;color:var(--text)"><?= clean($log['admin_name'] ?? 'System') ?></div>
                <?php if (!empty($log['admin_role'])): ?><div style="font-size:10px;color:var(--text3)"><?= ucfirst($log['admin_role']) ?></div><?php endif; ?>
              <?php else: ?>
                <?php if (!empty($log['username'])): ?>
                  <a href="/admin/user-view.php?id=<?= $log['user_id'] ?>" style="color:var(--blue);font-size:13px">@<?= clean($log['username']) ?></a>
                <?php else: ?>
                  <span style="color:var(--text3);font-size:12px">User #<?= $log['user_id'] ?></span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $tab==='admin'?'badge-purple':'badge-blue' ?>" style="font-size:10px"><?= clean($log['action']) ?></span></td>
            <td style="font-size:12px;color:var(--text2);max-width:280px;word-break:break-word"><?= clean($log['description'] ?? '') ?></td>
            <td style="font-size:11px;font-family:monospace;color:var(--text3)"><?= clean($log['ip_address'] ?? '') ?></td>
            <td style="font-size:12px;white-space:nowrap"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($logs)): ?>
            <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text3)">No log entries yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <div class="table-footer">
        <span>Showing <?= min($offset+1,$total) ?>–<?= min($offset+$perPage,$total) ?> of <?= number_format($total) ?></span>
        <div class="pagination">
          <?php if ($page>1): ?><a href="?tab=<?=$tab?>&page=<?=$page-1?>" class="page-btn">←</a><?php endif; ?>
          <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
            <a href="?tab=<?=$tab?>&page=<?=$p?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
          <?php endfor; ?>
          <?php if ($page<$totalPages): ?><a href="?tab=<?=$tab?>&page=<?=$page+1?>" class="page-btn">→</a><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
