<?php
/**
 * Voxu — Podcast Hub
 * Free users: 10 min | Silver: 30 min | Gold: 60 min | Platinum: unlimited
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/i18n.php';
requireAuth();

$user     = auth();
$userId   = (int)$user['id'];
$settings = getPlatformSettings();
$myPlan   = getUserPlan($userId);
$podLimit = getPodcastLimit($userId);
$showCreate = isset($_GET['create']);
$tab      = sanitize($_GET['tab'] ?? 'browse');
$page     = max(1,(int)($_GET['page']??1));
$perPage  = 12;
$offset   = ($page-1)*$perPage;

// ── ENSURE PODCASTS TABLE EXISTS ─────────────────────
DB::exec("CREATE TABLE IF NOT EXISTS `podcasts` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL,
  `title`        VARCHAR(200) NOT NULL,
  `description`  TEXT         DEFAULT NULL,
  `audio_url`    VARCHAR(255) NOT NULL,
  `cover_url`    VARCHAR(255) DEFAULT NULL,
  `duration`     INT UNSIGNED DEFAULT 0,
  `category`     VARCHAR(60)  DEFAULT 'general',
  `play_count`   INT UNSIGNED DEFAULT 0,
  `like_count`   INT UNSIGNED DEFAULT 0,
  `status`       ENUM('active','removed','pending') DEFAULT 'active',
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user  (user_id),
  INDEX idx_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── LOAD DATA ─────────────────────────────────────────
if ($tab === 'mine') {
    $total    = DB::count('podcasts','user_id=? AND status="active"',[$userId]);
    $podcasts = DB::query(
        "SELECT p.*, u.username, up.avatar
         FROM podcasts p JOIN users u ON u.id=p.user_id
         LEFT JOIN user_profiles up ON up.user_id=p.user_id
         WHERE p.user_id=? AND p.status='active'
         ORDER BY p.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
        [$userId]
    );
} else {
    $total    = DB::count('podcasts','status="active"');
    $podcasts = DB::query(
        "SELECT p.*, u.username, up.avatar
         FROM podcasts p JOIN users u ON u.id=p.user_id
         LEFT JOIN user_profiles up ON up.user_id=p.user_id
         WHERE p.status='active'
         ORDER BY p.play_count DESC, p.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
}
$totalPages = max(1, ceil($total/$perPage));

$categories = ['general','technology','business','health','education','entertainment','news','sports','arts','science'];
$unreadNotifs = DB::count('notifications','user_id=? AND is_read=0',[$userId]);
$theme = $_COOKIE['voxu_theme'] ?? 'dark';

function fmtDur(int $s): string {
    if (!$s) return '0:00';
    $m = floor($s/60); $sec = $s%60;
    return "{$m}:".str_pad($sec,2,'0',STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>" <?= isRtl() ? 'dir="rtl"' : '' ?>>
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta name="csrf-token" content="<?= csrfToken() ?>"/>
  <title>Podcast — <?= clean($settings['app_name']??'Voxu') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
  <style>
    .pod-page{padding:calc(var(--nav-h)+16px) 16px calc(var(--bottom-h)+20px);max-width:960px;margin:0 auto}
    .pod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:16px}
    .pod-card{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;transition:var(--transition);cursor:pointer}
    .pod-card:hover{border-color:rgba(108,59,255,.4);transform:translateY(-2px);box-shadow:var(--shadow)}
    .pod-cover{width:100%;aspect-ratio:1;object-fit:cover;background:linear-gradient(135deg,var(--purple-l),var(--bg3));display:flex;align-items:center;justify-content:center;font-size:48px}
    .pod-cover img{width:100%;height:100%;object-fit:cover}
    .pod-info{padding:14px}
    .pod-title{font-size:15px;font-weight:700;color:var(--text);margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .pod-host{font-size:13px;color:var(--text2);margin-bottom:8px}
    .pod-footer{display:flex;align-items:center;justify-content:space-between}
    .pod-dur{font-size:12px;color:var(--text3)}
    .pod-plays{font-size:12px;color:var(--text3)}
    /* Upload form */
    .upload-zone{background:var(--bg2);border:2px dashed var(--border2);border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:var(--transition)}
    .upload-zone:hover,.upload-zone.drag{border-color:var(--purple);background:var(--purple-l)}
    .upload-icon{font-size:40px;margin-bottom:10px}
    .upload-label{font-size:15px;font-weight:600;color:var(--text);margin-bottom:4px}
    .upload-sub{font-size:13px;color:var(--text2)}
    .limit-badge{display:inline-flex;align-items:center;gap:6px;background:var(--purple-l);border:1px solid var(--purple);color:var(--purple);border-radius:20px;padding:6px 14px;font-size:13px;font-weight:600}
    .player-bar{background:var(--card);border-top:1px solid var(--border);padding:12px 16px;display:flex;align-items:center;gap:14px;position:fixed;bottom:var(--bottom-h);left:0;right:0;z-index:200;transform:translateY(100%);transition:.3s}
    .player-bar.visible{transform:translateY(0)}
    .theme-toggle { width: 38px; height: 20px; background: var(--bg3); border-radius: 10px; position: relative; cursor: pointer; border: 1px solid var(--border2); flex-shrink: 0; }
    .theme-toggle-knob { position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--purple); transition: left .2s; }
    body.theme-light .theme-toggle-knob { left: 20px; background: var(--warning); }
  </style>
