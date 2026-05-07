<?php
/**
 * Voxu Admin — Voice Posts Management
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('moderator');

$admin      = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$activeMenu = 'posts';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $postId = (int)($_POST['post_id'] ?? 0);
    if ($action === 'remove' && $postId) {
        DB::update('posts', ['status' => 'removed'], ['id' => $postId]);
        logAdminAction((int)$admin['id'], 'post_remove', "Removed post #{$postId}");
    } elseif ($action === 'restore' && $postId) {
        DB::update('posts', ['status' => 'active'], ['id' => $postId]);
        logAdminAction((int)$admin['id'], 'post_restore', "Restored post #{$postId}");
    }
    header('Location: posts.php?' . http_build_query(array_filter(['q'=>$_GET['q']??'','status'=>$_GET['status']??''])));
    exit;
}

$search  = sanitize($_GET['q']      ?? '');
$filter  = sanitize($_GET['status'] ?? 'active');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where  = 'p.status = ?';
$params = [$filter];
if ($search) {
    $where  .= ' AND (p.title LIKE ? OR u.username LIKE ?)';
    $params  = array_merge($params, ["%{$search}%", "%{$search}%"]);
}

$total = DB::first(
    "SELECT COUNT(*) AS n FROM posts p JOIN users u ON u.id=p.user_id WHERE {$where}",
    $params
)['n'] ?? 0;

$posts = DB::query(
    "SELECT p.*, u.username,
            (SELECT COUNT(*) FROM replies WHERE post_id=p.id) AS reply_count,
            (SELECT COALESCE(SUM(amount),0) FROM energy_transactions WHERE post_id=p.id) AS total_energy
     FROM posts p JOIN users u ON u.id=p.user_id
     WHERE {$where}
     ORDER BY p.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);
$totalPages = max(1, ceil($total / $perPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Voice Posts — Voxu Admin</title>
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
    <div class="admin-page-title">Voice Posts</div>
    <div class="topbar-actions">
      <form method="GET" style="display:flex;gap:8px">
        <input type="text" class="search-input" name="q" value="<?= clean($search) ?>" placeholder="Search title or user…"/>
        <select class="search-input" name="status" style="width:auto">
          <option value="active"  <?= $filter==='active' ?'selected':'' ?>>Active</option>
          <option value="removed" <?= $filter==='removed'?'selected':'' ?>>Removed</option>
          <option value="draft"   <?= $filter==='draft'  ?'selected':'' ?>>Draft</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($search): ?><a href="posts.php?status=<?= $filter ?>" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
      </form>
      <a href="posts.php?export=csv&status=<?= urlencode($filter) ?>&q=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">Export CSV</a>
    </div>
  </div>
  <div class="admin-content">
    <div class="table-card">
      <div class="table-header">
        <span class="table-title">Posts (<?= number_format($total) ?>)</span>
      </div>
      <table>
        <thead><tr><th>Title</th><th>User</th><th>Replies</th><th>Energy</th><th>Plays</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($posts as $p): ?>
          <tr>
            <td style="max-width:220px">
              <div class="truncate" style="font-weight:500;color:var(--text)"><?= clean($p['title']) ?></div>
              <?php if ($p['audio_url']): ?>
                <audio controls style="margin-top:4px;max-width:180px;height:28px">
                  <source src="<?= clean($p['audio_url']) ?>">
                </audio>
              <?php endif; ?>
            </td>
            <td><a href="/admin/user-view.php?id=<?= $p['user_id'] ?>" style="color:var(--blue);font-size:13px">@<?= clean($p['username']) ?></a></td>
            <td><?= number_format((int)$p['reply_count']) ?></td>
            <td>⚡ <?= number_format((int)$p['total_energy']) ?></td>
            <td><?= number_format((int)$p['play_count']) ?></td>
            <td><span class="badge <?= $p['status']==='active'?'badge-green':($p['status']==='removed'?'badge-danger':'badge-muted') ?>"><?= ucfirst($p['status']) ?></span></td>
            <td style="font-size:12px"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
            <td>
              <div style="display:flex;gap:6px">
                <a href="/post/<?= $p['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">View</a>
                <?php if ($p['status'] === 'active'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="_csrf"    value="<?= csrfToken() ?>"/>
                  <input type="hidden" name="action"   value="remove"/>
                  <input type="hidden" name="post_id"  value="<?= $p['id'] ?>"/>
                  <input type="hidden" name="status"   value="<?= $filter ?>"/>
                  <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remove this post?')">Remove</button>
                </form>
                <?php else: ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="_csrf"    value="<?= csrfToken() ?>"/>
                  <input type="hidden" name="action"   value="restore"/>
                  <input type="hidden" name="post_id"  value="<?= $p['id'] ?>"/>
                  <button type="submit" class="btn btn-success btn-sm">Restore</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($posts)): ?><tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text3)">No posts found</td></tr><?php endif; ?>
        </tbody>
      </table>
      <div class="table-footer">
        <span>Showing <?= min($offset+1,$total) ?>–<?= min($offset+$perPage,$total) ?> of <?= number_format($total) ?></span>
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
