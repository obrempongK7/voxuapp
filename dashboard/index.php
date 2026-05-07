<?php
// @author  Jcode | ObrempongK
// dashboard/index.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAuth();
// Redirect to the new X-style feed
if (!isset($_GET['legacy'])) {
    header('Location: /dashboard/feed.php');
    exit;
}

$user     = auth();
$wallet   = getUserWallet((int)$user['id']);
$myPlan   = getUserPlan((int)$user['id']);
$settings = getPlatformSettings();
$page     = max(1, (int)($_GET['page'] ?? 1));
$channel  = sanitize($_GET['channel'] ?? '');

// Build feed query
$cond   = "p.status='active'";
$params = [];
if ($channel) {
    $ch = DB::first('SELECT id FROM channels WHERE slug=?', [$channel]);
    if ($ch) { $cond .= ' AND p.channel_id=?'; $params[] = $ch['id']; }
}
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$posts = DB::query(
    "SELECT p.*, p.image_url AS post_image_url, u.username, up.avatar,
            (SELECT COALESCE(SUM(amount),0) FROM energy_transactions WHERE post_id=p.id) AS energy_total,
            (SELECT COUNT(*) FROM replies WHERE post_id=p.id AND status='active') AS reply_count,
            (SELECT 1 FROM energy_transactions WHERE post_id=p.id AND giver_id=? LIMIT 1) AS user_gave_energy
     FROM posts p
     JOIN users u ON u.id=p.user_id
     LEFT JOIN user_profiles up ON up.user_id=p.user_id
     WHERE {$cond}
     ORDER BY p.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    array_merge([(int)$user['id']], $params)
);

$channels = DB::query('SELECT * FROM channels WHERE is_active=1 ORDER BY name');
$unreadNotifs = DB::count('notifications','user_id=? AND is_read=0', [(int)$user['id']]);
$theme = getTheme();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta name="csrf-token" content="<?= csrfToken() ?>"/>
  <title>Voice Hub — Voxu</title>
  <link rel="manifest" href="/manifest.json"/>
  <meta name="theme-color" content="#6C3BFF"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
