<?php
// @author  Jcode | ObrempongK
// admin/finance.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('admin');

$admin   = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$page    = max(1,(int)($_GET['page']??1));
$search  = sanitize($_GET['q']    ?? '');
$type    = sanitize($_GET['type'] ?? '');
$perPage = 30;
$offset  = ($page-1)*$perPage;

$where   = '1';
$params  = [];
if ($search) {
    $where   .= ' AND (u.username LIKE ? OR t.reference LIKE ?)';
    $params   = array_merge($params, ["%{$search}%", "%{$search}%"]);
}
if ($type) {
    $where   .= ' AND t.type=?';
    $params[] = $type;
}

$total = DB::first("SELECT COUNT(*) AS n FROM transactions t JOIN users u ON u.id=t.user_id WHERE {$where}", $params)['n'] ?? 0;
$txns  = DB::query(
    "SELECT t.*, u.username FROM transactions t
     JOIN users u ON u.id=t.user_id
     WHERE {$where}
     ORDER BY t.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$settings   = getPlatformSettings();
$symbol     = $settings['currency_symbol'] ?? '$';
$totalPages = ceil($total / $perPage);

// Summary stats
$todayVolume= DB::first("SELECT COALESCE(SUM(amount),0) AS s FROM transactions WHERE DATE(created_at)=CURDATE() AND status='completed'")['s'] ?? 0;
$totalDep   = DB::first("SELECT COALESCE(SUM(amount),0) AS s FROM transactions WHERE type='deposit' AND status='completed'")['s'] ?? 0;
$totalWith  = DB::first("SELECT COALESCE(SUM(amount),0) AS s FROM withdrawals WHERE status='completed'")['s'] ?? 0;

$txTypes = DB::query('SELECT DISTINCT type FROM transactions ORDER BY type');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Finance — Voxu Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
</head>
<body>
<?php $activeMenu = 'finance'; require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <div class="admin-page-title">Financial Transactions</div>
    <div class="topbar-actions">
      <form method="GET" style="display:flex;gap:8px">
        <input type="text" class="search-input" name="q" value="<?= clean($search) ?>" placeholder="Search username or ref…"/>
        <select class="search-input" name="type" style="width:auto">
          <option value="">All Types</option>
          <?php foreach ($txTypes as $t): ?>
            <option value="<?= clean($t['type']) ?>" <?= $type===$t['type']?'selected':'' ?>><?= clean($t['type']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($search||$type): ?><a href="/admin/finance.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="admin-content">
    <!-- SUMMARY CARDS -->
    <div class="admin-stats" style="margin-bottom:24px">
      <div class="admin-stat-card" style="--indicator:var(--green)">
        <div class="admin-stat-label">Total Deposits</div>
        <div class="admin-stat-val"><?= $symbol ?><?= number_format((float)$totalDep,2) ?></div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--danger)">
        <div class="admin-stat-label">Total Withdrawn</div>
        <div class="admin-stat-val"><?= $symbol ?><?= number_format((float)$totalWith,2) ?></div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--blue)">
        <div class="admin-stat-label">Today's Volume</div>
        <div class="admin-stat-val"><?= $symbol ?><?= number_format((float)$todayVolume,2) ?></div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--purple)">
        <div class="admin-stat-label">Total Transactions</div>
        <div class="admin-stat-val"><?= number_format($total) ?></div>
      </div>
    </div>

    <div class="table-card">
      <div class="table-header">
        <span class="table-title">Transactions (<?= number_format($total) ?>)</span>
        <a href="/admin/finance.php?export=csv&q=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>" class="btn btn-secondary btn-sm">Export CSV</a>
      </div>
      <table>
        <thead>
          <tr><th>ID</th><th>User</th><th>Type</th><th>Amount</th><th>Reference</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php foreach ($txns as $t):
            $isC = in_array($t['type'], ['deposit','transfer_in','reward','refund','admin_credit','points_conversion']);
          ?>
          <tr>
            <td style="color:var(--text3);font-size:11px">#<?= $t['id'] ?></td>
            <td style="font-weight:500"><?= clean($t['username']) ?></td>
            <td><span class="badge badge-muted" style="font-size:10px"><?= clean(str_replace('_',' ',$t['type'])) ?></span></td>
            <td style="font-weight:700;color:<?= $isC?'var(--green)':'var(--danger)' ?>"><?= $isC?'+':'-' ?><?= $symbol ?><?= number_format((float)$t['amount'],2) ?></td>
            <td style="font-size:11px;color:var(--text3);font-family:monospace"><?= clean($t['reference']??'—') ?></td>
            <td>
              <span class="badge <?= $t['status']==='completed'?'badge-green':($t['status']==='failed'?'badge-danger':'badge-warning') ?>">
                <?= ucfirst($t['status']) ?>
              </span>
            </td>
            <td style="font-size:12px"><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($txns)): ?>
            <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text3)">No transactions found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <div class="table-footer">
        <span>Showing <?= min($offset+1,$total) ?>–<?= min($offset+$perPage,$total) ?> of <?= number_format($total) ?></span>
        <div class="pagination">
          <?php if ($page>1): ?><a href="?page=<?=$page-1?>&q=<?=urlencode($search)?>&type=<?=urlencode($type)?>" class="page-btn">←</a><?php endif; ?>
          <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
            <a href="?page=<?=$p?>&q=<?=urlencode($search)?>&type=<?=urlencode($type)?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
          <?php endfor; ?>
          <?php if ($page<$totalPages): ?><a href="?page=<?=$page+1?>&q=<?=urlencode($search)?>&type=<?=urlencode($type)?>" class="page-btn">→</a><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