</head>
<body class="theme-<?= clean($theme) ?>">

<nav class="sk-topnav">
  <a href="/dashboard/feed.php" class="sk-logo"><?= $appName ?><span class="dot">.</span></a>
  <div style="position:absolute;left:50%;transform:translateX(-50%);font-size:17px;font-weight:700;color:var(--text)">&#127911; Podcasts</div>
  <div class="sk-nav-actions">
    <button class="btn btn-primary btn-sm" style="border-radius:999px" onclick="Modal.open('upload-modal')">+ Upload</button>
    <a href="/dashboard/notifications.php" class="sk-nav-btn" title="Notifications">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </a>
    <a href="/dashboard/profile.php" style="flex-shrink:0;text-decoration:none">
      <div class="avatar avatar-sm"><?php if(!empty($user['avatar'])): ?><img src="<?= clean($user['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?></div>
    </a>
  </div>
</nav>

<div class="pod-page">

  <!-- PLAN LIMIT BANNER -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <div>
      <div style="font-size:22px;font-weight:800;color:var(--text)">Podcast Hub</div>
      <div style="font-size:14px;color:var(--text2)">Share long-form audio content with the world</div>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <div class="limit-badge">
        🎧 <?= formatDuration($podLimit) ?> limit
        <?php if (($myPlan['slug']??'free') !== 'free'): ?> · <?= clean($myPlan['name']) ?><?php endif; ?>
      </div>
      <?php if (($myPlan['slug']??'free') === 'free'): ?>
        <a href="/dashboard/premium.php" class="btn btn-secondary btn-sm" style="border-radius:999px">⭐ Upgrade</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- TABS -->
  <div class="sk-tabs" style="border-radius:12px 12px 0 0;overflow:hidden;border:1px solid var(--border)">
    <div class="sk-tab <?= $tab==='browse'?'active':'' ?>" onclick="location.href='?tab=browse'">🌐 Browse All</div>
    <div class="sk-tab <?= $tab==='mine'?'active':'' ?>" onclick="location.href='?tab=mine'">🎙 My Podcasts</div>
  </div>

  <!-- PODCAST GRID -->
  <div class="pod-grid">
    <?php if (empty($podcasts)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:60px 20px">
        <div style="font-size:48px;margin-bottom:12px">🎧</div>
        <div style="font-size:20px;font-weight:700;color:var(--text);margin-bottom:8px"><?= $tab==='mine'?'No podcasts yet':'No podcasts published yet' ?></div>
        <p style="color:var(--text2);font-size:14px">Be the first to share a podcast!</p>
        <button class="btn btn-primary" style="margin-top:16px;border-radius:999px" onclick="Modal.open('upload-modal')">Upload Podcast</button>
      </div>
    <?php else: ?>
      <?php foreach ($podcasts as $pod): ?>
      <div class="pod-card" onclick="playPodcast(<?= $pod['id'] ?>,'<?= clean($pod['audio_url']) ?>','<?= clean($pod['title']) ?>','<?= clean($pod['username']) ?>','<?= clean($pod['cover_url']??'') ?>')">
        <div class="pod-cover">
          <?php if ($pod['cover_url']): ?>
            <img src="<?= clean($pod['cover_url']) ?>" alt="<?= clean($pod['title']) ?>" loading="lazy"/>
          <?php else: ?>
            🎧
          <?php endif; ?>
        </div>
        <div class="pod-info">
          <div class="pod-title" title="<?= clean($pod['title']) ?>"><?= clean($pod['title']) ?></div>
          <div class="pod-host">
            <a href="/dashboard/profile.php?u=<?= urlencode($pod['username']) ?>" onclick="event.stopPropagation()" style="color:var(--text2);text-decoration:none">@<?= clean($pod['username']) ?></a>
            · <span class="badge badge-muted" style="font-size:10px"><?= ucfirst($pod['category']??'general') ?></span>
          </div>
          <?php if ($pod['description']): ?>
            <div style="font-size:12px;color:var(--text3);margin-bottom:8px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical"><?= clean($pod['description']) ?></div>
          <?php endif; ?>
          <div class="pod-footer">
            <span class="pod-dur">⏱ <?= fmtDur((int)$pod['duration']) ?></span>
            <span class="pod-plays">▶ <?= number_format((int)$pod['play_count']) ?> plays</span>
            <?php if ($pod['user_id'] == $userId): ?>
              <button class="btn btn-danger" style="padding:3px 10px;font-size:11px;border-radius:20px" onclick="event.stopPropagation();deletePodcast(<?= $pod['id'] ?>)">✕</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- PAGINATION -->
  <?php if ($totalPages > 1): ?>
  <div style="display:flex;justify-content:center;gap:8px;margin-top:24px">
    <?php for ($p=1;$p<=$totalPages;$p++): ?>
      <a href="?tab=<?=$tab?>&page=<?=$p?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary' ?>" style="border-radius:999px"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</div>

<!-- PERSISTENT PLAYER BAR -->
<div class="player-bar" id="podPlayerBar">
  <div class="podcast-cover" id="podThumb" style="width:44px;height:44px;flex-shrink:0;font-size:20px;display:flex;align-items:center;justify-content:center">🎧</div>
  <div style="flex:1;min-width:0">
    <div id="podTitle" style="font-size:14px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
    <div id="podHost" style="font-size:12px;color:var(--text2)"></div>
    <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
      <span id="podCurrent" style="font-size:11px;color:var(--text3);white-space:nowrap">0:00</span>
      <div class="podcast-progress" id="podProgress" onclick="seekPodcast(event)"><div class="podcast-progress-fill" id="podFill"></div></div>
      <span id="podTotal" style="font-size:11px;color:var(--text3);white-space:nowrap">0:00</span>
    </div>
  </div>
  <button id="podPlayBtn" onclick="togglePodcast()" style="width:44px;height:44px;border-radius:50%;background:var(--purple);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0">
    <svg id="podPlayIcon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
  </button>
  <button onclick="closePodcastPlayer()" style="background:none;border:none;color:var(--text3);cursor:pointer;padding:4px;font-size:18px">✕</button>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="/dashboard/feed.php"    class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>Home</a>
  <a href="/dashboard/status.php"  class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/></svg>Status</a>
  <a href="#" onclick="Modal.open('upload-modal');return false" class="bottom-nav-item"><div class="bottom-nav-create"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div></a>
  <a href="/dashboard/wallet.php"  class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Wallet</a>
  <a href="/dashboard/podcast.php" class="bottom-nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>Podcast</a>
</nav>

<!-- UPLOAD MODAL -->
<div class="modal-overlay" id="upload-modal">
  <div class="modal" style="max-width:520px">
    <div class="modal-handle"></div>
    <div class="modal-title">🎧 Upload Podcast Episode</div>
    <div class="limit-badge" style="margin-bottom:16px">Max length: <?= formatDuration($podLimit) ?><?php if(($myPlan['slug']??'free')==='free'): ?> — <a href="/dashboard/premium.php" style="color:var(--purple)">Upgrade for more</a><?php endif; ?></div>

    <form id="podcastForm">
      <!-- AUDIO FILE -->
      <div class="input-group mb-3">
        <label class="input-label">Audio File *</label>
        <label class="upload-zone" id="audioZone" for="audioFileInput">
          <div class="upload-icon">🎵</div>
          <div class="upload-label" id="audioFileName">Tap to choose MP3 / WAV / M4A</div>
          <div class="upload-sub">Max <?= formatDuration($podLimit) ?> · Up to 200MB</div>
          <input type="file" id="audioFileInput" accept="audio/mpeg,audio/mp4,audio/wav,audio/ogg,audio/webm" style="display:none" required/>
        </label>
        <div id="audioDurationInfo" style="font-size:12px;color:var(--text3);margin-top:4px"></div>
      </div>

      <!-- COVER IMAGE -->
      <div class="input-group mb-3">
        <label class="input-label">Cover Art (optional)</label>
        <label class="upload-zone" id="coverZone" for="coverFileInput" style="padding:16px">
          <div id="coverPreviewWrap" class="hidden" style="width:80px;height:80px;margin:0 auto 8px;border-radius:12px;overflow:hidden">
            <img id="coverPreview" style="width:100%;height:100%;object-fit:cover"/>
          </div>
          <div id="coverFileName" style="font-size:13px;color:var(--text2)">Upload square image (1:1)</div>
          <input type="file" id="coverFileInput" accept="image/jpeg,image/png,image/webp" style="display:none"/>
        </label>
      </div>

      <div class="input-group mb-3">
        <label class="input-label">Episode Title *</label>
        <input class="input" type="text" id="podTitle" placeholder="e.g. Ep. 1 — Starting Your Journey" maxlength="200" required/>
      </div>

      <div class="input-group mb-3">
        <label class="input-label">Description</label>
        <textarea class="input" id="podDescription" rows="3" placeholder="What is this episode about?" maxlength="1000"></textarea>
      </div>

      <div class="input-group mb-3">
        <label class="input-label">Category</label>
        <select class="input" id="podCategory">
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="uploadProgressWrap" class="hidden" style="margin-bottom:12px">
        <div style="height:4px;background:var(--bg3);border-radius:4px;overflow:hidden">
          <div id="uploadProgressBar" style="height:100%;background:var(--purple);border-radius:4px;width:0%;transition:width .3s"></div>
        </div>
        <div id="uploadProgressText" style="font-size:12px;color:var(--text3);text-align:center;margin-top:4px">Uploading…</div>
      </div>

      <button type="submit" class="btn btn-primary w-full" id="submitPodcast" style="border-radius:999px">
        🚀 Publish Episode
      </button>
    </form>
  </div>
</div>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
const POD_LIMIT_SECS = <?= $podLimit === 0 ? 999999 : $podLimit ?>;
let podAudio = null;
let podPlaying = false;

/* ── FILE PICKERS ───────────────────────────── */
document.getElementById('audioZone').addEventListener('click', () => document.getElementById('audioFileInput').click());
document.getElementById('coverZone').addEventListener('click', () => document.getElementById('coverFileInput').click());

document.getElementById('audioFileInput').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  document.getElementById('audioFileName').textContent = file.name;
  // Check duration
  const url = URL.createObjectURL(file);
  const tmp = new Audio(url);
  tmp.addEventListener('loadedmetadata', () => {
    URL.revokeObjectURL(url);
    const dur = Math.floor(tmp.duration);
    const info = document.getElementById('audioDurationInfo');
    if (POD_LIMIT_SECS < 999999 && dur > POD_LIMIT_SECS) {
      info.style.color = 'var(--danger)';
      info.textContent = `⚠ Duration ${fmtDur(dur)} exceeds your plan limit (${fmtDur(POD_LIMIT_SECS)}). Upgrade to upload longer episodes.`;
    } else {
      info.style.color = 'var(--green)';
      info.textContent = `✓ Duration: ${fmtDur(dur)} — within your limit`;
    }
  });
});