</head>
<body class="<?= clean(themeClass()) ?>">
<!-- TOP NAV -->
<nav class="topnav">
  <a href="/dashboard/" style="font-family:'Poppins',sans-serif;font-size:20px;font-weight:800">Vo<span style="color:var(--purple)">xu</span></a>
  <div class="topnav-right">
    <a href="/dashboard/notifications.php" class="notif-btn">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if ($unreadNotifs > 0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
    <a href="/dashboard/profile.php">
      <div class="avatar avatar-sm" style="background:var(--purple-l);color:var(--purple)"><?= avatarInitials($user['username']) ?></div>
    </a>
  </div>
</nav>

<div class="app-layout">
  <div class="page">
    <!-- BALANCE STRIP -->
    <div class="card card-sm mb-4" style="background:linear-gradient(135deg,#13103a,var(--bg2));border-color:rgba(108,59,255,0.2)">
      <div class="flex items-center justify-between">
        <div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px">
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--text2)">Points Balance</div>
          <?= planBadge($myPlan) ?>
        </div>
          <div style="font-size:22px;font-weight:800;color:#fff"><?= number_format((int)($wallet['points_balance']??0)) ?> <span style="font-size:13px;font-weight:400;color:var(--text2)">pts</span></div>
        </div>
        <div class="flex gap-2">
          <a href="/dashboard/wallet.php" class="btn btn-primary btn-sm">Wallet</a>
          <a href="/dashboard/premium.php" class="btn btn-secondary btn-sm">⭐ Plans</a>
          <button onclick="Modal.open('create-modal')" class="btn btn-secondary btn-sm">+ Post</button>
        </div>
      </div>
    </div>

    <!-- CHANNEL FILTER -->
    <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;margin-bottom:16px;scrollbar-width:none">
      <a href="/dashboard/" class="badge <?= !$channel ? 'badge-purple' : 'badge-muted' ?>" style="padding:6px 14px;white-space:nowrap;font-size:12px">🔥 All</a>
      <?php foreach ($channels as $ch): ?>
        <a href="?channel=<?= urlencode($ch['slug']) ?>" class="badge <?= $channel===$ch['slug'] ? 'badge-purple' : 'badge-muted' ?>" style="padding:6px 14px;white-space:nowrap;font-size:12px">
          <?= clean($ch['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- VOICE FEED -->
    <div class="feed" id="voice-feed">
      <?php if (empty($posts)): ?>
        <div class="empty">
          <div class="empty-icon">🎙</div>
          <div class="empty-title">No voices yet</div>
          <p class="empty-text">Be the first to speak in this channel.</p>
          <button onclick="Modal.open('create-modal')" class="btn btn-primary">Record Now</button>
        </div>
      <?php else: ?>
        <?php foreach ($posts as $post): ?>
          <div class="voice-card animate-in" data-voice-player data-src="<?= clean($post['audio_url']??'') ?>" data-post-id="<?= $post['id'] ?>">
            <div class="voice-card-header">
              <a href="/dashboard/profile.php?u=<?= urlencode($post['username']) ?>">
                <div class="avatar">
                  <?php if ($post['avatar']): ?>
                    <img src="<?= clean($post['avatar']) ?>" alt="<?= clean($post['username']) ?>" loading="lazy"/>
                  <?php else: ?>
                    <?= avatarInitials($post['username']) ?>
                  <?php endif; ?>
                </div>
              </a>
              <div style="flex:1;min-width:0">
                <div class="flex items-center gap-2">
                  <a href="/dashboard/profile.php?u=<?= urlencode($post['username']) ?>" style="font-size:14px;font-weight:600;color:#fff"><?= clean($post['username']) ?></a>
                  <?php if ($post['user_gave_energy']): ?><span class="badge badge-purple">⚡ Energized</span><?php endif; ?>
                </div>
                <div class="voice-meta"><?= timeAgo($post['created_at']) ?><?= $post['channel_id'] ? ' · ' . clean($post['channel_id']) : '' ?></div>
              </div>
              <?php if ($post['user_id'] == $user['id']): ?>
              <div class="dropdown" id="drop-<?= $post['id'] ?>">
                <button class="btn btn-ghost btn-icon-sm" onclick="document.getElementById('drop-<?= $post['id'] ?>').classList.toggle('open')">⋯</button>
                <div class="dropdown-menu">
                  <div class="dropdown-item danger" onclick="deletePost(<?= $post['id'] ?>)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg> Delete
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>
            <div class="voice-card-body">
              <div class="voice-title"><?= clean($post['title']) ?></div>
              <?php if (!empty($post['image_url']) || !empty($post['post_image_url'])): ?>
              <div class="voice-post-image" style="margin:8px 0;border-radius:10px;overflow:hidden;max-height:260px">
                <img src="<?= clean($post['image_url'] ?? $post['post_image_url'] ?? '') ?>" 
                     alt="<?= clean($post['title']) ?>" 
                     loading="lazy"
                     style="width:100%;object-fit:cover;border-radius:10px;cursor:pointer"
                     onclick="openImageViewer('<?= clean($post['image_url'] ?? $post['post_image_url'] ?? '') ?>')"
                />
              </div>
              <?php endif; ?>
              <!-- WAVEFORM -->
              <div class="player-controls">
                <button class="play-btn" aria-label="Play">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </button>
                <div class="waveform" style="flex:1">
                  <?php for($i=0;$i<36;$i++): $h=rand(15,85); ?>
                    <div class="waveform-bar" style="height:<?=$h?>%"></div>
                  <?php endfor; ?>
                </div>
                <span class="player-time"><?= gmdate('i:s', (int)($post['duration']??0)) ?></span>
              </div>
              <!-- ACTIONS -->
              <div class="voice-actions">
                <button class="action-btn" onclick="Energy.send(<?= $post['id'] ?>)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                  <span class="energy-display">⚡ <?= number_format((int)($post['energy_total']??0)) ?></span>
                </button>
                <button class="action-btn" onclick="openReplies(<?= $post['id'] ?>)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                  <?= number_format((int)($post['reply_count']??0)) ?>
                </button>
                <button class="action-btn" onclick="copyText('<?= APP_URL ?>/post/<?= $post['id'] ?>','Link copied!')">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                  Share
                </button>
                <button class="action-btn" onclick="sendTip(<?= $post['id'] ?>, '<?= clean($post['username']) ?>')">
                  💸 Tip
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if (count($posts) === $perPage): ?>
    <div class="load-more" id="load-more">
      <button class="btn btn-secondary btn-sm" onclick="loadMorePosts(2)">Load More</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="/dashboard/" class="bottom-nav-item active">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
    Voice
  </a>
  <a href="/dashboard/status.php" class="bottom-nav-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
    Status
  </a>
  <a href="#" class="bottom-nav-item" onclick="Modal.open('create-modal');return false">
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

<!-- CREATE MODAL -->
<div class="modal-overlay" id="create-modal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title">Create Content</div>
    <div class="create-options">
      <div class="create-option" onclick="Modal.close('create-modal');Modal.open('voice-modal')">
        <div class="create-option-icon">🎙</div>
        <div class="create-option-title">Voice Post</div>
        <div class="create-option-desc">Record your voice opinion</div>
      </div>
      <div class="create-option" onclick="Modal.close('create-modal');window.location='/dashboard/status.php?create=1'">
        <div class="create-option-icon">✨</div>
        <div class="create-option-title">Status</div>
        <div class="create-option-desc">Post a status and earn</div>
      </div>
    </div>
  </div>
</div>

<!-- VOICE RECORD MODAL -->
<div class="modal-overlay" id="voice-modal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title">New Voice Post</div>
    <form id="voiceForm">
      <div class="input-group mb-3">
        <label class="input-label">Title / Topic</label>
        <input class="input" type="text" id="voiceTitle" placeholder="What's on your mind?" maxlength="200" required/>
      </div>
      <div class="input-group mb-3">
        <label class="input-label">Channel</label>
        <select class="input" id="voiceChannel">
          <option value="">No channel</option>
          <?php foreach ($channels as $ch): ?>
            <option value="<?= $ch['id'] ?>"><?= clean($ch['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- OPTIONAL COVER IMAGE -->
      <div class="input-group mb-3">
        <label class="input-label" style="display:flex;align-items:center;gap:6px">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          Cover Image <span style="color:var(--text3);font-weight:400">(optional)</span>
        </label>
        <label style="display:flex;align-items:center;gap:10px;background:var(--bg2);border:1px dashed var(--border2);border-radius:8px;padding:10px 14px;cursor:pointer;transition:.2s" id="imageDropZone">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <span id="imageLabel" style="font-size:13px;color:var(--text2)">Upload an image to go with your voice</span>
          <input type="file" id="coverImageInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none"/>
        </label>
        <div id="imagePreviewWrap" class="hidden" style="margin-top:8px;position:relative">
          <img id="voiceImagePreview" style="width:100%;max-height:180px;object-fit:cover;border-radius:8px"/>
          <button type="button" onclick="clearCoverImage()" style="position:absolute;top:6px;right:6px;width:26px;height:26px;background:rgba(0,0,0,.6);border:none;border-radius:50%;color:#fff;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center">✕</button>
        </div>
      </div>

      <div class="recorder-card mb-3">
        <div class="recorder-circle" id="recCircle">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:36px;height:36px;color:var(--purple)"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
        </div>
        <div class="recording-time" id="recTime">0:00</div>
        <div class="recording-label" id="recLabel">Tap to record your voice</div>
        <div class="waveform mt-3" id="recWave" style="height:36px"></div>
      </div>
      <button type="submit" class="btn btn-primary w-full" id="submitVoice" disabled>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Post Voice
      </button>
    </form>
  </div>
</div>

<!-- REPLIES MODAL -->
<div class="modal-overlay" id="replies-modal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title">Replies</div>
    <div id="replies-list" style="min-height:80px"></div>
    <div class="divider"></div>
    <div class="flex gap-2 mt-3">
      <input class="input" id="replyText" placeholder="Type a reply..." style="flex:1"/>
      <button class="btn btn-primary btn-sm" onclick="submitReply()">Send</button>
    </div>
  </div>
</div>

<!-- TIP MODAL -->
<div class="modal-overlay" id="tip-modal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title" id="tipTitle">Send Tip</div>
    <input type="hidden" id="tipPostId"/>
    <p class="text-muted mb-3" style="font-size:14px">Send points as a tip to this creator</p>
    <div class="flex gap-2 mb-3" style="flex-wrap:wrap">
      <?php foreach ([10,25,50,100] as $amt): ?>
        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('tipAmount').value=<?=$amt?>"><?=$amt?> pts</button>
      <?php endforeach; ?>
    </div>
    <input class="input mb-3" type="number" id="tipAmount" placeholder="Custom amount" min="1"/>
    <button class="btn btn-primary w-full" onclick="confirmTip()">Send Tip</button>
  </div>
</div>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
// Set recording limit from user plan
window.VOXU_MAX_RECORD_SECS = <?= getUserRecordingLimit((int)$user['id']) === 0 ? 99999 : getUserRecordingLimit((int)$user['id']) ?>;

// Init voice recorder
VoiceRecorder.init('recCircle','recTime','recWave','submitVoice');

// Cover image handling
const coverImageInput = document.getElementById('coverImageInput');
const imageDropZone   = document.getElementById('imageDropZone');
let coverImageFile    = null;

if (imageDropZone) {
  imageDropZone.addEventListener('click', () => coverImageInput.click());
  // Drag-and-drop
  imageDropZone.addEventListener('dragover',  e => { e.preventDefault(); imageDropZone.style.borderColor = 'var(--purple)'; });
  imageDropZone.addEventListener('dragleave', e => { imageDropZone.style.borderColor = ''; });
  imageDropZone.addEventListener('drop', e => {
    e.preventDefault(); imageDropZone.style.borderColor = '';
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) handleCoverImage(file);
  });
}
if (coverImageInput) {
  coverImageInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if (file) handleCoverImage(file);
  });
}
function handleCoverImage(file) {
  coverImageFile = file;
  const reader = new FileReader();
  reader.onload = ev => {
    document.getElementById('voiceImagePreview').src = ev.target.result;
    document.getElementById('imagePreviewWrap').classList.remove('hidden');
    document.getElementById('imageLabel').textContent = file.name;
  };
  reader.readAsDataURL(file);
}
function clearCoverImage() {
  coverImageFile = null;
  coverImageInput.value = '';
  document.getElementById('imagePreviewWrap').classList.add('hidden');
  document.getElementById('imageLabel').textContent = 'Upload an image to go with your voice';
}

// Image lightbox
function openImageViewer(src) {
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:16px';
  overlay.innerHTML = `<img src="${src}" style="max-width:100%;max-height:90vh;border-radius:10px;object-fit:contain"/>`;
  overlay.addEventListener('click', () => overlay.remove());
  document.body.appendChild(overlay);
}

// Submit voice post
document.getElementById('voiceForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('submitVoice');
  const title = document.getElementById('voiceTitle').value.trim();
  const channel = document.getElementById('voiceChannel').value;
  if (!title) { Toast.error('Please add a title.'); return; }
  setLoading(btn, true);
  // Pass cover image file if present
  const res = await VoiceRecorder.upload(title, { channel_id: channel }, null, coverImageFile);
  setLoading(btn, false);
  if (res?.success) {
    Toast.success('Voice posted!');
    Modal.close('voice-modal');
    setTimeout(() => location.reload(), 800);
  }
});

