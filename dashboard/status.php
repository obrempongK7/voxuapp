<?php
// @author  Jcode | ObrempongK
// dashboard/status.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/i18n.php';
requireAuth();

$user     = auth();
$settings = getPlatformSettings();
$appName  = clean($settings['app_name'] ?? 'Voxu');
$showCreate = isset($_GET['create']);

// Fetch active statuses (excluding expired)
$statuses = DB::query(
    "SELECT s.*, u.username, up.avatar
     FROM status_posts s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN user_profiles up ON up.user_id = s.user_id
     WHERE s.status = 'active' AND s.expires_at > NOW()
     ORDER BY s.created_at DESC
     LIMIT 60"
);

// Group by user for story-style display
$byUser = [];
foreach ($statuses as $s) {
    $byUser[$s['user_id']] = $byUser[$s['user_id']] ?? ['user' => ['username'=>$s['username'],'avatar'=>$s['avatar']], 'items'=>[]];
    $byUser[$s['user_id']]['items'][] = $s;
}

// My statuses
$myStatuses = DB::query(
    "SELECT * FROM status_posts WHERE user_id=? ORDER BY created_at DESC LIMIT 20",
    [(int)$user['id']]
);
$theme = getTheme();
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>" <?= isRtl() ? 'dir="rtl"' : '' ?>>
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Status Hub — Voxu</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
  <style>
    .story-row{display:flex;gap:12px;overflow-x:auto;padding:4px 0 12px;scrollbar-width:none;-webkit-overflow-scrolling:touch}
    .story-row::-webkit-scrollbar{display:none}
    .story-item{display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;flex-shrink:0;width:68px}
    .story-ring{width:64px;height:64px;border-radius:50%;padding:3px;background:linear-gradient(135deg,var(--purple),var(--blue));transition:.2s}
    .story-ring.seen{background:var(--bg2)}
    .story-ring:hover{transform:scale(1.06)}
    .story-inner{width:100%;height:100%;border-radius:50%;background:var(--card);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--purple);overflow:hidden;border:2px solid var(--bg)}
    .story-inner img{width:100%;height:100%;object-fit:cover}
    .story-name{font-size:10px;color:var(--text2);text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:100%}
    .story-add{background:var(--bg2);border:2px dashed var(--border2)}
    .story-add-inner{background:var(--purple-l);color:var(--purple);font-size:24px}
    .status-stats-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px}
    .mini-stat{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center}
    .mini-stat .num{font-size:20px;font-weight:800;color:#fff}
    .mini-stat .lbl{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--text2);margin-top:2px}
    .theme-toggle { width: 38px; height: 20px; background: var(--bg3); border-radius: 10px; position: relative; cursor: pointer; border: 1px solid var(--border2); flex-shrink: 0; }
    .theme-toggle-knob { position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--purple); transition: left .2s; }
    body.theme-light .theme-toggle-knob { left: 20px; background: var(--warning); }
  </style>
</head>
<body class="<?= clean(themeClass()) ?>">
<nav class="sk-topnav">
  <a href="/dashboard/feed.php" class="sk-logo"><?= $appName ?><span class="dot">.</span></a>
  <div style="position:absolute;left:50%;transform:translateX(-50%);font-size:17px;font-weight:700;color:var(--text)">&#10024; Status</div>
  <div class="sk-nav-actions">
    <a href="/dashboard/notifications.php" class="sk-nav-btn" title="Notifications">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </a>
    <a href="/dashboard/profile.php" style="flex-shrink:0;text-decoration:none">
      <div class="avatar avatar-sm"><?php if(!empty($user['avatar'])): ?><img src="<?= clean($user['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?></div>
    </a>
  </div>
</nav>