document.getElementById('coverFileInput').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  document.getElementById('coverFileName').textContent = file.name;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('coverPreview').src = e.target.result;
    document.getElementById('coverPreviewWrap').classList.remove('hidden');
  };
  reader.readAsDataURL(file);
});

function fmtDur(s) {
  if (!s || s >= 999999) return 'Unlimited';
  const m = Math.floor(s/60), sec = s%60;
  return m + ':' + String(sec).padStart(2,'0');
}

/* ── UPLOAD FORM ────────────────────────────── */
document.getElementById('podcastForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const audioFile = document.getElementById('audioFileInput').files[0];
  const coverFile = document.getElementById('coverFileInput').files[0];
  const title     = document.getElementById('podTitle').value.trim();
  const desc      = document.getElementById('podDescription').value.trim();
  const category  = document.getElementById('podCategory').value;

  if (!audioFile) { Toast.error('Please select an audio file'); return; }
  if (!title)     { Toast.error('Please add a title'); return; }

  // Duration check
  const tmpAudio = new Audio(URL.createObjectURL(audioFile));
  await new Promise(res => { tmpAudio.addEventListener('loadedmetadata', res); tmpAudio.addEventListener('error', res); });
  const dur = Math.floor(tmpAudio.duration || 0);
  if (POD_LIMIT_SECS < 999999 && dur > POD_LIMIT_SECS) {
    Toast.error(`Episode too long for your plan. Max: ${fmtDur(POD_LIMIT_SECS)}`);
    return;
  }

  const btn = document.getElementById('submitPodcast');
  setLoading(btn, true);
  document.getElementById('uploadProgressWrap').classList.remove('hidden');

  const fd = new FormData();
  fd.append('audio', audioFile, audioFile.name);
  if (coverFile) fd.append('cover', coverFile, coverFile.name);
  fd.append('title', title);
  fd.append('description', desc);
  fd.append('category', category);
  fd.append('duration', String(dur));

  try {
    const xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', ev => {
      if (ev.lengthComputable) {
        const pct = Math.round(ev.loaded / ev.total * 100);
        document.getElementById('uploadProgressBar').style.width = pct + '%';
        document.getElementById('uploadProgressText').textContent = `Uploading… ${pct}%`;
      }
    });
    xhr.addEventListener('load', () => {
      setLoading(btn, false);
      try {
        const res = JSON.parse(xhr.responseText);
        if (res.success) {
          Toast.success('Podcast published!');
          Modal.close('upload-modal');
          setTimeout(() => location.reload(), 800);
        } else {
          Toast.error(res.message || 'Upload failed');
        }
      } catch { Toast.error('Upload failed'); }
    });
    xhr.addEventListener('error', () => { setLoading(btn, false); Toast.error('Network error'); });
    xhr.open('POST', '/api/v1/podcast/create');
    xhr.setRequestHeader('X-CSRF-Token', getCsrfToken() || '');
    xhr.send(fd);
  } catch (err) {
    setLoading(btn, false);
    Toast.error('Upload failed: ' + err.message);
  }
});

