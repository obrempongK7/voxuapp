<?php
// @author  Jcode | ObrempongK
// admin/reports.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('moderator');

$admin  = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$page   = max(1,(int)($_GET['page']??1));
$filter = sanitize($_GET['status']??'open');
$perPage= 25;
$offset = ($page-1)*$perPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $rId    = (int)($_POST['report_id']??0);
    $action = $_POST['action'] ?? '';
    if (in_array($action,['resolve','dismiss'])) {
        $newStatus = $action === 'resolve' ? 'resolved' : 'dismissed';
        DB::update('reports', [
            'status'      => $newStatus,
            'reviewed_by' => (int)$admin['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
        ], ['id'=>$rId]);
        logAdminAction((int)$admin['id'],"report_{$action}","Report #{$rId} {$action}d");
        if ($action === 'resolve') {
            // Remove reported content
            $report = DB::first('SELECT * FROM reports WHERE id=?', [$rId]);
            if ($report && $report['type']==='post') {
                DB::update('posts', ['status'=>'removed'], ['id'=>(int)$report['target_id']]);
            } elseif ($report && $report['type']==='status') {
                DB::update('status_posts', ['status'=>'removed'], ['id'=>(int)$report['target_id']]);
            }
        }
    }
    header('Location: reports.php?status='.$filter);
    exit;
}

$total   = DB::first("SELECT COUNT(*) AS n FROM reports WHERE status=?",[$filter])['n']??0;
$reports = DB::query(
    "SELECT r.*, u.username AS reporter_name FROM reports r
     JOIN users u ON u.id=r.reporter_id
     WHERE r.status=?
     ORDER BY r.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
    [$filter]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Reports — Voxu Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
</head>
<body>
<?php $activeMenu = 'reports'; require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <div class="admin-page-title">Content Reports</div>
    <div class="topbar-actions">
      <?php foreach (['open','reviewed','resolved','dismissed'] as $st): ?>
        <a href="?status=<?=$st?>" class="btn btn-sm <?=$filter===$st?'btn-primary':'btn-secondary'?>"><?=ucfirst($st)?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="admin-content">
    <?php if ($filter==='open' && $total>0): ?>
      <div class="alert alert-warning">⚠ <?=$total?> open report<?=$total!=1?'s':''?> need review.</div>
    <?php endif; ?>
    <div class="table-card">
      <div class="table-header"><span class="table-title"><?=ucfirst($filter)?> Reports (<?=$total?>)</span></div>
      <table>
        <thead><tr><th>Reporter</th><th>Type</th><th>Reason</th><th>Date</th><?=$filter==='open'?'<th>Actions</th>':''?></tr></thead>
        <tbody>
          <?php foreach ($reports as $r): ?>
          <tr>
            <td style="font-weight:500"><?=clean($r['reporter_name'])?></td>
            <td><span class="badge badge-muted"><?=ucfirst($r['type'])?> #<?=$r['target_id']?></span></td>
            <td style="font-size:13px"><?=clean($r['reason'])?><?=$r['description']?' — <span style="color:var(--text3)">'.clean(substr($r['description'],0,60)).'</span>':''?></td>
            <td style="font-size:12px"><?=date('d M Y H:i',strtotime($r['created_at']))?></td>
            <?php if ($filter==='open'): ?>
            <td>
              <div style="display:flex;gap:6px">
                <form method="POST" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
                  <input type="hidden" name="report_id" value="<?=$r['id']?>"/>
                  <input type="hidden" name="action" value="resolve"/>
                  <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Resolve and remove content?')">Resolve</button>
                </form>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
                  <input type="hidden" name="report_id" value="<?=$r['id']?>"/>
                  <input type="hidden" name="action" value="dismiss"/>
                  <button type="submit" class="btn btn-sm btn-secondary">Dismiss</button>
                </form>
              </div>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($reports)): ?><tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text3)">No <?=$filter?> reports</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