<div class="app-layout">
  <div class="page">
    <!-- TABS -->
    <div class="tabs">
      <div class="tab active" id="tab-discover" onclick="switchTab('discover')">Discover</div>
      <div class="tab" id="tab-mine" onclick="switchTab('mine')">My Statuses</div>
    </div>

    <!-- DISCOVER TAB -->
    <div id="pane-discover">
      <!-- Stories Row -->
      <div class="story-row">
        <div class="story-item" onclick="Modal.open('status-create-modal')">
          <div class="story-ring" style="background:var(--bg2)">
            <div class="story-inner story-add-inner">+</div>
          </div>
          <div class="story-name">Your Story</div>
        </div>
        <?php foreach ($byUser as $uid => $group): $u = $group['user']; $items = $group['items']; ?>
        <div class="story-item" onclick="openStatusGroup(<?= htmlspecialchars(json_encode(array_values($items))) ?>,0)">
          <div class="story-ring">
            <div class="story-inner">
              <?php if ($u['avatar']): ?>
                <img src="<?= clean($u['avatar']) ?>" alt="<?= clean($u['username']) ?>"/>
              <?php else: ?>
                <?= avatarInitials($u['username']) ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="story-name">@<?= clean($u['username']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Grid View -->
      <?php if (empty($statuses)): ?>
        <div class="empty">
          <div class="empty-icon">✨</div>
          <div class="empty-title">No statuses yet</div>
          <p class="empty-text">Be the first to post a status and earn!</p>
          <button onclick="Modal.open('status-create-modal')" class="btn btn-primary">Post Status</button>
        </div>
      <?php else: ?>
        <div class="status-grid">
          <?php foreach ($statuses as $s): ?>
            <div class="status-thumb" onclick="openSingleStatus(<?= htmlspecialchars(json_encode($s)) ?>)">
              <?php if ($s['type']==='image' && $s['media_url']): ?>
                <img src="<?= clean($s['media_url']) ?>" alt="" loading="lazy"/>
              <?php elseif ($s['type']==='video' && $s['media_url']): ?>
                <video src="<?= clean($s['media_url']) ?>" muted preload="metadata"></video>
              <?php else: ?>
                <div style="width:100%;height:100%;background:linear-gradient(135deg,<?= clean($s['bg_color']??'#6C3BFF,#00D1FF') ?>);display:flex;align-items:center;justify-content:center;padding:12px">
                  <span style="font-size:13px;font-weight:700;color:#fff;text-align:center;line-height:1.3"><?= clean(substr($s['text']??'',0,60)) ?></span>
                </div>
              <?php endif; ?>
              <div class="status-thumb-overlay">
                <div class="status-thumb-user">
                  <div class="avatar" style="width:22px;height:22px;font-size:9px"><?= avatarInitials($s['username']) ?></div>
                  <span class="status-thumb-name"><?= clean($s['username']) ?></span>
                </div>
                <div class="status-thumb-caption"><?= clean($s['caption']??'') ?></div>
              </div>
              <?php if ($s['type']==='video'): ?>
                <div style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.5);border-radius:4px;padding:2px 6px;font-size:10px;color:#fff">▶</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- MY STATUSES TAB -->
    <div id="pane-mine" class="hidden">
      <?php
        $totalViews  = array_sum(array_column($myStatuses,'views_count'));
        $totalClicks = array_sum(array_column($myStatuses,'clicks_count'));
        $totalPts    = array_sum(array_column($myStatuses,'earnings_points'));
      ?>
      <div class="status-stats-row">
        <div class="mini-stat">
          <div class="num"><?= number_format($totalViews) ?></div>
          <div class="lbl">Total Views</div>
        </div>
        <div class="mini-stat">
          <div class="num"><?= number_format($totalClicks) ?></div>
          <div class="lbl">Clicks</div>
        </div>
        <div class="mini-stat">
          <div class="num" style="color:var(--green)"><?= number_format($totalPts) ?></div>
          <div class="lbl">Points Earned</div>
        </div>
      </div>

      <?php if (empty($myStatuses)): ?>
        <div class="empty">
          <div class="empty-icon">📸</div>
          <div class="empty-title">No statuses posted</div>
          <p class="empty-text">Post your first status to start earning from views and clicks.</p>
          <button onclick="Modal.open('status-create-modal')" class="btn btn-primary">Post Now</button>
        </div>
      <?php else: ?>
        <div class="feed">
          <?php foreach ($myStatuses as $s): ?>
          <div class="card">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <span class="badge <?= $s['type']==='image'?'badge-blue':($s['type']==='video'?'badge-purple':'badge-muted') ?>">
                  <?= strtoupper($s['type']) ?>
                </span>
                <span class="badge <?= $s['status']==='active'?'badge-green':($s['status']==='expired'?'badge-muted':'badge-danger') ?>">
                  <?= ucfirst($s['status']) ?>
                </span>
              </div>
              <button class="btn btn-ghost btn-icon-sm" onclick="deleteStatus(<?= $s['id'] ?>)" style="color:var(--danger)">✕</button>
            </div>
            <?php if ($s['caption']): ?>
              <p style="font-size:14px;color:var(--text);margin-bottom:10px"><?= clean($s['caption']) ?></p>
            <?php endif; ?>
            <div class="flex gap-4" style="font-size:13px;color:var(--text2)">
              <span>👁 <?= number_format((int)$s['views_count']) ?> views</span>
              <span>📞 <?= number_format((int)$s['clicks_count']) ?> clicks</span>
              <span style="color:var(--green)">⚡ <?= number_format((int)$s['earnings_points']) ?> pts</span>
            </div>
            <div style="font-size:11px;color:var(--text3);margin-top:8px">
              Expires: <?= date('d M Y H:i', strtotime($s['expires_at'])) ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="/dashboard/" class="bottom-nav-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
    Voice
  </a>
  <a href="/dashboard/status.php" class="bottom-nav-item active">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
    Status
  </a>
  <a href="#" class="bottom-nav-item" onclick="Modal.open('status-create-modal');return false">
    <div class="bottom-nav-create">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </div>
  </a>
  <a href="/dashboard/wallet.php" class="bottom-nav-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
    Wallet
  </a>
  <a href="/dashboard/profile.php" class="bottom-nav-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    Profile
  </a>
