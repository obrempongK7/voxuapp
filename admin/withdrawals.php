<?php
// @author  Jcode | ObrempongK
// admin/withdrawals.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('admin');

$admin  = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$filter = sanitize($_GET['status'] ?? 'pending');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage= 20;
$offset = ($page-1)*$perPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $wId    = (int)($_POST['withdrawal_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note   = sanitize($_POST['note'] ?? '');
    $w      = DB::first('SELECT * FROM withdrawals WHERE id=?', [$wId]);

    if ($w && $w['status'] === 'pending' && in_array($action, ['approve','reject','complete'])) {
        $newStatus = ['approve'=>'approved','reject'=>'rejected','complete'=>'completed'][$action];
        if ($action === 'approve') {
            if (!deductPoints((int)$w['user_id'], (int)$w['amount'], 'Withdrawal approved')) {
                $_SESSION['admin_flash_error'] = 'Unable to approve withdrawal because the user no longer has enough available points.';
                header('Location: withdrawals.php?status=' . $filter);
                exit;
            }
            createNotification((int)$w['user_id'], 'withdrawal_approved', 'Your withdrawal of ' . $w['amount'] . ' pts has been approved.');
        } elseif ($action === 'reject') {
            createNotification((int)$w['user_id'], 'withdrawal_rejected', 'Your withdrawal was rejected. Reason: ' . $note);
        }
        DB::update('withdrawals', [
            'status'       => $newStatus,
            'admin_note'   => $note,
            'processed_by' => (int)$admin['id'],
            'processed_at' => date('Y-m-d H:i:s'),
        ], ['id'=>$wId]);
        logAdminAction((int)$admin['id'], "withdrawal_{$action}", "Withdrawal #{$wId} {$action}");
    }
    header('Location: withdrawals.php?status=' . $filter);
    exit;
}

$total = DB::first("SELECT COUNT(*) AS n FROM withdrawals WHERE status=?", [$filter])['n'] ?? 0;
$withdrawals = DB::query(
    "SELECT w.*, u.username, u.email FROM withdrawals w
     JOIN users u ON u.id=w.user_id
     WHERE w.status=?
     ORDER BY w.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    [$filter]
);
$settings = getPlatformSettings();
$symbol   = $settings['currency_symbol'] ?? '$';
$rate     = (int)($settings['points_to_cash_rate'] ?? 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Withdrawals — Voxu Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
</head>
<body>
<?php $activeMenu = 'withdrawals'; require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <div class="admin-page-title">Withdrawal Requests</div>
    <div class="topbar-actions">
      <?php foreach (['pending','approved','completed','rejected'] as $st): ?>
        <a href="?status=<?=$st?>" class="btn btn-sm <?= $filter===$st?'btn-primary':'btn-secondary' ?>"><?= ucfirst($st) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="admin-content">
    <?php if ($filter==='pending' && $total > 0): ?>
    <div class="alert alert-warning">⚠ <?= $total ?> pending withdrawal<?= $total!=1?'s':'' ?> require your attention.</div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['admin_flash_error'])): ?>
    <div class="alert alert-danger"><?= clean($_SESSION['admin_flash_error']) ?></div>
    <?php unset($_SESSION['admin_flash_error']); endif; ?>

    <div class="table-card">
      <div class="table-header">
        <span class="table-title"><?= ucfirst($filter) ?> Withdrawals (<?= $total ?>)</span>
      </div>
      <table>
        <thead>
          <tr><th>User</th><th>Amount (pts)</th><th>Cash Value</th><th>Method</th><th>Account</th><th>Date</th><?= $filter==='pending'?'<th>Actions</th>':'' ?></tr>
        </thead>
        <tbody>
          <?php foreach ($withdrawals as $w):
            $cashVal = round($w['amount'] / max(1,$rate), 2);
            $acct = is_string($w['account_details']) ? $w['account_details'] : json_decode($w['account_details']??'{}',true);
          ?>
          <tr>
            <td>
              <div style="font-weight:600;color:var(--text)"><?= clean($w['username']) ?></div>
              <div style="font-size:11px;color:var(--text3)"><?= clean($w['email']) ?></div>
            </td>
            <td style="font-weight:700"><?= number_format((int)$w['amount']) ?> pts</td>
            <td style="font-weight:700;color:var(--green)"><?= $symbol ?><?= number_format($cashVal,2) ?></td>
            <td><span class="badge badge-muted"><?= clean($w['method']) ?></span></td>
            <td style="font-size:12px"><?= is_array($acct) ? clean(implode(', ', $acct)) : clean((string)($acct??'')) ?></td>
            <td style="font-size:12px"><?= date('d M Y H:i', strtotime($w['created_at'])) ?></td>
            <?php if ($filter==='pending'): ?>
            <td>
              <div style="display:flex;gap:6px">
                <button class="btn btn-success btn-sm" onclick="processWithdrawal(<?=$w['id']?>,'approve')">Approve</button>
                <button class="btn btn-danger btn-sm" onclick="processWithdrawal(<?=$w['id']?>,'reject')">Reject</button>
              </div>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($withdrawals)): ?>
            <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text3)">No <?= $filter ?> withdrawals</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- PROCESS WITHDRAWAL MODAL -->
<div class="modal-backdrop" id="withdrawModal">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <div class="admin-modal-title" id="withdrawModalTitle">Process Withdrawal</div>
      <button onclick="document.getElementById('withdrawModal').classList.remove('open')" style="color:var(--text2)">✕</button>
    </div>
    <form method="POST" id="withdrawProcessForm">
      <div class="admin-modal-body">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
        <input type="hidden" name="withdrawal_id" id="withdrawId"/>
        <input type="hidden" name="action" id="withdrawAction"/>
        <div class="form-group">
          <label class="form-label">Admin Note (optional)</label>
          <input class="form-input" type="text" name="note" placeholder="Reason or reference…"/>
        </div>
        <div class="alert alert-warning" id="withdrawWarning"></div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('withdrawModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="withdrawSubmitBtn">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
function processWithdrawal(id, action) {
  document.getElementById('withdrawId').value = id;
  document.getElementById('withdrawAction').value = action;
  document.getElementById('withdrawModalTitle').textContent = (action === 'approve' ? 'Approve' : 'Reject') + ' Withdrawal #' + id;
  document.getElementById('withdrawWarning').textContent = action === 'approve'
    ? '✓ Points will be deducted from the user\'s wallet and a notification will be sent.'
    : '⚠ Withdrawal will be rejected and user will be notified.';
  document.getElementById('withdrawSubmitBtn').className = 'btn ' + (action === 'approve' ? 'btn-success' : 'btn-danger');
  document.getElementById('withdrawSubmitBtn').textContent = action === 'approve' ? 'Approve' : 'Reject';
  document.getElementById('withdrawModal').classList.add('open');
}
</script>
</body>
</html>
