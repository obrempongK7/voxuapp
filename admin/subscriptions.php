<?php
/**
 * Voxu Admin — Subscription Plans Manager
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('admin');

$admin      = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$activeMenu = 'subscriptions';
$settings   = getPlatformSettings();
$symbol     = $settings['currency_symbol'] ?? '$';
$success    = '';
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_plan') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan   = DB::first('SELECT * FROM subscription_plans WHERE id=?', [$planId]);
        if (!$plan) { $error = 'Plan not found.'; }
        else {
            DB::update('subscription_plans', [
                'price_monthly'      => (float)($_POST['price_monthly']      ?? 0),
                'price_yearly'       => (float)($_POST['price_yearly']        ?? 0),
                'max_recording_secs' => (int)  ($_POST['max_recording_secs'] ?? 180),
                'max_daily_earnings' => (int)  ($_POST['max_daily_earnings'] ?? 1000),
                'min_withdrawal_pts' => (int)  ($_POST['min_withdrawal_pts'] ?? 500),
                'cashout_multiplier' => (float)($_POST['cashout_multiplier'] ?? 1.00),
                'max_status_per_day' => (int)  ($_POST['max_status_per_day'] ?? 10),
                'can_voice_bg'       => isset($_POST['can_voice_bg'])  ? 1 : 0,
                'can_analytics'      => isset($_POST['can_analytics']) ? 1 : 0,
                'can_custom_link'    => isset($_POST['can_custom_link'])? 1 : 0,
                'verified_badge'     => isset($_POST['verified_badge']) ? 1 : 0,
                'priority_support'   => isset($_POST['priority_support'])? 1 : 0,
                'is_active'          => isset($_POST['is_active'])     ? 1 : 0,
            ], ['id' => $planId]);
            logAdminAction((int)$admin['id'], 'plan_update', "Updated subscription plan #{$planId}");
            $success = "Plan '{$plan['name']}' updated.";
        }
    } elseif ($action === 'assign_plan') {
        $username = sanitize($_POST['username'] ?? '');
        $planId   = (int)($_POST['assign_plan_id'] ?? 1);
        $billing  = sanitize($_POST['billing']    ?? 'monthly');
        $months   = (int)($_POST['months']        ?? 1);
        $target   = DB::first('SELECT id, username FROM users WHERE username=? AND status="active"', [strtolower($username)]);
        if (!$target) {
            $error = "User @{$username} not found.";
        } else {
            $expires = $months > 0 ? date('Y-m-d H:i:s', strtotime("+{$months} months")) : null;
            assignPlan((int)$target['id'], $planId, $billing, $expires);
            createNotification((int)$target['id'], 'subscription', "Your subscription has been updated by admin.");
            logAdminAction((int)$admin['id'], 'plan_assign', "Assigned plan #{$planId} to @{$username}");
            $success = "Plan assigned to @{$target['username']}.";
        }
    } elseif ($action === 'grant_custom_url') {
        $username   = strtolower(sanitize($_POST['username'] ?? ''));
        $slug       = preg_replace('/[^a-zA-Z0-9_]/', '', sanitize($_POST['custom_slug'] ?? ''));
        $months     = (int)($_POST['url_months'] ?? 1);
        if (!$username || !$slug || strlen($slug) < 3) {
            $error = 'Valid username and URL slug (min 3 chars) required.';
        } else {
            $target = DB::first('SELECT id FROM users WHERE username=? AND status="active"', [$username]);
            if (!$target) {
                $error = "User @{$username} not found.";
            } else {
                // Check slug not taken
                $exists = DB::first('SELECT user_id FROM user_profiles WHERE custom_url_slug=? AND user_id!=?', [$slug, (int)$target['id']]);
                if ($exists) {
                    $error = "The slug '{$slug}' is already in use by another user.";
                } else {
                    // Ensure user_profiles has custom_url_slug column
                    try {
                        DB::exec("ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS custom_url_slug VARCHAR(30) DEFAULT NULL");
                    } catch (Throwable) {}
                    DB::exec("INSERT INTO user_profiles (user_id, custom_url_slug) VALUES (?,?) ON DUPLICATE KEY UPDATE custom_url_slug=?", [(int)$target['id'], $slug, $slug]);
                    // Set can_custom_link perk in user_subscriptions
                    $expiresAt = $months > 0 ? date('Y-m-d H:i:s', strtotime("+{$months} months")) : null;
                    assignPlan((int)$target['id'], getUserPlan((int)$target['id'])['id'] ?? 1, 'free', $expiresAt);
                    createNotification((int)$target['id'], 'custom_url', "Your custom URL /@{$slug} has been activated!");
                    logAdminAction((int)$admin['id'], 'custom_url_grant', "Granted custom URL /@{$slug} to @{$username}");
                    $success = "Custom URL /@{$slug} granted to @{$username}.";
                }
            }
        }
    } elseif ($action === 'cancel_plan') {
        $userId = (int)($_POST['cancel_user_id'] ?? 0);
        DB::update('user_subscriptions', ['status' => 'cancelled'], ['user_id' => $userId]);
        createNotification($userId, 'subscription', 'Your subscription has been cancelled.');
        logAdminAction((int)$admin['id'], 'plan_cancel', "Cancelled plan for user #{$userId}");
        $success = 'Subscription cancelled.';
    }
}

$plans     = DB::query('SELECT * FROM subscription_plans ORDER BY price_monthly ASC');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 25;
$offset    = ($page - 1) * $perPage;
$subTotal  = DB::count('user_subscriptions', 'plan_id > 1 AND status="active" AND (expires_at IS NULL OR expires_at > NOW())');
$subs      = DB::query(
    "SELECT us.*, u.username, u.email, sp.name AS plan_name, sp.color, sp.icon
     FROM user_subscriptions us
     JOIN users u ON u.id = us.user_id
     JOIN subscription_plans sp ON sp.id = us.plan_id
     WHERE us.plan_id > 1
     ORDER BY us.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$planOptions = DB::query('SELECT id, name, slug FROM subscription_plans ORDER BY price_monthly');

function recLabel(int $s): string {
    if ($s === 0)   return 'Unlimited';
    if ($s < 60)    return $s . 's';
    return floor($s/60) . ' min';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Subscriptions — Voxu Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
  <style>
    .plan-card{background:var(--card);border:2px solid var(--border);border-radius:16px;padding:22px;transition:.2s;position:relative}
    .plan-card:hover{transform:translateY(-2px)}
    .plan-color-bar{position:absolute;top:0;left:0;right:0;height:4px;border-radius:14px 14px 0 0}
    .plan-name{font-size:20px;font-weight:800;color:#fff;margin-bottom:4px}
    .plan-price{font-size:28px;font-weight:800;color:#fff;margin:10px 0 4px}
    .plan-price span{font-size:14px;font-weight:400;color:var(--text2)}
    .feature-row{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px}
    .feature-row:last-child{border-bottom:none}
    .feature-val{color:#fff;font-weight:600}
    .plans-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:28px}
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="admin-page-title">Subscription Plans</div>
    <div class="topbar-actions">
      <span class="badge badge-green"><?= number_format($subTotal) ?> active paid subscribers</span>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('assignModal').classList.add('open')">+ Assign Plan</button>
    </div>
  </div>
  <div class="admin-content">
    <?php if ($success): ?><div class="alert alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">⚠ <?= clean($error)   ?></div><?php endif; ?>

    <!-- PLAN CARDS -->
    <div class="plans-grid">
      <?php foreach ($plans as $p): ?>
      <div class="plan-card" style="border-color:<?= clean($p['color']) ?>44">
        <div class="plan-color-bar" style="background:<?= clean($p['color']) ?>"></div>
        <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
          <span style="font-size:24px"><?= $p['icon'] ?></span>
          <div class="plan-name"><?= clean($p['name']) ?></div>
          <?php if (!$p['is_active']): ?><span class="badge badge-muted">Off</span><?php endif; ?>
        </div>
        <div class="plan-price"><?= $symbol ?><?= number_format((float)$p['price_monthly'],2) ?><span>/mo</span></div>
        <div style="font-size:12px;color:var(--text3);margin-bottom:14px"><?= $symbol ?><?= number_format((float)$p['price_yearly'],2) ?>/year</div>

        <div class="feature-row"><span style="color:var(--text2)">Max Recording</span><span class="feature-val"><?= recLabel((int)$p['max_recording_secs']) ?></span></div>
        <div class="feature-row"><span style="color:var(--text2)">Daily Earnings</span><span class="feature-val"><?= number_format((int)$p['max_daily_earnings']) ?> pts</span></div>
        <div class="feature-row"><span style="color:var(--text2)">Min Withdrawal</span><span class="feature-val"><?= number_format((int)$p['min_withdrawal_pts']) ?> pts</span></div>
        <div class="feature-row"><span style="color:var(--text2)">Earnings Boost</span><span class="feature-val" style="color:var(--green)"><?= $p['cashout_multiplier'] ?>×</span></div>
        <div class="feature-row"><span style="color:var(--text2)">Status/day</span><span class="feature-val"><?= (int)$p['max_status_per_day'] === 100 ? 'Unlimited' : $p['max_status_per_day'] ?></span></div>

        <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:4px">
          <?php
          $feats = ['can_analytics'=>'📊 Analytics','can_custom_link'=>'🔗 Custom URL','can_voice_bg'=>'🎵 Voice BG','verified_badge'=>'✓ Badge','priority_support'=>'⭐ Support'];
          foreach ($feats as $fk => $fl):
            if ($p[$fk]):
          ?>
            <span style="background:<?= clean($p['color']) ?>22;color:<?= clean($p['color']) ?>;border:1px solid <?= clean($p['color']) ?>55;border-radius:12px;padding:2px 8px;font-size:10px;font-weight:600"><?= $fl ?></span>
          <?php endif; endforeach; ?>
        </div>

        <button class="btn btn-secondary btn-sm w-full" style="margin-top:14px" onclick="editPlan(<?= htmlspecialchars(json_encode($p)) ?>)">
          ✏ Edit Plan
        </button>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ACTIVE SUBSCRIPTIONS -->
    <div class="table-card">
      <div class="table-header">
        <span class="table-title">Active Paid Subscriptions</span>
      </div>
      <table>
        <thead><tr><th>User</th><th>Plan</th><th>Billing</th><th>Status</th><th>Expires</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($subs as $sub): ?>
          <tr>
            <td>
              <a href="/admin/user-view.php?id=<?= $sub['user_id'] ?>" style="color:var(--blue);font-size:13px">@<?= clean($sub['username']) ?></a>
              <div style="font-size:11px;color:var(--text3)"><?= clean($sub['email']) ?></div>
            </td>
            <td>
              <span style="display:inline-flex;align-items:center;gap:4px;background:<?= clean($sub['color']) ?>22;color:<?= clean($sub['color']) ?>;border:1px solid <?= clean($sub['color']) ?>55;border-radius:12px;padding:3px 10px;font-size:12px;font-weight:600">
                <?= $sub['icon'] ?> <?= clean($sub['plan_name']) ?>
              </span>
            </td>
            <td><span class="badge badge-muted"><?= ucfirst($sub['billing']) ?></span></td>
            <td><span class="badge <?= $sub['status']==='active'?'badge-green':'badge-danger' ?>"><?= ucfirst($sub['status']) ?></span></td>
            <td style="font-size:12px"><?= $sub['expires_at'] ? date('d M Y', strtotime($sub['expires_at'])) : 'Never' ?></td>
            <td>
              <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this subscription?')">
                <input type="hidden" name="_csrf"           value="<?= csrfToken() ?>"/>
                <input type="hidden" name="action"          value="cancel_plan"/>
                <input type="hidden" name="cancel_user_id"  value="<?= $sub['user_id'] ?>"/>
                <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($subs)): ?><tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text3)">No active paid subscriptions yet</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- EDIT PLAN MODAL -->
<div class="modal-backdrop" id="editPlanModal">
  <div class="admin-modal" style="max-width:600px">
    <div class="admin-modal-header">
      <div class="admin-modal-title" id="editPlanTitle">Edit Plan</div>
      <button onclick="document.getElementById('editPlanModal').classList.remove('open')" style="color:var(--text2)">✕</button>
    </div>
    <form method="POST">
      <div class="admin-modal-body" style="display:grid;gap:12px">
        <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action"  value="update_plan"/>
        <input type="hidden" name="plan_id" id="ep_id"/>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Monthly Price (<?= $symbol ?>)</label><input class="form-input" type="number" name="price_monthly"      id="ep_pm"  step="0.01" min="0"/></div>
          <div class="form-group"><label class="form-label">Yearly Price (<?= $symbol ?>)</label>  <input class="form-input" type="number" name="price_yearly"       id="ep_py"  step="0.01" min="0"/></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Max Recording (seconds, 0=unlimited)</label>
            <select class="form-input" name="max_recording_secs" id="ep_rec">
              <option value="180">3 min (180s) — Free</option>
              <option value="300">5 min (300s) — Silver</option>
              <option value="600">10 min (600s) — Gold</option>
              <option value="0">Unlimited — Platinum</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Max Daily Earnings (pts)</label><input class="form-input" type="number" name="max_daily_earnings" id="ep_mde" min="1"/></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Min Withdrawal (pts)</label>   <input class="form-input" type="number" name="min_withdrawal_pts" id="ep_mw"  min="1"/></div>
          <div class="form-group"><label class="form-label">Cashout Multiplier (e.g. 1.5)</label><input class="form-input" type="number" name="cashout_multiplier"  id="ep_cm"  step="0.1" min="1"/></div>
        </div>
        <div class="form-group"><label class="form-label">Max Status per Day</label><input class="form-input" type="number" name="max_status_per_day" id="ep_mspd" min="1"/></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <?php
          $toggles = [
            ['can_analytics',   'ep_ca',  '📊 Detailed Analytics'],
            ['can_custom_link',  'ep_ccl', '🔗 Custom Profile URL'],
            ['can_voice_bg',     'ep_cvb', '🎵 Voice Background Music'],
            ['verified_badge',   'ep_vb',  '✓ Verified Badge'],
            ['priority_support', 'ep_ps',  '⭐ Priority Support'],
            ['is_active',        'ep_ia',  '✓ Plan Active'],
          ];
          foreach ($toggles as $t):
          ?>
          <label class="toggle">
            <input type="checkbox" name="<?= $t[0] ?>" id="<?= $t[1] ?>" value="1"/>
            <span class="toggle-track"></span>
            <span class="toggle-label"><?= $t[2] ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('editPlanModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Plan</button>
      </div>
    </form>
  </div>
</div>

<!-- CUSTOM URL GRANT (standalone, no full plan upgrade needed) -->
<div class="table-card" style="margin-top:20px">
  <div class="table-header">
    <span class="table-title">🔗 Grant Custom Profile URL</span>
    <span style="font-size:12px;color:var(--text3)">Give a specific user a custom URL without upgrading their full plan</span>
  </div>
  <form method="POST" style="padding:20px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
    <input type="hidden" name="action"  value="grant_custom_url"/>
    <div class="form-group" style="flex:1;min-width:200px">
      <label class="form-label">Username</label>
      <input class="form-input" type="text" name="username" placeholder="@username (without @)" required/>
    </div>
    <div class="form-group" style="flex:1;min-width:200px">
      <label class="form-label">Custom URL slug</label>
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:13px;color:var(--text2);white-space:nowrap"><?= APP_URL ?>/@</span>
        <input class="form-input" type="text" name="custom_slug" placeholder="e.g. john" pattern="[a-zA-Z0-9_]{3,30}" title="3-30 chars: letters, numbers, underscore"/>
      </div>
    </div>
    <div class="form-group" style="flex:1;min-width:200px">
      <label class="form-label">Duration</label>
      <select class="form-input" name="url_months">
        <option value="1">1 month</option>
        <option value="3">3 months</option>
        <option value="6">6 months</option>
        <option value="12">1 year</option>
        <option value="0">Permanent</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm" style="flex-shrink:0">Grant Custom URL</button>
  </form>

  <?php
  // List users with custom URLs
  $customUrlUsers = DB::query(
      "SELECT u.username, up.custom_url_slug, us.expires_at
       FROM users u
       LEFT JOIN user_profiles up ON up.user_id = u.id
       LEFT JOIN user_subscriptions us ON us.user_id = u.id
       WHERE up.custom_url_slug IS NOT NULL AND up.custom_url_slug != ''
       ORDER BY u.username ASC"
  );
  if (!empty($customUrlUsers)): ?>
  <div style="padding:0 20px 16px">
    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin-bottom:10px">Active Custom URLs</div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach ($customUrlUsers as $cu): ?>
        <div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:6px 12px;font-size:13px">
          <span style="color:var(--text)">@<?= clean($cu['username']) ?></span>
          <span style="color:var(--text3);margin:0 6px">→</span>
          <span style="color:var(--purple)"><?= APP_URL ?>/@<?= clean($cu['custom_url_slug']) ?></span>
          <?php if ($cu['expires_at']): ?>
            <span style="font-size:11px;color:var(--text3);margin-left:6px">Expires <?= date('d M Y', strtotime($cu['expires_at'])) ?></span>
          <?php else: ?>
            <span style="font-size:11px;color:var(--green);margin-left:6px">Permanent</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ASSIGN PLAN MODAL -->
<div class="modal-backdrop" id="assignModal">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <div class="admin-modal-title">Assign Plan to User</div>
      <button onclick="document.getElementById('assignModal').classList.remove('open')" style="color:var(--text2)">✕</button>
    </div>
    <form method="POST">
      <div class="admin-modal-body">
        <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action" value="assign_plan"/>
        <div class="form-group"><label class="form-label">Username</label><input class="form-input" name="username" placeholder="@username (without @)" required/></div>
        <div class="form-group">
          <label class="form-label">Plan</label>
          <select class="form-input" name="assign_plan_id">
            <?php foreach ($planOptions as $po): ?>
              <option value="<?= $po['id'] ?>"><?= clean($po['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Billing Type</label>
            <select class="form-input" name="billing">
              <option value="monthly">Monthly</option>
              <option value="yearly">Yearly</option>
              <option value="free">Free (Admin Grant)</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Duration (months, 0=never expires)</label><input class="form-input" type="number" name="months" value="1" min="0"/></div>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('assignModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Assign Plan</button>
      </div>
    </form>
  </div>
</div>

<script>
function editPlan(p) {
  document.getElementById('editPlanTitle').textContent = 'Edit Plan: ' + p.name;
  document.getElementById('ep_id').value   = p.id;
  document.getElementById('ep_pm').value   = p.price_monthly;
  document.getElementById('ep_py').value   = p.price_yearly;
  document.getElementById('ep_rec').value  = p.max_recording_secs;
  document.getElementById('ep_mde').value  = p.max_daily_earnings;
  document.getElementById('ep_mw').value   = p.min_withdrawal_pts;
  document.getElementById('ep_cm').value   = p.cashout_multiplier;
  document.getElementById('ep_mspd').value = p.max_status_per_day;
  ['ca','ccl','cvb','vb','ps','ia'].forEach((k, i) => {
    const keys = ['can_analytics','can_custom_link','can_voice_bg','verified_badge','priority_support','is_active'];
    document.getElementById('ep_' + k).checked = !!p[keys[i]];
  });
  document.getElementById('editPlanModal').classList.add('open');
}
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-backdrop')) e.target.classList.remove('open');
});
</script>
</body>
</html>