</nav>

<!-- STATUS VIEWER (FULL SCREEN) -->
<div id="status-viewer" class="status-viewer hidden">
  <div class="status-progress-bars" id="status-prog-bars"></div>
  <div class="status-viewer-header">
    <div class="status-user-info">
      <div class="avatar avatar-sm" style="border:2px solid rgba(255,255,255,0.3)">
        <span class="status-user-avatar-text" style="font-size:10px"></span>
      </div>
      <span class="status-user-name" style="font-size:13px;font-weight:600;color:#fff;margin-left:8px"></span>
    </div>
    <button class="status-close" onclick="StatusViewer.close()">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="status-content" id="status-content">
    <div id="status-nav-prev" style="position:absolute;left:0;top:0;width:40%;height:100%;z-index:5;cursor:pointer" onclick="StatusViewer.prev()"></div>
    <div id="status-nav-next" style="position:absolute;right:0;top:0;width:40%;height:100%;z-index:5;cursor:pointer" onclick="StatusViewer.next()"></div>
  </div>
  <div class="status-viewer-footer">
    <div class="status-caption"></div>
    <div class="status-source"></div>
    <a id="status-contact-btn" href="#" target="_blank" rel="noopener noreferrer" class="contact-btn hidden" onclick="trackClick(event)">
      📞 Contact / View Profile
    </a>
  </div>
</div>

<!-- CREATE STATUS MODAL -->
<div class="modal-overlay" id="status-create-modal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title">New Status</div>
    <form id="statusForm" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
      <div class="input-group mb-3">
        <label class="input-label">Type</label>
        <select class="input" id="statusType" name="type" onchange="updateStatusForm()">
          <option value="image">📷 Image</option>
          <option value="video">🎬 Video</option>
          <option value="text">📝 Text</option>
        </select>
      </div>
      <div id="mediaInput" class="input-group mb-3">
        <label class="input-label">Media File</label>
        <input class="input" type="file" name="media_file" id="statusMedia" accept="image/*,video/*"/>
        <img id="statusPreview" class="hidden mt-2" style="max-height:160px;border-radius:8px;object-fit:cover;width:100%"/>
      </div>
      <div id="textInput" class="input-group mb-3 hidden">
        <label class="input-label">Text Content</label>
        <textarea class="input" name="text" rows="3" placeholder="What's your message?"></textarea>
      </div>
      <div class="input-group mb-3">
        <label class="input-label">Caption</label>
        <input class="input" type="text" name="caption" placeholder="Add a caption..." maxlength="200"/>
      </div>
      <div class="input-group mb-3">
        <label class="input-label">Source / Label <span style="color:var(--text3)">(optional)</span></label>
        <input class="input" type="text" name="source_label" placeholder="e.g. My Business, My Store"/>
      </div>
      <div class="input-group mb-4">
        <label class="input-label">Contact / Profile Link <span style="color:var(--text3)">(earn from clicks)</span></label>
        <input class="input" type="url" name="contact_link" placeholder="https://wa.me/... or instagram.com/..."/>
      </div>
      <button type="submit" class="btn btn-primary w-full" id="submitStatus">
        ✨ Post Status
      </button>
    </form>
  </div>
