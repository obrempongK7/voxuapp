<?php
/**
 * Voxu Admin — Advertising Manager
 * Place image or HTML ads in various sections of the site
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('admin');

$admin      = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$activeMenu = 'advertising';
$success    = '';
$error      = '';

// ── ENSURE ad_slots TABLE EXISTS ────────────────────────────
DB::exec("CREATE TABLE IF NOT EXISTS `ad_slots` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`            VARCHAR(120)  NOT NULL,
  `slot`             VARCHAR(60)   NOT NULL COMMENT 'e.g. feed_top, sidebar, post_bottom',
  `type`             ENUM('image','html','adsense') NOT NULL DEFAULT 'image',
  `image_url`        VARCHAR(255)  DEFAULT NULL,
  `link_url`         VARCHAR(500)  DEFAULT NULL,
  `open_new_tab`     TINYINT(1)    NOT NULL DEFAULT 1,
  `custom_html`      TEXT          DEFAULT NULL,
  `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order`       INT UNSIGNED  NOT NULL DEFAULT 0,
  `impression_count` INT UNSIGNED  NOT NULL DEFAULT 0,
  `click_count`      INT UNSIGNED  NOT NULL DEFAULT 0,
  `expires_at`       DATETIME      DEFAULT NULL,
  `created_by`       INT UNSIGNED  DEFAULT NULL,
  `created_at`       DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_slot     (slot),
  INDEX idx_active   (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title      = sanitize($_POST['title']       ?? '');
        $slot       = sanitize($_POST['slot']        ?? '');
        $type       = sanitize($_POST['type']        ?? 'image');
        $linkUrl    = sanitizeUrl($_POST['link_url'] ?? '');
        $newTab     = isset($_POST['open_new_tab']) ? 1 : 0;
        $isActive   = isset($_POST['is_active'])    ? 1 : 0;
        $sortOrder  = (int)($_POST['sort_order']    ?? 0);
        $expiresAt  = sanitize($_POST['expires_at'] ?? '');
        $customHtml = ($admin['role'] === 'super_admin') ? trim($_POST['custom_html'] ?? '') : '';

        if (!$title || !$slot) {
            $error = 'Title and slot are required.';
        } else {
            $imageUrl = null;
            if ($type === 'image' && !empty($_FILES['ad_image']['name'])) {
                if ($_FILES['ad_image']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Image upload error code ' . $_FILES['ad_image']['error'];
                } else {
                    $up = uploadFile($_FILES['ad_image'], 'ad');
                    if (!$up['ok']) { $error = $up['error']; }
                    else { $imageUrl = $up['url']; }
                }
            }

            if (!$error) {
                $data = [
                    'title'       => $title,
                    'slot'        => $slot,
                    'type'        => in_array($type,['image','html','adsense']) ? $type : 'image',
                    'link_url'    => $linkUrl,
                    'open_new_tab'=> $newTab,
                    'is_active'   => $isActive,
                    'sort_order'  => $sortOrder,
                    'expires_at'  => $expiresAt ?: null,
                    'custom_html' => $customHtml,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ];
                if ($imageUrl) $data['image_url'] = $imageUrl;

                if ($action === 'create') {
                    $data['created_by'] = (int)$admin['id'];
                    $data['created_at'] = date('Y-m-d H:i:s');
                    DB::insert('ad_slots', $data);
                    logAdminAction((int)$admin['id'], 'ad_create', "Created ad: {$title} in slot {$slot}");
                    $success = "Ad '{$title}' created.";
                } else {
                    $adId = (int)($_POST['ad_id'] ?? 0);
                    DB::update('ad_slots', $data, ['id' => $adId]);
                    logAdminAction((int)$admin['id'], 'ad_update', "Updated ad #{$adId}: {$title}");
                    $success = "Ad updated.";
                }
            }
        }
    } elseif ($action === 'toggle') {
        $adId  = (int)($_POST['ad_id'] ?? 0);
        $ad    = DB::first('SELECT is_active FROM ad_slots WHERE id=?', [$adId]);
        if ($ad) {
            DB::update('ad_slots', ['is_active' => $ad['is_active'] ? 0 : 1], ['id' => $adId]);
            logAdminAction((int)$admin['id'], 'ad_toggle', "Toggled ad #{$adId}");
            $success = 'Ad status toggled.';
        }
    } elseif ($action === 'delete') {
        $adId = (int)($_POST['ad_id'] ?? 0);
        DB::exec('DELETE FROM ad_slots WHERE id=?', [$adId]);
        logAdminAction((int)$admin['id'], 'ad_delete', "Deleted ad #{$adId}");
        $success = 'Ad deleted.';
    }
}

$ads = DB::query('SELECT * FROM ad_slots ORDER BY slot ASC, sort_order ASC, created_at DESC');

// Slot definitions with descriptions
$slotDefs = [
    'feed_top'        => ['🔝 Feed Top',          'Above the voice feed on the main dashboard'],
    'feed_middle'     => ['🔁 Feed Middle',        'Between voice posts (every N posts)'],
    'feed_bottom'     => ['⬇ Feed Bottom',         'Below the voice feed'],
    'status_top'      => ['✨ Status Hub Top',      'Above the status feed'],
    'post_bottom'     => ['📄 Post Page Bottom',    'Below a single post on /post/{id}'],
    'wallet_top'      => ['💰 Wallet Top',          'Above the wallet balance card'],
    'sidebar_right'   => ['➡ Right Sidebar',        'Desktop right sidebar (future)'],
    'landing_banner'  => ['🏠 Landing Page Banner', 'Banner in the landing page hero'],
    'premium_page'    => ['⭐ Premium Page',         'Shown on the premium plans page'],
    'profile_bottom'  => ['👤 Profile Bottom',      'Below user profile stats'],
];

// Group ads by slot
$adsBySlot = [];
foreach ($ads as $ad) {
    $adsBySlot[$ad['slot']][] = $ad;
}

// Summary stats
$totalAds   = count($ads);
$activeAds  = count(array_filter($ads, fn($a) => $a['is_active']));
$totalImpr  = array_sum(array_column($ads, 'impression_count'));
$totalClicks= array_sum(array_column($ads, 'click_count'));
$ctr        = $totalImpr > 0 ? round($totalClicks / $totalImpr * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Advertising — Voxu Admin</title>
  <meta name="csrf" content="<?= csrfToken() ?>"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
  <style>
    .slot-section{background:var(--card);border:1px solid var(--border);border-radius:12px;margin-bottom:16px;overflow:hidden}
    .slot-header{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);background:var(--bg3)}
    .slot-title{font-size:14px;font-weight:600;color:#fff}
    .slot-desc{font-size:11px;color:var(--text3);margin-top:2px}
    .slot-ads{padding:12px}
    .ad-row{display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;margin-bottom:8px}
    .ad-thumb{width:56px;height:40px;object-fit:cover;border-radius:6px;flex-shrink:0;background:var(--bg3)}
    .ad-info{flex:1;min-width:0}
    .ad-title{font-size:13px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .ad-meta{font-size:11px;color:var(--text3);margin-top:2px}
    .ad-stats{text-align:right;font-size:11px;color:var(--text2);white-space:nowrap}
    .preview-img{max-width:100%;max-height:120px;object-fit:cover;border-radius:8px;margin-top:8px}
    .slot-chip{display:inline-flex;padding:4px 10px;background:var(--purple-l);color:var(--purple);border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;user-select:none}
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="admin-page-title">Advertising Manager</div>
    <div class="topbar-actions">
      <button class="btn btn-primary btn-sm" onclick="openCreateModal()">+ New Ad</button>
    </div>
  </div>
  <div class="admin-content">
    <?php if ($success): ?><div class="alert alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">⚠ <?= clean($error)   ?></div><?php endif; ?>

    <!-- STATS -->
    <div class="admin-stats" style="margin-bottom:20px">
      <div class="admin-stat-card" style="--indicator:var(--purple)">
        <div class="admin-stat-label">Total Ads</div>
        <div class="admin-stat-val"><?= $totalAds ?></div>
        <div class="admin-stat-sub up"><?= $activeAds ?> active</div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--blue)">
        <div class="admin-stat-label">Total Impressions</div>
        <div class="admin-stat-val"><?= number_format($totalImpr) ?></div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--green)">
        <div class="admin-stat-label">Total Clicks</div>
        <div class="admin-stat-val"><?= number_format($totalClicks) ?></div>
      </div>
      <div class="admin-stat-card" style="--indicator:var(--warning)">
        <div class="admin-stat-label">CTR</div>
        <div class="admin-stat-val"><?= $ctr ?>%</div>
        <div class="admin-stat-sub">Click-through rate</div>
      </div>
    </div>

    <!-- INTEGRATION GUIDE -->
    <div class="table-card" style="margin-bottom:20px">
      <div class="table-header"><span class="table-title">📘 How to Use Ads in Your Pages</span></div>
      <div style="padding:16px">
        <p style="font-size:13px;color:var(--text2);margin-bottom:12px">Insert ads into any PHP page using the <code style="background:var(--bg3);padding:2px 6px;border-radius:4px;color:var(--blue)">renderAds()</code> function:</p>
        <?php foreach ($slotDefs as $slotKey => [$label, $desc]): ?>
        <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
          <div class="slot-chip" onclick="navigator.clipboard?.writeText('<?= htmlspecialchars("<?= renderAds('{$slotKey}') ?>", ENT_QUOTES) ?>').then(()=>Toast.success('Copied!'))"><?= $label ?></div>
          <div>
            <code style="font-size:12px;color:var(--blue)">&lt;?= renderAds('<?= $slotKey ?>') ?&gt;</code>
            <div style="font-size:11px;color:var(--text3);margin-top:2px"><?= $desc ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ADS BY SLOT -->
    <?php foreach ($slotDefs as $slotKey => [$label, $desc]): ?>
    <div class="slot-section">
      <div class="slot-header">
        <div>
          <div class="slot-title"><?= $label ?></div>
          <div class="slot-desc"><?= $desc ?> &nbsp;·&nbsp; slot: <code><?= $slotKey ?></code></div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openCreateModal('<?= $slotKey ?>')">+ Add</button>
      </div>
      <div class="slot-ads">
        <?php if (empty($adsBySlot[$slotKey])): ?>
          <p style="text-align:center;color:var(--text3);font-size:13px;padding:14px 0">No ads in this slot</p>
        <?php else: ?>
          <?php foreach ($adsBySlot[$slotKey] as $ad): ?>
          <div class="ad-row">
            <?php if ($ad['image_url']): ?>
              <img src="<?= clean($ad['image_url']) ?>" alt="" class="ad-thumb"/>
            <?php else: ?>
              <div class="ad-thumb" style="display:flex;align-items:center;justify-content:center;font-size:18px"><?= $ad['type']==='adsense'?'📊':'📝' ?></div>
            <?php endif; ?>
            <div class="ad-info">
              <div class="ad-title"><?= clean($ad['title']) ?></div>
              <div class="ad-meta">
                <span class="badge <?= $ad['is_active']?'badge-green':'badge-muted' ?>"><?= $ad['is_active']?'Active':'Paused' ?></span>
                <span style="margin:0 4px">·</span><?= strtoupper($ad['type']) ?>
                <?php if ($ad['expires_at']): ?><span style="margin-left:4px">· Expires <?= date('d M Y', strtotime($ad['expires_at'])) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="ad-stats">
              <div><?= number_format($ad['impression_count']) ?> impressions</div>
              <div><?= number_format($ad['click_count']) ?> clicks</div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0">
              <button class="btn btn-secondary btn-sm" onclick='editAd(<?= htmlspecialchars(json_encode($ad), ENT_QUOTES) ?>)'>Edit</button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
                <input type="hidden" name="action"  value="toggle"/>
                <input type="hidden" name="ad_id"   value="<?= $ad['id'] ?>"/>
                <button type="submit" class="btn btn-sm <?= $ad['is_active']?'btn-secondary':'btn-success' ?>"><?= $ad['is_active']?'Pause':'Resume' ?></button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this ad?')">
                <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
                <input type="hidden" name="action"  value="delete"/>
                <input type="hidden" name="ad_id"   value="<?= $ad['id'] ?>"/>
                <button type="submit" class="btn btn-danger btn-sm">✕</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- CREATE / EDIT AD MODAL -->
<div class="modal-backdrop" id="adModal">
  <div class="admin-modal" style="max-width:580px">
    <div class="admin-modal-header">
      <div class="admin-modal-title" id="adModalTitle">New Ad</div>
      <button onclick="document.getElementById('adModal').classList.remove('open')" style="color:var(--text2)">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="admin-modal-body">
        <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action"  id="adAction"  value="create"/>
        <input type="hidden" name="ad_id"   id="adId"      value=""/>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Ad Title *</label>
            <input class="form-input" type="text" name="title" id="adTitle" required placeholder="e.g. Summer Sale Banner"/>
          </div>
          <div class="form-group">
            <label class="form-label">Ad Type *</label>
            <select class="form-input" name="type" id="adType" onchange="toggleAdType(this.value)">
              <option value="image">🖼 Image Ad</option>
              <option value="html">📝 Custom HTML</option>
              <option value="adsense">📊 Google AdSense</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Slot / Placement *</label>
          <select class="form-input" name="slot" id="adSlot">
            <?php foreach ($slotDefs as $key => [$lbl, $d]): ?>
              <option value="<?= $key ?>"><?= $lbl ?> (<?= $key ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- IMAGE FIELDS -->
        <div id="imageFields">
          <div class="form-group">
            <label class="form-label">Ad Image</label>
            <input class="form-input" type="file" name="ad_image" id="adImageInput" accept="image/jpeg,image/png,image/gif,image/webp"/>
            <div class="form-hint">JPG, PNG, GIF, WebP — max 10MB</div>
            <img id="adImgPreview" class="preview-img hidden"/>
            <div id="existingImg" style="display:none;margin-top:8px">
              <img id="existingImgEl" style="max-height:80px;border-radius:6px"/>
              <div style="font-size:11px;color:var(--text3);margin-top:4px">Current image — upload new to replace</div>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Click URL</label>
            <input class="form-input" type="url" name="link_url" id="adLink" placeholder="https://…"/>
          </div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:10px">
            <label class="toggle">
              <input type="checkbox" name="open_new_tab" id="adNewTab" value="1" checked/>
              <span class="toggle-track"></span>
              <span class="toggle-label">Open in new tab</span>
            </label>
          </div>
        </div>

        <!-- HTML FIELDS (super_admin only) -->
        <div id="htmlFields" class="hidden">
          <?php if ($admin['role'] === 'super_admin'): ?>
          <div class="form-group">
            <label class="form-label">Custom HTML</label>
            <textarea class="form-input" name="custom_html" id="adHtml" rows="5" placeholder="Paste your ad HTML here…"></textarea>
            <div class="form-hint">⚠ HTML is rendered as-is. Only Super Admins can set this.</div>
          </div>
          <?php else: ?>
          <div class="alert alert-warning">Only Super Admins can insert custom HTML ads.</div>
          <?php endif; ?>
        </div>

        <!-- ADSENSE FIELDS -->
        <div id="adsenseFields" class="hidden">
          <div class="form-group">
            <label class="form-label">AdSense Code</label>
            <textarea class="form-input" name="custom_html" rows="4" placeholder="Paste your Google AdSense &lt;script&gt; + ad unit code here…"></textarea>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Sort Order</label>
            <input class="form-input" type="number" name="sort_order" id="adSort" value="0" min="0"/>
            <div class="form-hint">Lower = shown first</div>
          </div>
          <div class="form-group">
            <label class="form-label">Expires At (leave blank = never)</label>
            <input class="form-input" type="datetime-local" name="expires_at" id="adExpires"/>
          </div>
        </div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:10px">
          <label class="toggle">
            <input type="checkbox" name="is_active" id="adActive" value="1" checked/>
            <span class="toggle-track"></span>
            <span class="toggle-label">Active (show immediately)</span>
          </label>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('adModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="adSubmitBtn">Create Ad</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreateModal(slot) {
  document.getElementById('adModalTitle').textContent = 'New Ad';
  document.getElementById('adAction').value  = 'create';
  document.getElementById('adId').value      = '';
  document.getElementById('adTitle').value   = '';
  document.getElementById('adLink').value    = '';
  document.getElementById('adHtml') && (document.getElementById('adHtml').value = '');
  document.getElementById('adSort').value    = '0';
  document.getElementById('adExpires').value = '';
  document.getElementById('adActive').checked   = true;
  document.getElementById('adNewTab').checked   = true;
  document.getElementById('adImgPreview').classList.add('hidden');
  document.getElementById('existingImg').style.display = 'none';
  document.getElementById('adSubmitBtn').textContent = 'Create Ad';
  if (slot) document.getElementById('adSlot').value = slot;
  toggleAdType('image');
  document.getElementById('adModal').classList.add('open');
}

function editAd(ad) {
  document.getElementById('adModalTitle').textContent = 'Edit Ad';
  document.getElementById('adAction').value  = 'update';
  document.getElementById('adId').value      = ad.id;
  document.getElementById('adTitle').value   = ad.title || '';
  document.getElementById('adSlot').value    = ad.slot  || '';
  document.getElementById('adType').value    = ad.type  || 'image';
  document.getElementById('adLink').value    = ad.link_url || '';
  if (document.getElementById('adHtml')) document.getElementById('adHtml').value = ad.custom_html || '';
  document.getElementById('adSort').value    = ad.sort_order || '0';
  document.getElementById('adExpires').value = ad.expires_at ? ad.expires_at.replace(' ','T').substring(0,16) : '';
  document.getElementById('adActive').checked = !!ad.is_active;
  document.getElementById('adNewTab').checked = !!ad.open_new_tab;
  document.getElementById('adSubmitBtn').textContent = 'Save Changes';
  if (ad.image_url) {
    document.getElementById('existingImgEl').src = ad.image_url;
    document.getElementById('existingImg').style.display = 'block';
  } else {
    document.getElementById('existingImg').style.display = 'none';
  }
  toggleAdType(ad.type || 'image');
  document.getElementById('adModal').classList.add('open');
}

function toggleAdType(type) {
  document.getElementById('imageFields').classList.toggle('hidden',   type !== 'image');
  document.getElementById('htmlFields').classList.toggle('hidden',    type !== 'html');
  document.getElementById('adsenseFields').classList.toggle('hidden', type !== 'adsense');
}

// Image preview
document.getElementById('adImageInput')?.addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const prev = document.getElementById('adImgPreview');
    prev.src = ev.target.result;
    prev.classList.remove('hidden');
  };
  reader.readAsDataURL(file);
});

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-backdrop')) e.target.classList.remove('open');
});

if (!window.Toast || typeof window.Toast.success !== 'function') {
  window.Toast = {
    success(message) { alert(message); },
    error(message) { alert(message); }
  };
}
</script>
</body>
</html>
