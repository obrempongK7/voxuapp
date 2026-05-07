<?php
/**
 * Voxu — Public Post View
 * URL: /post/{id}  or  /@{username}/{id}
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$postId   = (int)($_GET['id'] ?? 0);
$settings = getPlatformSettings();
$appName  = $settings['app_name'] ?? 'Voxu';
$me       = auth();

if (!$postId) {
    http_response_code(404);
    die('Post not found.');
}

$post = DB::first(
    "SELECT p.*, u.username, up.avatar, up.bio,
            (SELECT COALESCE(SUM(amount),0) FROM energy_transactions WHERE post_id=p.id) AS total_energy,
            (SELECT COUNT(*) FROM replies WHERE post_id=p.id AND status='active') AS reply_count,
            (SELECT COUNT(*) FROM followers WHERE following_id=p.user_id) AS follower_count
     FROM posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN user_profiles up ON up.user_id = p.user_id
     WHERE p.id = ? AND p.status = 'active'",
    [$postId]
);

if (!$post) {
    http_response_code(404);
    die('Post not found or has been removed.');
}

// Log user audit if viewing while logged in (but not own post)
if ($me && $me['id'] != $post['user_id']) {
    DB::insert('users_audit_logs', [
        'user_id'     => (int)$me['id'],
        'action'      => 'post_view',
        'description' => "Viewed post #{$postId}",
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
}

// Track play count
DB::exec('UPDATE posts SET play_count = play_count + 1 WHERE id = ?', [$postId]);

// Replies
$replies = DB::query(
    "SELECT r.*, u.username, up.avatar
     FROM replies r
     JOIN users u ON u.id = r.user_id
     LEFT JOIN user_profiles up ON up.user_id = r.user_id
     WHERE r.post_id = ? AND r.status = 'active'
     ORDER BY r.created_at ASC",
    [$postId]
);

// Is current user following the post author?
$isFollowing = $me && DB::count('followers','follower_id=? AND following_id=?',[(int)$me['id'],(int)$post['user_id']]) > 0;
$userGaveEnergy = $me && DB::count('energy_transactions','post_id=? AND giver_id=?',[$postId,(int)$me['id']]) > 0;

$pageTitle = clean($post['username']) . ': ' . clean($post['title']) . ' — ' . $appName;
$ogUrl     = APP_URL . '/post/' . $postId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?= $pageTitle ?></title>
  <meta name="description" content="Listen to @<?= clean($post['username']) ?>'s voice on Voxu: <?= clean(substr($post['title'],0,120)) ?>"/>
  <!-- Open Graph -->
  <meta property="og:title"       content="<?= $pageTitle ?>"/>
  <meta property="og:description" content="Listen on Voxu — Speak. Be Seen. Earn."/>
  <meta property="og:url"         content="<?= $ogUrl ?>"/>
  <meta property="og:type"        content="article"/>
  <meta name="twitter:card"       content="summary"/>
  <meta name="twitter:title"      content="<?= $pageTitle ?>"/>
  <link rel="canonical"           href="<?= $ogUrl ?>"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
  <style>
    .post-page { max-width: 660px; margin: 0 auto; padding: 80px 16px 100px; }
    .back-btn  { display:inline-flex;align-items:center;gap:6px;color:var(--text2);font-size:13px;margin-bottom:24px;text-decoration:none;transition:.2s; }
    .back-btn:hover { color: #fff; }
    .post-card { background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:20px; }
    .post-header { padding:20px 20px 0; }
    .post-body   { padding:16px 20px 20px; }
    .post-title  { font-size:20px;font-weight:700;color:#fff;line-height:1.35;margin-bottom:16px; }
    .waveform    { display:flex;align-items:center;gap:2px;height:52px;cursor:pointer;padding:4px 0; }
    .waveform-bar{ flex:1;background:var(--bg3);border-radius:2px;min-height:4px;transition:.08s ease; }
    .waveform-bar.active { background:var(--purple); }
    .waveform-bar.played { background:var(--purple-d); }
    .player-row  { display:flex;align-items:center;gap:12px;margin-top:12px; }
    .play-btn    { width:48px;height:48px;background:var(--purple);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;border:none;cursor:pointer;transition:.2s;flex-shrink:0; }
    .play-btn:hover { background:var(--purple-d);transform:scale(1.05);box-shadow:var(--glow-p); }
    .progress-bar{ flex:1;height:5px;background:var(--bg3);border-radius:4px;cursor:pointer;position:relative; }
    .progress-fill{ height:100%;background:var(--purple);border-radius:4px;transition:width .1s linear; }
    .player-time { font-size:12px;color:var(--text2);white-space:nowrap;font-variant-numeric:tabular-nums; }
    .post-actions{ display:flex;align-items:center;gap:6px;padding:12px 20px;border-top:1px solid var(--border);flex-wrap:wrap; }
    .act-btn     { display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:20px;font-size:13px;font-weight:500;color:var(--text2);background:transparent;border:1px solid var(--border);cursor:pointer;transition:.2s; }
    .act-btn:hover { background:var(--bg2);color:#fff;border-color:var(--border2); }
    .act-btn.active { color:var(--purple);border-color:var(--purple);background:var(--purple-l); }
    /* Replies */
    .replies-section { margin-bottom:20px; }
    .reply-card { background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-bottom:10px;transition:.2s; }
    .reply-card:hover { border-color:rgba(108,59,255,.3); }
    .reply-header { display:flex;align-items:center;gap:10px;margin-bottom:10px; }
    .reply-text  { font-size:14px;color:var(--text);line-height:1.6; }
    /* Voice reply recorder */
    .recorder-section { background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:20px; }
    .rec-title   { font-size:15px;font-weight:600;color:#fff;margin-bottom:14px; }
    .rec-tabs    { display:flex;gap:6px;margin-bottom:14px; }
    .rec-tab     { padding:7px 14px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;color:var(--text2);background:var(--bg2);border:1px solid var(--border);cursor:pointer;transition:.2s; }
    .rec-tab.active { background:var(--purple-l);color:var(--purple);border-color:var(--purple); }
    .rec-circle  { width:72px;height:72px;border-radius:50%;background:var(--purple-l);border:2px solid var(--purple);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s;margin:0 auto 10px; }
    .rec-circle.recording { border-color:var(--danger);background:var(--danger-l);animation:pulse 1.5s ease-in-out infinite; }
    @keyframes pulse { 0%,100%{box-shadow:0 0 0 0 rgba(255,68,68,0.4)} 50%{box-shadow:0 0 0 12px transparent} }
    .rec-time    { font-size:20px;font-weight:800;color:#fff;text-align:center;font-variant-numeric:tabular-nums; }
    .rec-label   { font-size:12px;color:var(--text2);text-align:center;margin-top:4px; }
  </style>
</head>
<body>

<!-- NAV -->
<nav class="topnav">
  <a href="/" style="font-family:'Poppins',sans-serif;font-size:20px;font-weight:800;text-decoration:none;color:#fff">
    Vo<span style="color:var(--purple)">xu</span>
  </a>
  <div class="topnav-right">
    <?php if ($me): ?>
      <a href="/dashboard/" class="btn btn-primary btn-sm">Dashboard</a>
    <?php else: ?>
      <a href="/auth/login.php"    class="btn btn-secondary btn-sm">Log In</a>
      <a href="/auth/register.php" class="btn btn-primary   btn-sm">Join</a>
    <?php endif; ?>
  </div>
</nav>

<div class="post-page">

  <!-- BACK -->
  <a class="back-btn" href="<?= $me ? '/dashboard/' : '/' ?>">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    Back
  </a>

  <!-- MAIN POST CARD -->
  <div class="post-card">
    <div class="post-header">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
        <a href="/dashboard/profile.php?u=<?= urlencode($post['username']) ?>">
          <div class="avatar">
            <?php if ($post['avatar']): ?>
              <img src="<?= clean($post['avatar']) ?>" alt="<?= clean($post['username']) ?>"/>
            <?php else: ?>
              <?= avatarInitials($post['username']) ?>
            <?php endif; ?>
          </div>
        </a>
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <a href="/dashboard/profile.php?u=<?= urlencode($post['username']) ?>" style="font-weight:700;font-size:15px;color:#fff;text-decoration:none">
              @<?= clean($post['username']) ?>
            </a>
            <?php if ($me && $me['id'] != $post['user_id']): ?>
              <button
                id="followBtn"
                onclick="toggleFollow(<?= $post['user_id'] ?>)"
                class="btn btn-sm <?= $isFollowing?'btn-secondary':'btn-primary' ?>"
                style="padding:4px 12px;font-size:11px"
              ><?= $isFollowing ? 'Following' : 'Follow' ?></button>
            <?php endif; ?>
          </div>
          <div style="font-size:12px;color:var(--text2);margin-top:2px">
            <?= timeAgo($post['created_at']) ?>
            · <?= number_format((int)$post['play_count']) ?> plays
          </div>
        </div>
        <!-- Share URL copy -->
        <button onclick="copyText('<?= $ogUrl ?>','Link copied!')" class="btn btn-ghost btn-icon-sm" title="Copy link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        </button>
      </div>
    </div>

    <div class="post-body">
      <div class="post-title"><?= clean($post['title']) ?></div>

      <!-- WAVEFORM PLAYER -->
      <div class="waveform" id="waveform">
        <?php for ($i=0; $i<56; $i++): $h = rand(12,88); ?>
          <div class="waveform-bar" style="height:<?= $h ?>%"></div>
        <?php endfor; ?>
      </div>
      <div class="player-row">
        <button class="play-btn" id="playBtn" onclick="togglePlay()">
          <svg id="playIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        </button>
        <div class="progress-bar" id="progressBar" onclick="seekAudio(event)">
          <div class="progress-fill" id="progressFill" style="width:0%"></div>
        </div>
        <span class="player-time" id="playerTime">
          <?= gmdate('i:s', (int)($post['duration']??0)) ?>
        </span>
      </div>
    </div>

    <!-- ACTIONS -->
    <div class="post-actions">
      <button id="energyBtn" onclick="sendEnergy()" class="act-btn <?= $userGaveEnergy?'active':'' ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        <span id="energyCount"><?= number_format((int)$post['total_energy']) ?></span> Energy
      </button>

      <button onclick="scrollToReplies()" class="act-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <?= number_format((int)$post['reply_count']) ?> Replies
      </button>

      <?php if ($me && $me['id'] != $post['user_id']): ?>
      <button onclick="openTipModal()" class="act-btn">
        💸 Tip
      </button>
      <?php endif; ?>

      <button onclick="copyText('<?= $ogUrl ?>','Link copied!')" class="act-btn" style="margin-left:auto">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        Share
      </button>
    </div>
  </div>

  <!-- REPLY RECORDER -->
  <?php if ($me): ?>
  <div class="recorder-section" id="replySection">
    <div class="rec-title">Leave a Reply</div>
    <div class="rec-tabs">
      <div class="rec-tab active" id="tabVoice" onclick="switchReplyTab('voice')">🎙 Voice Reply</div>
      <div class="rec-tab"        id="tabText"  onclick="switchReplyTab('text')">✏ Text Reply</div>
    </div>

    <!-- VOICE TAB -->
    <div id="voiceReplyPanel">
      <div class="rec-circle" id="recCircle" onclick="VoiceRecorder.toggle()">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:28px;height:28px;color:var(--purple)"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
      </div>
      <div class="rec-time"  id="recTime">0:00</div>
      <div class="rec-label">Tap mic to start · Max 3 min</div>
      <div class="waveform mt-3" id="recWave" style="height:32px;margin:10px 0"></div>
      <button id="submitVoiceReply" class="btn btn-primary w-full" style="margin-top:8px" disabled onclick="submitVoiceReply()">
        Post Voice Reply
      </button>
    </div>

    <!-- TEXT TAB -->
    <div id="textReplyPanel" class="hidden">
      <textarea id="replyText" class="input" rows="3" placeholder="Write your reply…" style="margin-bottom:10px"></textarea>
      <button class="btn btn-primary w-full" onclick="submitTextReply()">Post Text Reply</button>
    </div>
  </div>
  <?php else: ?>
  <div class="card text-center" style="padding:24px;margin-bottom:20px">
    <p style="margin-bottom:12px;font-size:14px">Join Voxu to reply with your voice and earn points</p>
    <a href="/auth/register.php" class="btn btn-primary">Join Free →</a>
  </div>
  <?php endif; ?>

  <!-- REPLIES LIST -->
  <div class="replies-section" id="repliesList">
    <div style="font-size:15px;font-weight:600;color:#fff;margin-bottom:14px">
      Replies (<?= number_format((int)$post['reply_count']) ?>)
    </div>

    <?php if (empty($replies)): ?>
      <div class="empty" style="padding:32px 0">
        <div class="empty-icon">💬</div>
        <div class="empty-title">No replies yet</div>
        <p class="empty-text">Be the first to respond to this voice post.</p>
      </div>
    <?php else: ?>
      <?php foreach ($replies as $r): ?>
      <div class="reply-card">
        <div class="reply-header">
          <a href="/dashboard/profile.php?u=<?= urlencode($r['username']) ?>">
            <div class="avatar avatar-sm">
              <?php if ($r['avatar']): ?>
                <img src="<?= clean($r['avatar']) ?>" alt="<?= clean($r['username']) ?>"/>
              <?php else: ?>
                <?= avatarInitials($r['username']) ?>
              <?php endif; ?>
            </div>
          </a>
          <div>
            <a href="/dashboard/profile.php?u=<?= urlencode($r['username']) ?>" style="font-size:13px;font-weight:600;color:#fff;text-decoration:none">
              @<?= clean($r['username']) ?>
            </a>
            <div style="font-size:11px;color:var(--text3)"><?= timeAgo($r['created_at']) ?></div>
          </div>
        </div>

        <?php if ($r['audio_url']): ?>
          <!-- Voice Reply Player -->
          <div data-voice-player data-src="<?= clean($r['audio_url']) ?>" data-post-id="<?= $r['id'] ?>">
            <div class="player-controls">
              <button class="play-btn" style="width:36px;height:36px" aria-label="Play reply">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              </button>
              <div class="waveform" style="flex:1;height:32px">
                <?php for($i=0;$i<28;$i++): $h=rand(15,85); ?>
                  <div class="waveform-bar" style="height:<?=$h?>%"></div>
                <?php endfor; ?>
              </div>
              <span class="player-time"><?= gmdate('i:s',(int)($r['duration']??0)) ?></span>
            </div>
          </div>
          <?php if ($r['text']): ?><div class="reply-text" style="margin-top:8px"><?= clean($r['text']) ?></div><?php endif; ?>
        <?php elseif ($r['text']): ?>
          <div class="reply-text"><?= clean($r['text']) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div><!-- /post-page -->

<!-- TIP MODAL -->
<?php if ($me && $me['id'] != $post['user_id']): ?>
<div class="modal-overlay" id="tipModal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title">Tip @<?= clean($post['username']) ?></div>
    <p class="text-muted mb-3" style="font-size:14px">Send points as appreciation. Your wallet: <?= number_format((int)($me['points_balance']??0)) ?> pts</p>
    <div class="flex gap-2 mb-3" style="flex-wrap:wrap">
      <?php foreach ([10,25,50,100,200] as $amt): ?>
        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('tipAmt').value=<?=$amt?>"><?=$amt?> pts</button>
      <?php endforeach; ?>
    </div>
    <input class="input mb-3" type="number" id="tipAmt" placeholder="Custom amount" min="1"/>
    <button class="btn btn-primary w-full" onclick="sendTip()">💸 Send Tip</button>
  </div>
</div>
<?php endif; ?>

<!-- BOTTOM NAV (when logged in) -->
<?php if ($me): ?>
<nav class="bottom-nav">
  <a href="/dashboard/"          class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>Voice</a>
  <a href="/dashboard/status.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>Status</a>
  <a href="/dashboard/"           class="bottom-nav-item"><div class="bottom-nav-create"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div></a>
  <a href="/dashboard/wallet.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Wallet</a>
  <a href="/dashboard/profile.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profile</a>
</nav>
<?php endif; ?>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
/* ── Main Post Player ─────────────────────────────── */
const postAudio    = new Audio('<?= clean($post['audio_url'] ?? '') ?>');
const bars         = document.querySelectorAll('#waveform .waveform-bar');
const progressFill = document.getElementById('progressFill');
const progressBar  = document.getElementById('progressBar');
const playerTime   = document.getElementById('playerTime');
const playBtn      = document.getElementById('playBtn');
let   playing      = false;

const fmt = s => String(Math.floor(s/60)).padStart(2,'0') + ':' + String(Math.floor(s%60)).padStart(2,'0');

postAudio.addEventListener('loadedmetadata', () => {
  playerTime.textContent = '0:00 / ' + fmt(postAudio.duration);
});
postAudio.addEventListener('timeupdate', () => {
  const pct    = postAudio.currentTime / postAudio.duration * 100 || 0;
  progressFill.style.width = pct + '%';
  playerTime.textContent   = fmt(postAudio.currentTime) + ' / ' + fmt(postAudio.duration);
  const idx = Math.floor(pct / 100 * bars.length);
  bars.forEach((b, i) => {
    b.classList.toggle('played', i < idx);
    b.classList.toggle('active', i === idx);
  });
});
postAudio.addEventListener('ended', () => {
  playing = false;
  playBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
  bars.forEach(b => b.classList.remove('active','played'));
  progressFill.style.width = '0%';
});

function togglePlay() {
  if (playing) {
    postAudio.pause();
    playBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
  } else {
    postAudio.play();
    playBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>';
  }
  playing = !playing;
}

function seekAudio(e) {
  const pct = e.offsetX / progressBar.offsetWidth;
  postAudio.currentTime = pct * postAudio.duration;
}
function scrollToReplies() {
  document.getElementById('repliesList').scrollIntoView({ behavior:'smooth', block:'start' });
}

/* ── Energy ─────────────────────────────────────── */
async function sendEnergy() {
  const btn = document.getElementById('energyBtn');
  if (btn.classList.contains('active')) { Toast.info('You already energized this post'); return; }
  const res = await API.post('/posts/<?= $postId ?>/energy', { amount: 1 });
  if (res?.success) {
    btn.classList.add('active');
    document.getElementById('energyCount').textContent = res.total_energy.toLocaleString();
    Toast.success('⚡ Energy sent!');
  }
}

/* ── Follow ─────────────────────────────────────── */
async function toggleFollow(userId) {
  const btn   = document.getElementById('followBtn');
  const isFol = btn.textContent.trim() === 'Following';
  const ep    = isFol ? '/unfollow' : '/follow';
  const res   = await API.post(ep, { user_id: userId });
  if (res?.success) {
    btn.textContent = isFol ? 'Follow' : 'Following';
    btn.classList.toggle('btn-primary',   isFol);
    btn.classList.toggle('btn-secondary', !isFol);
  }
}

/* ── Tip ────────────────────────────────────────── */
function openTipModal()  { Modal.open('tipModal'); }
async function sendTip() {
  const amt = parseInt(document.getElementById('tipAmt').value);
  if (!amt || amt < 1) { Toast.error('Enter a tip amount'); return; }
  const res = await API.post('/tips/send', { post_id: <?= $postId ?>, amount: amt });
  if (res?.success) { Toast.success('💸 Tip sent!'); Modal.close('tipModal'); }
}

/* ── Reply Tabs ─────────────────────────────────── */
function switchReplyTab(tab) {
  document.getElementById('voiceReplyPanel').classList.toggle('hidden', tab !== 'voice');
  document.getElementById('textReplyPanel').classList.toggle('hidden',  tab !== 'text');
  document.getElementById('tabVoice').classList.toggle('active', tab === 'voice');
  document.getElementById('tabText').classList.toggle('active',  tab === 'text');
}

/* ── Voice Reply ────────────────────────────────── */
VoiceRecorder.init('recCircle','recTime','recWave','submitVoiceReply');

async function submitVoiceReply() {
  const btn = document.getElementById('submitVoiceReply');
  setLoading(btn, true);
  const res = await VoiceRecorder.upload('', { post_id: <?= $postId ?> }, '/api/v1/voice/reply');
  setLoading(btn, false);
  if (res?.success) {
    Toast.success('Voice reply posted!');
    setTimeout(() => location.reload(), 800);
  } else if (res?.message) {
    Toast.error(res.message);
  }
}

/* ── Text Reply ─────────────────────────────────── */
async function submitTextReply() {
  const text = document.getElementById('replyText').value.trim();
  if (!text) { Toast.error('Type your reply first'); return; }
  const res = await API.post('/posts/<?= $postId ?>/reply', { text });
  if (res?.success) {
    Toast.success('Reply posted!');
    setTimeout(() => location.reload(), 600);
  }
}

// Init reply players
VoicePlayer.init();
</script>
</body>
</html>
