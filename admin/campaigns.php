<?php
/**
 * Voxu Admin — Campaigns Management
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('admin');

$admin      = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$activeMenu = 'campaigns';
$settings   = getPlatformSettings();
$symbol     = $settings['currency_symbol'] ?? '$';
$success    = '';
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title       = sanitize($_POST['title']       ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $type        = sanitize($_POST['type']         ?? 'general');
        $totalPoints = (int)($_POST['total_points']    ?? 0);
        $rewardPer   = (int)($_POST['reward_per']      ?? 10);
        $maxPerUser  = (int)($_POST['max_per_user']    ?? 100);
        $startsAt    = sanitize($_POST['starts_at']   ?? '');
        $endsAt      = sanitize($_POST['ends_at']     ?? '');

        if (!$title || $totalPoints < 1) {
            $error = 'Title and total points are required.';
        } else {
            DB::beginTransaction();
            try {
                $campId = DB::insert('campaigns', [
                    'creator_id'  => (int)$admin['id'],
                    'title'       => $title,
                    'description' => $description,
                    'type'        => $type,
                    'status'      => 'active',
                    'starts_at'   => $startsAt ?: date('Y-m-d H:i:s'),
                    'ends_at'     => $endsAt   ?: null,
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                DB::insert('campaign_reward_pools', [
                    'campaign_id'      => $campId,
                    'total_points'     => $totalPoints,
                    'remaining'        => $totalPoints,
                    'reward_per_action'=> $rewardPer,
                    'max_per_user'     => $maxPerUser,
                ]);
                DB::commit();
                logAdminAction((int)$admin['id'], 'campaign_create', "Created campaign: {$title}");
                $success = 'Campaign created successfully.';
            } catch (Throwable $e) {
                DB::rollback();
                $error = 'Failed to create campaign. Please try again.';
            }
        }
    } elseif ($action === 'toggle' && (int)($_POST['campaign_id']??0)) {
        $campId  = (int)$_POST['campaign_id'];
        $camp    = DB::first('SELECT status FROM campaigns WHERE id=?', [$campId]);
        $newStat = ($camp['status'] === 'active') ? 'paused' : 'active';
        DB::update('campaigns', ['status' => $newStat], ['id' => $campId]);
        logAdminAction((int)$admin['id'], 'campaign_toggle', "Campaign #{$campId} set to {$newStat}");
        $success = "Campaign set to {$newStat}.";
    } elseif ($action === 'cancel' && (int)($_POST['campaign_id']??0)) {
        $campId = (int)$_POST['campaign_id'];
        DB::update('campaigns', ['status' => 'cancelled'], ['id' => $campId]);
        logAdminAction((int)$admin['id'], 'campaign_cancel', "Cancelled campaign #{$campId}");
        $success = 'Campaign cancelled.';
    }
}

$filter   = sanitize($_GET['status'] ?? '');
$where    = $filter ? 'c.status=?' : '1';
$params   = $filter ? [$filter]    : [];

$campaigns = DB::query(
    "SELECT c.*, crp.total_points, crp.remaining, crp.reward_per_action,
            (SELECT COUNT(*) FROM campaign_responses WHERE campaign_id=c.id) AS response_count
     FROM campaigns c
     LEFT JOIN campaign_reward_pools crp ON crp.campaign_id=c.id
     WHERE {$where}
     ORDER BY c.created_at DESC",
    $params
);
$statusColors = ['active'=>'badge-green','paused'=>'badge-warning','completed'=>'badge-blue','cancelled'=>'badge-danger','draft'=>'badge-muted'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Campaigns — Voxu Admin</title>
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
    <div class="admin-page-title">Campaigns</div>
    <div class="topbar-actions">
      <?php foreach(['','active','paused','completed','cancelled'] as $st): ?>
        <a href="?status=<?= $st ?>" class="btn btn-sm <?= $filter===$st?'btn-primary':'btn-secondary' ?>"><?= $st ? ucfirst($st) : 'All' ?></a>
      <?php endforeach; ?>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('createModal').classList.add('open')">+ New Campaign</button>
    </div>
  </div>
  <div class="admin-content">
    <?php if ($success): ?><div class="alert alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">⚠ <?= clean($error)   ?></div><?php endif; ?>

    <div class="table-card">
      <div class="table-header"><span class="table-title">Campaigns (<?= count($campaigns) ?>)</span></div>
      <table>
        <thead><tr><th>Title</th><th>Type</th><th>Points Pool</th><th>Remaining</th><th>Responses</th><th>Status</th><th>Ends</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($campaigns as $c):
            $pct = $c['total_points'] > 0 ? round(($c['total_points']-$c['remaining'])/$c['total_points']*100) : 0;
          ?>
          <tr>
            <td style="font-weight:500;color:var(--text)"><?= clean($c['title']) ?><br/><span style="font-size:11px;color:var(--text3)"><?= clean(substr($c['description']??'',0,50)) ?></span></td>
            <td><span class="badge badge-muted"><?= clean($c['type']) ?></span></td>
            <td>
              <?= number_format((int)$c['total_points']) ?> pts
              <div style="height:4px;background:var(--bg3);border-radius:2px;margin-top:4px;width:80px">
                <div style="height:100%;background:var(--purple);border-radius:2px;width:<?= $pct ?>%"></div>
              </div>
              <div style="font-size:10px;color:var(--text3)"><?= $pct ?>% used</div>
            </td>
            <td style="color:var(--green)"><?= number_format((int)$c['remaining']) ?> pts</td>
            <td><?= number_format((int)$c['response_count']) ?></td>
            <td><span class="badge <?= $statusColors[$c['status']] ?? 'badge-muted' ?>"><?= ucfirst($c['status']) ?></span></td>
            <td style="font-size:12px"><?= $c['ends_at'] ? date('d M Y', strtotime($c['ends_at'])) : '—' ?></td>
            <td>
              <div style="display:flex;gap:5px">
                <?php if (in_array($c['status'],['active','paused'])): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="_csrf"        value="<?= csrfToken() ?>"/>
                  <input type="hidden" name="action"       value="toggle"/>
                  <input type="hidden" name="campaign_id"  value="<?= $c['id'] ?>"/>
                  <button type="submit" class="btn btn-sm btn-secondary"><?= $c['status']==='active'?'Pause':'Resume' ?></button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Cancel campaign permanently?')">
                  <input type="hidden" name="_csrf"        value="<?= csrfToken() ?>"/>
                  <input type="hidden" name="action"       value="cancel"/>
                  <input type="hidden" name="campaign_id"  value="<?= $c['id'] ?>"/>
                  <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                </form>
                <?php else: ?>
                <span style="font-size:11px;color:var(--text3)"><?= ucfirst($c['status']) ?></span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($campaigns)): ?><tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text3)">No campaigns found</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- CREATE CAMPAIGN MODAL -->
<div class="modal-backdrop" id="createModal">
  <div class="admin-modal" style="max-width:560px">
    <div class="admin-modal-header">
      <div class="admin-modal-title">New Campaign</div>
      <button onclick="document.getElementById('createModal').classList.remove('open')" style="color:var(--text2)">✕</button>
    </div>
    <form method="POST">
      <div class="admin-modal-body">
        <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action" value="create"/>
        <div class="form-group"><label class="form-label">Campaign Title *</label><input class="form-input" type="text" name="title" required placeholder="e.g. Summer Engagement Drive"/></div>
        <div class="form-group"><label class="form-label">Description</label><textarea class="form-input" name="description" rows="2" placeholder="What is this campaign about?"></textarea></div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Type</label>
            <select class="form-input" name="type">
              <option value="general">General</option>
              <option value="voice">Voice Challenge</option>
              <option value="status">Status Drive</option>
              <option value="engagement">Engagement</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Total Points Pool *</label><input class="form-input" type="number" name="total_points" min="100" placeholder="e.g. 10000" required/></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Reward per Action (pts)</label><input class="form-input" type="number" name="reward_per" value="10" min="1"/></div>
          <div class="form-group"><label class="form-label">Max per User (pts)</label><input class="form-input" type="number" name="max_per_user" value="100" min="1"/></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Starts At</label><input class="form-input" type="datetime-local" name="starts_at"/></div>
          <div class="form-group"><label class="form-label">Ends At</label><input class="form-input" type="datetime-local" name="ends_at"/></div>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Campaign</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-backdrop')) e.target.classList.remove('open');
});
</script>
</body>
</html>