// Delete post
async function deletePost(id) {
  if (!confirm('Delete this post?')) return;
  await API.del('/posts/' + id);
  document.querySelector(`[data-post-id="${id}"]`)?.remove();
}

// Open replies
let currentReplyPost = null;
async function openReplies(postId) {
  currentReplyPost = postId;
  Modal.open('replies-modal');
  document.getElementById('replies-list').innerHTML = '<div class="text-center text-muted p-4">Loading…</div>';
  const data = await API.get('/posts/' + postId + '/replies');
  const list = document.getElementById('replies-list');
  if (!data.replies?.length) { list.innerHTML = '<div class="text-center text-muted p-4">No replies yet</div>'; return; }
  list.innerHTML = data.replies.map(r => `
    <div class="flex gap-3 p-3" style="border-bottom:1px solid var(--border)">
      <div class="avatar avatar-sm">${r.username.substring(0,2).toUpperCase()}</div>
      <div>
        <div style="font-size:13px;font-weight:600;color:#fff">${r.username}</div>
        <div style="font-size:13px;color:var(--text2);margin-top:2px">${r.text || '[Voice Reply]'}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px">${r.created_at}</div>
      </div>
    </div>`).join('');
}

async function submitReply() {
  const text = document.getElementById('replyText').value.trim();
  if (!text || !currentReplyPost) return;
  await API.post('/posts/' + currentReplyPost + '/reply', { text });
  document.getElementById('replyText').value = '';
  Toast.success('Reply sent!');
  await openReplies(currentReplyPost);
}