</div>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
function switchTab(tab) {
  document.getElementById('pane-discover').classList.toggle('hidden', tab !== 'discover');
  document.getElementById('pane-mine').classList.toggle('hidden', tab !== 'mine');
  document.getElementById('tab-discover').classList.toggle('active', tab === 'discover');
  document.getElementById('tab-mine').classList.toggle('active', tab === 'mine');
}

function updateStatusForm() {
  const type = document.getElementById('statusType').value;
  document.getElementById('mediaInput').classList.toggle('hidden', type === 'text');
  document.getElementById('textInput').classList.toggle('hidden', type !== 'text');
}

setupImagePreview('statusMedia','statusPreview');

// Open a group of statuses (StatusViewer._buildProgressBars handles bar creation)
function openStatusGroup(items, start) {
  if (!items || !items.length) return;
  // Clear existing bars so StatusViewer rebuilds them fresh
  const bars = document.getElementById('status-prog-bars');
  if (bars) bars.innerHTML = '';
  StatusViewer.open(items, start);
}

function openSingleStatus(item) {
  openStatusGroup([item], 0);
}

async function trackClick(e) {
  const btn = e.currentTarget;
  const statusId = btn.dataset.statusId;
  if (statusId) {
    await fetch(`/api/v1/status/${statusId}/click`, { method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-CSRF-Token': getCsrfToken()}, body: JSON.stringify({click_type:'link'})});
  }
}

// Submit status
document.getElementById('statusForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('submitStatus');
  setLoading(btn, true);
  const fd = new FormData(this);
  const res = await fetch('/api/v1/status/create', {
    method:'POST',
    credentials:'same-origin',
    headers: getCsrfToken() ? {'X-CSRF-Token': getCsrfToken()} : {},
    body: fd
  });
  const data = await res.json();
  setLoading(btn, false);
  if (data.success) {
    Toast.success('Status posted!');
    Modal.close('status-create-modal');
    setTimeout(() => location.reload(), 800);
  } else {
    Toast.error(data.message || 'Failed to post status');
  }
});

async function deleteStatus(id) {
  if (!confirm('Delete this status?')) return;
  const res = await API.del('/status/' + id);
  if (res?.success) { Toast.success('Deleted'); setTimeout(() => location.reload(), 600); }
}

// StatusViewer.updateContent is now fully handled by voxu.js
// We only need to post-process the contact button class for branded styling
const _origUpdate = StatusViewer.updateContent.bind(StatusViewer);
StatusViewer.updateContent = function(s) {
  _origUpdate(s); // call the base implementation
  // Apply branded contact button style
  const btn = document.getElementById('status-contact-btn');
  if (btn && s.contact_link) {
    btn.className = 'contact-btn';
    if (s.contact_link.includes('wa.me')      || s.contact_link.includes('whatsapp')) btn.classList.add('whatsapp');
    else if (s.contact_link.includes('instagram.com')) btn.classList.add('instagram');
    btn.dataset.statusId = s.id;
  }
  // Update avatar initials
  const avatarEl = document.querySelector('.status-viewer-header .avatar');
  if (avatarEl && s.username) {
    const initials = s.username.substring(0, 2).toUpperCase();
    if (!avatarEl.querySelector('img')) avatarEl.textContent = initials;
  }
};

<?php if ($showCreate): ?>
document.addEventListener('DOMContentLoaded', () => Modal.open('status-create-modal'));
<?php endif; ?>
</script>
</body>
</html>