/* ── PLAYER ─────────────────────────────────── */
function playPodcast(id, url, title, host, cover) {
  if (podAudio) { podAudio.pause(); }
  podAudio = new Audio(url);
  document.getElementById('podTitle').textContent = title;
  document.getElementById('podHost').textContent  = '@' + host;
  const thumb = document.getElementById('podThumb');
  if (cover) { thumb.innerHTML = `<img src="${cover}" style="width:100%;height:100%;object-fit:cover;border-radius:8px"/>`; }
  else { thumb.textContent = '🎧'; }
  podAudio.addEventListener('loadedmetadata', () => {
    document.getElementById('podTotal').textContent = fmtDur(Math.floor(podAudio.duration));
  });
  podAudio.addEventListener('timeupdate', () => {
    const pct = podAudio.currentTime / podAudio.duration * 100 || 0;
    document.getElementById('podFill').style.width = pct + '%';
    document.getElementById('podCurrent').textContent = fmtDur(Math.floor(podAudio.currentTime));
  });
  podAudio.addEventListener('ended', () => { updatePlayIcon(false); });
  podAudio.play();
  updatePlayIcon(true);
  document.getElementById('podPlayerBar').classList.add('visible');
  // Track play
  fetch('/api/v1/podcast/' + id + '/play', { method:'POST', credentials:'same-origin', headers:{'X-CSRF-Token':getCsrfToken()||''} });
}

function togglePodcast() {
  if (!podAudio) return;
  if (podPlaying) { podAudio.pause(); updatePlayIcon(false); }
  else            { podAudio.play();  updatePlayIcon(true);  }
}
function updatePlayIcon(playing) {
  podPlaying = playing;
  document.getElementById('podPlayIcon').innerHTML = playing
    ? '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>'
    : '<polygon points="5 3 19 12 5 21 5 3"/>';
}
function seekPodcast(e) {
  if (!podAudio || !podAudio.duration) return;
  const bar = document.getElementById('podProgress');
  podAudio.currentTime = (e.offsetX / bar.offsetWidth) * podAudio.duration;
}
function closePodcastPlayer() {
  if (podAudio) { podAudio.pause(); podAudio = null; }
  document.getElementById('podPlayerBar').classList.remove('visible');
}

async function deletePodcast(id) {
  if (!confirm('Delete this podcast episode?')) return;
  const res = await API.del('/podcast/' + id);
  if (res?.success) { Toast.success('Deleted'); setTimeout(() => location.reload(), 500); }
}

<?php if ($showCreate): ?>
document.addEventListener('DOMContentLoaded', () => Modal.open('upload-modal'));
<?php endif; ?>
</script>
</body>
</html>