// Tip
function sendTip(postId, username) {
  document.getElementById('tipPostId').value = postId;
  document.getElementById('tipTitle').textContent = 'Tip @' + username;
  Modal.open('tip-modal');
}
async function confirmTip() {
  const amt = parseInt(document.getElementById('tipAmount').value);
  const postId = document.getElementById('tipPostId').value;
  if (!amt || amt < 1) { Toast.error('Enter a tip amount'); return; }
  const res = await API.post('/tips/send', { post_id: postId, amount: amt });
  if (res?.success) { Toast.success('Tip sent!'); Modal.close('tip-modal'); }
}

// Load more
let feedPage = 1;
async function loadMorePosts(pg) {
  feedPage = pg;
  const btn = document.querySelector('#load-more button');
  if (btn) btn.textContent = 'Loading…';
  const url = `/api/v1/voice/feed?page=${pg}<?= $channel ? '&channel='.urlencode($channel) : '' ?>`;
  const data = await API.get(url.replace('/api/v1',''));
  if (!data.posts?.length) {
    document.getElementById('load-more').innerHTML = '<p class="text-muted text-sm">No more posts</p>';
    return;
  }
  // Append posts (simplified - full render via server)
  if (data.has_more) {
    if (btn) { btn.textContent = 'Load More'; btn.onclick = () => loadMorePosts(pg + 1); }
  } else {
    document.getElementById('load-more').innerHTML = '<p class="text-muted text-sm">You\'re all caught up</p>';
  }
}
</script>
</body>
</html>
