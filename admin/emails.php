<?php
/**
 * Voxu Admin — Email Users
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('admin');

$admin      = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$activeMenu = 'emails';
$settings   = getPlatformSettings();
$appName    = $settings['app_name'] ?? 'Voxu';
$supportEmail = $settings['support_email'] ?? '';
$success    = '';
$error      = '';

/**
 * Build a branded HTML email
 */
function buildEmailHTML(string $appName, string $subject, string $body): string {
    return '<!DOCTYPE html>
<html><head><meta charset="UTF-8"/>
<style>
  body{font-family:Inter,Arial,sans-serif;background:#0B0B0F;margin:0;padding:0}
  .wrap{max-width:580px;margin:0 auto;padding:32px 16px}
  .card{background:#16161E;border-radius:12px;padding:32px;border:1px solid #1A1A22}
  .logo{font-size:26px;font-weight:800;color:#fff;margin-bottom:24px}
  .logo span{color:#6C3BFF}
  h2{color:#fff;font-size:20px;margin-top:0}
  p{color:#A0A0B0;font-size:14px;line-height:1.7}
  .btn{display:inline-block;background:#6C3BFF;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;margin:16px 0}
  .footer{text-align:center;font-size:12px;color:#5A5A72;margin-top:20px}
</style></head>
<body>
<div class="wrap">
  <div class="card">
    <div class="logo">Vo<span>xu</span></div>
    <h2>' . htmlspecialchars($subject) . '</h2>
    <div>' . nl2br(htmlspecialchars($body)) . '</div>
    <a href="' . APP_URL . '" class="btn">Visit Voxu →</a>
  </div>
  <div class="footer">© ' . date('Y') . ' ' . htmlspecialchars($appName) . ' · <a href="' . APP_URL . '/privacy.php" style="color:#5A5A72">Privacy</a></div>
</div>
</body></html>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_email') {
        $subject    = sanitize($_POST['subject']     ?? '');
        $body       = sanitize($_POST['body']        ?? '');
        $target     = sanitize($_POST['target']      ?? 'all');
        $singleUser = sanitize($_POST['single_user'] ?? '');

        if (!$subject || !$body) {
            $error = 'Subject and body are required.';
        } elseif (!$supportEmail) {
            $error = 'No support email configured. Set it in Settings → General.';
        } else {
            // Collect recipients
            $recipients = [];
            if ($target === 'single') {
                if (!$singleUser) {
                    $error = 'Please enter a username or email.';
                } else {
                    $u = DB::first('SELECT email, username FROM users WHERE username=? OR email=?', [strtolower($singleUser), $singleUser]);
                    if (!$u) {
                        $error = "User not found: {$singleUser}";
                    } else {
                        $recipients = [$u];
                    }
                }
            } elseif ($target === 'all') {
                $recipients = DB::query('SELECT email, username FROM users WHERE status="active"');
            } elseif ($target === 'active_week') {
                $recipients = DB::query('SELECT email, username FROM users WHERE status="active" AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
            } elseif ($target === 'new') {
                $recipients = DB::query('SELECT email, username FROM users WHERE status="active" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
            }

            if (!$error && !empty($recipients)) {
                $sent  = 0;
                $failed= 0;
                $htmlBody = buildEmailHTML($appName, $subject, $body);
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: {$appName} <{$supportEmail}>\r\n";
                $headers .= "X-Mailer: Voxu-Mailer/1.0\r\n";

                foreach ($recipients as $r) {
                    $personalBody = str_replace('[USERNAME]', $r['username'], $htmlBody);
                    if (@mail($r['email'], $subject, $personalBody, $headers)) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                    // Small delay for bulk sends to avoid SMTP rate limits
                    if ($sent % 50 === 0) usleep(100000);
                }

                logAdminAction((int)$admin['id'], 'email_sent', "Email '{$subject}' sent to {$sent} users (target: {$target})");
                $success = "Email sent to {$sent} user(s)." . ($failed > 0 ? " {$failed} failed." : '');
            } elseif (!$error) {
                $error = 'No recipients found for the selected target group.';
            }
        }
    } elseif ($action === 'save_template') {
        $tplName = sanitize($_POST['template_name'] ?? '');
        $tplSubj = sanitize($_POST['tpl_subject']   ?? '');
        $tplBody = sanitize($_POST['tpl_body']       ?? '');
        if ($tplName && $tplSubj && $tplBody) {
            setSetting('email_template_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($tplName)),
                       json_encode(['subject' => $tplSubj, 'body' => $tplBody]));
            $success = "Template '{$tplName}' saved.";
        }
    }
}

$totalUsers  = DB::count('users', 'status="active"');
$activeWeek  = DB::count('users', 'status="active" AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$newUsers7d  = DB::count('users', 'status="active" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');

// Email history from audit log
$emailHistory = DB::query(
    "SELECT l.*, a.name AS sender FROM admin_activity_logs l
     LEFT JOIN admins a ON a.id=l.admin_id
     WHERE l.action='email_sent'
     ORDER BY l.created_at DESC LIMIT 20"
);

// Saved templates
$allSettings = getPlatformSettings();
$templates   = [];
foreach ($allSettings as $k => $v) {
    if (str_starts_with($k, 'email_template_')) {
        $tpl = json_decode($v, true);
        if ($tpl) $templates[substr($k, 15)] = $tpl;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Email Users — Voxu Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
  <style>
    .template-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
    .template-chip{padding:5px 12px;background:var(--bg2);border:1px solid var(--border);border-radius:20px;font-size:12px;cursor:pointer;transition:.2s;color:var(--text2)}
    .template-chip:hover{border-color:var(--purple);color:var(--purple)}
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="admin-page-title">Email Users</div>
  </div>
  <div class="admin-content">
    <?php if ($success): ?><div class="alert alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">⚠ <?= clean($error)   ?></div><?php endif; ?>

    <?php if (!$supportEmail): ?>
      <div class="alert alert-warning">⚠ No support email set. Go to <a href="/admin/settings.php" style="color:var(--warning)">Settings → General</a> and enter a support email first.</div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:20px">
      <!-- COMPOSE -->
      <div class="table-card">
        <div class="table-header"><span class="table-title">✉ Compose Email</span></div>
        <form method="POST" style="padding:20px" id="emailForm">
          <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
          <input type="hidden" name="action" value="send_email"/>

          <?php if (!empty($templates)): ?>
          <div class="form-group">
            <label class="form-label">Quick Templates</label>
            <div class="template-chips">
              <?php foreach ($templates as $name => $tpl): ?>
                <div class="template-chip" onclick="loadTemplate(<?= htmlspecialchars(json_encode($tpl)) ?>)">
                  <?= clean($name) ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <div class="form-group">
            <label class="form-label">Target Audience</label>
            <select class="form-input" name="target" id="targetSelect" onchange="toggleSingleUser(this.value)">
              <option value="all">All Active Users (<?= number_format($totalUsers) ?>)</option>
              <option value="active_week">Active This Week (<?= number_format($activeWeek) ?>)</option>
              <option value="new">New Users – Last 7 Days (<?= number_format($newUsers7d) ?>)</option>
              <option value="single">Specific User</option>
            </select>
          </div>
          <div class="form-group hidden" id="singleUserRow">
            <label class="form-label">Username or Email</label>
            <input class="form-input" type="text" name="single_user" placeholder="Enter username or email address"/>
          </div>
          <div class="form-group">
            <label class="form-label">Subject Line</label>
            <input class="form-input" type="text" name="subject" id="emailSubject" required placeholder="e.g. Important update from Voxu"/>
          </div>
          <div class="form-group">
            <label class="form-label">Email Body</label>
            <textarea class="form-input" name="body" id="emailBody" rows="8" required
              placeholder="Write your email content here. Use [USERNAME] to personalize with the recipient's username."></textarea>
            <div class="form-hint">Use [USERNAME] as a placeholder for the user's name. Plain text — will be wrapped in Voxu branded template.</div>
          </div>
          <div style="display:flex;gap:10px">
            <button type="button" class="btn btn-secondary" onclick="previewEmail()">Preview</button>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Send this email to selected users?')">
              ✉ Send Email
            </button>
          </div>
        </form>

        <!-- SAVE TEMPLATE -->
        <div style="padding:0 20px 20px">
          <div style="height:1px;background:var(--border);margin-bottom:16px"></div>
          <p style="font-size:12px;color:var(--text3);margin-bottom:10px">Save current compose as a template</p>
          <form method="POST" style="display:flex;gap:8px">
            <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
            <input type="hidden" name="action" value="save_template"/>
            <input type="hidden" name="tpl_subject" id="saveTplSubj"/>
            <input type="hidden" name="tpl_body"    id="saveTplBody"/>
            <input class="form-input" type="text" name="template_name" placeholder="Template name" style="flex:1"/>
            <button type="submit" class="btn btn-secondary btn-sm" onclick="syncTemplate()">Save Template</button>
          </form>
        </div>
      </div>

      <!-- RIGHT PANEL -->
      <div>
        <!-- PREVIEW -->
        <div class="table-card" style="margin-bottom:16px">
          <div class="table-header"><span class="table-title">Preview</span></div>
          <div id="emailPreview" style="padding:16px;font-size:13px;color:var(--text2);min-height:80px">
            Fill in subject and body, then click Preview.
          </div>
        </div>

        <!-- EMAIL HISTORY -->
        <div class="table-card">
          <div class="table-header"><span class="table-title">Send History</span></div>
          <div style="padding:0 4px">
            <?php foreach ($emailHistory as $eh): ?>
            <div style="display:flex;justify-content:space-between;padding:10px 12px;border-bottom:1px solid var(--border);font-size:13px">
              <div>
                <div style="color:var(--text)"><?= clean($eh['description']) ?></div>
                <div style="font-size:11px;color:var(--text3)">By <?= clean($eh['sender']??'Admin') ?></div>
              </div>
              <div style="font-size:11px;color:var(--text3);white-space:nowrap"><?= timeAgo($eh['created_at']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($emailHistory)): ?><p style="text-align:center;padding:20px;color:var(--text3);font-size:13px">No emails sent yet</p><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSingleUser(val) {
  document.getElementById('singleUserRow').classList.toggle('hidden', val !== 'single');
}
function loadTemplate(tpl) {
  document.getElementById('emailSubject').value = tpl.subject || '';
  document.getElementById('emailBody').value    = tpl.body    || '';
}
function syncTemplate() {
  document.getElementById('saveTplSubj').value = document.getElementById('emailSubject').value;
  document.getElementById('saveTplBody').value = document.getElementById('emailBody').value;
}
function previewEmail() {
  const subj = document.getElementById('emailSubject').value;
  const body = document.getElementById('emailBody').value;
  document.getElementById('emailPreview').innerHTML =
    '<div style="background:#0B0B0F;padding:16px;border-radius:8px">' +
    '<div style="font-size:22px;font-weight:800;color:#fff;margin-bottom:12px">Vo<span style="color:#6C3BFF">xu</span></div>' +
    '<div style="font-size:15px;font-weight:600;color:#fff;margin-bottom:8px">' + subj.replace(/</g,'&lt;') + '</div>' +
    '<div style="color:#A0A0B0;font-size:13px;white-space:pre-wrap">' + body.replace(/</g,'&lt;') + '</div>' +
    '</div>';
}
</script>
</body>
</html>
