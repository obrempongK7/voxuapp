<?php
/**
 * Voxu — Direct Messages
 * Followers: unlimited | Non-followers: 2 messages until accepted
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/i18n.php';
requireAuth();

$user   = auth();
$userId = (int)$user['id'];
$settings = getPlatformSettings();
$theme    = $_COOKIE['voxu_theme'] ?? 'dark';
$convId   = (int)($_GET['conv'] ?? 0);

// ── ENSURE TABLES ──────────────────────────────────────
DB::exec("CREATE TABLE IF NOT EXISTS `message_conversations` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_a`         INT UNSIGNED NOT NULL,
  `user_b`         INT UNSIGNED NOT NULL,
  `status`         ENUM('pending','accepted','blocked') DEFAULT 'pending',
  `last_message_id`INT UNSIGNED DEFAULT NULL,
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_conv (user_a, user_b),
  INDEX idx_a (user_a), INDEX idx_b (user_b)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

DB::exec("CREATE TABLE IF NOT EXISTS `messages` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT UNSIGNED NOT NULL,
  `sender_id`       INT UNSIGNED NOT NULL,
  `body`            TEXT NOT NULL,
  `is_read`         TINYINT(1) DEFAULT 0,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conv    (conversation_id),
  INDEX idx_sender  (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Load all conversations for sidebar
$conversations = DB::query(
    "SELECT mc.*,
            CASE WHEN mc.user_a=? THEN mc.user_b ELSE mc.user_a END AS other_id,
            u.username AS other_username,
            up.avatar  AS other_avatar,
            (SELECT body FROM messages WHERE conversation_id=mc.id ORDER BY created_at DESC LIMIT 1) AS last_msg,
            (SELECT created_at FROM messages WHERE conversation_id=mc.id ORDER BY created_at DESC LIMIT 1) AS last_msg_at,
            (SELECT COUNT(*) FROM messages WHERE conversation_id=mc.id AND sender_id!=? AND is_read=0) AS unread_count
     FROM message_conversations mc
     JOIN users u ON u.id = CASE WHEN mc.user_a=? THEN mc.user_b ELSE mc.user_a END
     LEFT JOIN user_profiles up ON up.user_id = u.id
     WHERE mc.user_a=? OR mc.user_b=?
     ORDER BY mc.updated_at DESC",
    [$userId, $userId, $userId, $userId, $userId]
);

// Active conversation
$activeConv   = null;
$otherUser    = null;
$chatMessages = [];
$canReply     = true;
$replyReason  = '';

if ($convId) {
    $activeConv = DB::first(
        "SELECT mc.*, CASE WHEN mc.user_a=? THEN mc.user_b ELSE mc.user_a END AS other_id
         FROM message_conversations mc WHERE mc.id=? AND (mc.user_a=? OR mc.user_b=?)",
        [$userId, $convId, $userId, $userId]
    );
    if ($activeConv) {
        $otherUser = DB::first(
            "SELECT u.*, up.avatar, up.bio FROM users u LEFT JOIN user_profiles up ON up.user_id=u.id WHERE u.id=?",
            [(int)$activeConv['other_id']]
        );
        $chatMessages = DB::query(
            "SELECT m.*, u.username FROM messages m JOIN users u ON u.id=m.sender_id
             WHERE m.conversation_id=? ORDER BY m.created_at ASC LIMIT 100",
            [$convId]
        );
        // Mark as read
        DB::exec('UPDATE messages SET is_read=1 WHERE conversation_id=? AND sender_id!=?', [$convId, $userId]);
        // Check if can reply
        $check = canSendMessage($userId, (int)$activeConv['other_id']);
        $canReply   = $check['allowed'];
        $replyReason= $check['reason'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>" <?= isRtl() ? 'dir="rtl"' : '' ?>>
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta name="csrf-token" content="<?= csrfToken() ?>"/>
  <title>Messages — <?= clean($settings['app_name']??'Voxu') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
  <style>
    body { overflow: hidden; }
    .msg-page { padding-top: var(--nav-h); height: 100vh; display: flex; flex-direction: column; }
    .msg-body  { flex: 1; display: flex; overflow: hidden; }
    @media(max-width:700px) {
      .msg-list  { display: <?= $convId ? 'none' : 'flex' ?>; flex-direction: column; width: 100%; }
      .msg-pane  { display: <?= $convId ? 'flex' : 'none' ?>; }
    }
    .back-btn { display: none; }
    @media(max-width:700px) { .back-btn { display: flex; } }
    .empty-msgs { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; text-align: center; }
    .accept-bar { background: var(--bg2); border-top: 1px solid var(--border); padding: 12px 16px; display: flex; align-items: center; gap: 10px; }
    .theme-toggle { width: 38px; height: 20px; background: var(--bg3); border-radius: 10px; position: relative; cursor: pointer; border: 1px solid var(--border2); flex-shrink: 0; }
    .theme-toggle-knob { position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--purple); transition: left .2s; }
    body.theme-light .theme-toggle-knob { left: 20px; background: var(--warning); }
  </style>
</head>
<body class="theme-<?= clean($theme) ?>">

<nav class="sk-topnav">
  <a href="/dashboard/feed.php" class="sk-logo"><?= $appName ?><span class="dot">.</span></a>
  <div style="position:absolute;left:50%;transform:translateX(-50%);font-size:17px;font-weight:700;color:var(--text)">&#128172; Messages</div>
  <div class="sk-nav-actions">
    <a href="/dashboard/notifications.php" class="sk-nav-btn" title="Notifications">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </a>
    <a href="/dashboard/profile.php" style="flex-shrink:0;text-decoration:none">
      <div class="avatar avatar-sm"><?php if(!empty($user['avatar'])): ?><img src="<?= clean($user['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?></div>
    </a>
  </div>
</nav>

<div class="msg-page">
  <div class="msg-body">

    <!-- CONVERSATION LIST -->
    <div class="msg-list" style="border-right:1px solid var(--border)">
      <?php if (empty($conversations)): ?>
        <div class="empty-msgs">
          <div style="font-size:40px;margin-bottom:12px">💬</div>
          <div style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:6px">No messages yet</div>
          <p style="font-size:14px;color:var(--text2)">Send a message to start a conversation</p>
          <button class="btn btn-primary" style="margin-top:14px;border-radius:999px" onclick="Modal.open('new-msg-modal')">Start a conversation</button>
        </div>
      <?php else: ?>
        <!-- Message request section -->
        <?php
        $requests  = array_filter($conversations, fn($c) => $c['status']==='pending' && $c['user_a']!=$userId);
        $accepted  = array_filter($conversations, fn($c) => $c['status']==='accepted' || $c['user_a']==$userId);
        if (!empty($requests)):
        ?>
        <div style="padding:10px 14px;background:var(--warning-l);border-bottom:1px solid var(--border)">
          <div style="font-size:13px;font-weight:600;color:var(--warning)">📩 <?= count($requests) ?> message request<?= count($requests)>1?'s':'' ?></div>
        </div>
        <?php foreach ($requests as $conv): ?>
        <div class="msg-list-item <?= $convId==$conv['id']?'active':'' ?>" onclick="location.href='?conv=<?= $conv['id'] ?>'">
          <div class="avatar avatar-sm">
            <?php if ($conv['other_avatar']): ?><img src="<?= clean($conv['other_avatar']) ?>" alt=""/><?php else: ?><?= avatarInitials($conv['other_username']) ?><?php endif; ?>
          </div>
          <div class="msg-list-info">
            <div class="msg-list-name"><?= clean($conv['other_username']) ?> <span style="font-size:11px;background:var(--warning-l);color:var(--warning);padding:1px 6px;border-radius:10px;font-weight:600">Request</span></div>
            <div class="msg-list-preview"><?= clean(mb_substr($conv['last_msg']??'',0,40)) ?></div>
          </div>
          <?php if ($conv['unread_count']>0): ?><div class="msg-unread-dot"></div><?php endif; ?>
        </div>
        <?php endforeach; endif; ?>

        <?php foreach ($accepted as $conv): ?>
        <div class="msg-list-item <?= $convId==$conv['id']?'active':'' ?>" onclick="location.href='?conv=<?= $conv['id'] ?>'">
          <div class="avatar avatar-sm">
            <?php if ($conv['other_avatar']): ?><img src="<?= clean($conv['other_avatar']) ?>" alt=""/><?php else: ?><?= avatarInitials($conv['other_username']) ?><?php endif; ?>
          </div>
          <div class="msg-list-info">
            <div class="msg-list-name"><?= clean($conv['other_username']) ?></div>
            <div class="msg-list-preview"><?= clean(mb_substr($conv['last_msg']??'No messages yet',0,45)) ?></div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <?php if ($conv['last_msg_at']): ?><div class="msg-list-time"><?= timeAgo($conv['last_msg_at']) ?></div><?php endif; ?>
            <?php if ($conv['unread_count']>0): ?><div class="msg-unread-dot" style="margin-top:4px;margin-left:auto"></div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- CHAT PANE -->
    <div class="msg-pane">
      <?php if (!$convId || !$otherUser): ?>
        <div class="empty-msgs">
          <div style="font-size:48px;margin-bottom:12px">💬</div>
          <div style="font-size:20px;font-weight:700;color:var(--text);margin-bottom:8px">Select a conversation</div>
          <p style="font-size:14px;color:var(--text2)">Choose from your messages on the left, or start a new one.</p>
        </div>
      <?php else: ?>

        <!-- CHAT HEADER -->
        <div class="msg-pane-header">
          <a href="/dashboard/profile.php?u=<?= urlencode($otherUser['username']) ?>">
            <div class="avatar avatar-sm">
              <?php if ($otherUser['avatar']): ?><img src="<?= clean($otherUser['avatar']) ?>" alt=""/><?php else: ?><?= avatarInitials($otherUser['username']) ?><?php endif; ?>
            </div>
          </a>
          <div style="flex:1">
            <div style="font-weight:700;font-size:15px;color:var(--text)">@<?= clean($otherUser['username']) ?></div>
            <?php
            $isFollowing = DB::count('followers','follower_id=? AND following_id=?',[$userId,(int)$activeConv['other_id']]) > 0;
            ?>
            <div style="font-size:12px;color:var(--text3)"><?= $isFollowing ? '✓ You follow them' : 'Not following' ?></div>
          </div>
          <?php if ($activeConv['status'] === 'pending' && $activeConv['user_b'] == $userId): ?>
            <div style="display:flex;gap:8px">
              <button class="btn btn-success btn-sm" style="border-radius:999px" onclick="respondRequest(<?= $convId ?>,'accept')">Accept</button>
              <button class="btn btn-danger btn-sm" style="border-radius:999px" onclick="respondRequest(<?= $convId ?>,'block')">Block</button>
            </div>
          <?php elseif ($activeConv['status'] === 'accepted'): ?>
            <span class="msg-accepted-badge">✓ Connected</span>
          <?php endif; ?>
        </div>

        <!-- REQUEST BANNER (for sender waiting on acceptance) -->
        <?php if ($activeConv['status'] === 'pending' && $activeConv['user_b'] != $userId): ?>
        <div class="msg-request-banner">
          ⚠ Your message request is pending. You can send up to 2 messages until @<?= clean($otherUser['username']) ?> accepts.
        </div>
        <?php endif; ?>

        <!-- MESSAGES -->
        <div class="msg-pane-messages" id="messagesContainer">
          <?php if (empty($chatMessages)): ?>
            <div style="text-align:center;color:var(--text3);font-size:14px;padding:20px">
              Say hello to @<?= clean($otherUser['username']) ?>!
            </div>
          <?php else: ?>
            <?php $lastDate = null; foreach ($chatMessages as $msg):
              $isMe   = $msg['sender_id'] == $userId;
              $msgDate= date('Y-m-d', strtotime($msg['created_at']));
              if ($msgDate !== $lastDate):
                $lastDate = $msgDate;
            ?>
              <div class="msg-time"><?= $msgDate === date('Y-m-d') ? 'Today' : date('d M Y', strtotime($msg['created_at'])) ?></div>
            <?php endif; ?>
            <div class="msg-bubble-wrap <?= $isMe ? 'mine' : '' ?>">
              <?php if (!$isMe): ?>
              <div class="avatar" style="width:28px;height:28px;font-size:10px;flex-shrink:0">
                <?php if ($otherUser['avatar']): ?><img src="<?= clean($otherUser['avatar']) ?>" alt=""/><?php else: ?><?= avatarInitials($otherUser['username']) ?><?php endif; ?>
              </div>
              <?php endif; ?>
              <div>
                <div class="msg-bubble <?= $isMe ? 'mine' : 'theirs' ?>"><?= clean($msg['body']) ?></div>
                <div style="font-size:10px;color:var(--text3);margin-top:3px;text-align:<?= $isMe?'right':'left' ?>"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- INPUT -->
        <?php if ($canReply || $replyReason === 'request'): ?>
        <div class="msg-input-row">
          <textarea class="msg-input" id="msgInput" placeholder="Message @<?= clean($otherUser['username']) ?>…" rows="1"
            onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage()}"
            oninput="autoResizeTextarea(this)"></textarea>
          <button onclick="sendMessage()" style="width:42px;height:42px;border-radius:50%;background:var(--purple);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:var(--transition)" title="Send">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          </button>
        </div>
        <?php else: ?>
        <div style="padding:14px 16px;text-align:center;background:var(--bg2);border-top:1px solid var(--border)">
          <div style="font-size:14px;color:var(--text2)"><?= clean($replyReason) ?></div>
          <?php if (!$isFollowing): ?>
            <button class="btn btn-primary btn-sm" style="margin-top:8px;border-radius:999px" onclick="followToUnlock()">Follow @<?= clean($otherUser['username']) ?> to send more</button>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- NEW MESSAGE MODAL -->
<div class="modal-overlay" id="new-msg-modal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title">New Message</div>
    <div class="input-group mb-3">
      <label class="input-label">To</label>
      <input class="input" type="text" id="newMsgUsername" placeholder="@username" autocomplete="off"/>
      <div id="userSuggestions" style="background:var(--bg2);border:1px solid var(--border);border-radius:8px;max-height:150px;overflow-y:auto;display:none"></div>
    </div>
    <div class="input-group mb-3">
      <label class="input-label">Message</label>
      <textarea class="input" id="newMsgBody" rows="3" placeholder="Write your message…"></textarea>
    </div>
    <button class="btn btn-primary w-full" style="border-radius:999px" onclick="startConversation()">Send Message</button>
  </div>
</div>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
const CONV_ID    = <?= $convId ?: 'null' ?>;
const OTHER_ID   = <?= $otherUser ? $activeConv['other_id'] : 'null' ?>;
const CONV_STATUS= '<?= $activeConv['status'] ?? '' ?>';

/* ── AUTO-SCROLL ────────────────────────────── */
const container = document.getElementById('messagesContainer');
if (container) container.scrollTop = container.scrollHeight;

/* ── SEND MESSAGE ───────────────────────────── */
async function sendMessage() {
  const input = document.getElementById('msgInput');
  const body  = input.value.trim();
  if (!body) return;
  input.value = '';
  input.style.height = 'auto';

  const res = await API.post('/messages/send', { conversation_id: CONV_ID, recipient_id: OTHER_ID, body });
  if (res?.success) {
    appendMessage(body, true, res.created_at);
    if (res.conversation_id && !CONV_ID) {
      window.history.replaceState({}, '', '?conv=' + res.conversation_id);
    }
  } else {
    Toast.error(res?.message || 'Could not send message');
    input.value = body; // restore
  }
}

function appendMessage(body, isMe, time) {
  if (!container) return;
  const el = document.createElement('div');
  el.className = 'msg-bubble-wrap' + (isMe ? ' mine' : '');
  el.innerHTML = `<div><div class="msg-bubble ${isMe?'mine':'theirs'}">${escapeHtml(body)}</div><div style="font-size:10px;color:var(--text3);margin-top:3px;text-align:${isMe?'right':'left'}">${time||'just now'}</div></div>`;
  container.appendChild(el);
  container.scrollTop = container.scrollHeight;
}

function escapeHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ── ACCEPT / BLOCK ─────────────────────────── */
async function respondRequest(convId, action) {
  const res = await API.post('/messages/respond', { conversation_id: convId, action });
  if (res?.success) { Toast.success(action === 'accept' ? 'Request accepted' : 'Conversation blocked'); setTimeout(() => location.reload(), 600); }
}

/* ── FOLLOW TO UNLOCK ───────────────────────── */
async function followToUnlock() {
  const res = await API.post('/follow', { user_id: OTHER_ID });
  if (res?.success) { Toast.success('Following — you can now send unlimited messages'); setTimeout(() => location.reload(), 800); }
}

/* ── START NEW CONVERSATION ─────────────────── */
async function startConversation() {
  const username = document.getElementById('newMsgUsername').value.trim().replace('@','');
  const body     = document.getElementById('newMsgBody').value.trim();
  if (!username || !body) { Toast.error('Username and message required'); return; }
  const res = await API.post('/messages/send', { username, body });
  if (res?.success) { Modal.close('new-msg-modal'); window.location.href = '?conv=' + res.conversation_id; }
  else { Toast.error(res?.message || 'Failed to send'); }
}

/* ── USER AUTOCOMPLETE ──────────────────────── */
let searchTimer;
document.getElementById('newMsgUsername')?.addEventListener('input', function() {
  clearTimeout(searchTimer);
  const q = this.value.trim().replace('@','');
  const sug = document.getElementById('userSuggestions');
  if (q.length < 2) { sug.style.display = 'none'; return; }
  searchTimer = setTimeout(async () => {
    const data = await API.get('/admin/search?q=' + encodeURIComponent(q));
    if (!data?.results?.length) { sug.style.display = 'none'; return; }
    sug.innerHTML = data.results.map(u => `<div style="padding:10px 12px;cursor:pointer;transition:background .15s" onmouseover="this.style.background='var(--bg3)'" onmouseout="this.style.background=''" onclick="selectUser('${u.username}')"><span style="font-weight:600;color:var(--text)">@${u.username}</span></div>`).join('');
    sug.style.display = 'block';
  }, 300);
});
function selectUser(username) {
  document.getElementById('newMsgUsername').value = '@' + username;
  document.getElementById('userSuggestions').style.display = 'none';
}

/* ── TEXTAREA AUTO-RESIZE ───────────────────── */
function autoResizeTextarea(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

/* ── POLLING (refresh every 8s when chat is open) ─ */
<?php if ($convId): ?>
setInterval(async () => {
  const data = await API.get('/messages/' + CONV_ID + '?after=' + <?= $chatMessages ? max(array_column($chatMessages,'id')) : 0 ?>);
  if (data?.messages?.length) {
    data.messages.forEach(m => {
      if (m.sender_id != <?= $userId ?>) appendMessage(m.body, false, m.created_at.substr(11,5));
    });
  }
}, 8000);
<?php endif; ?>
</script>
</body>
</html>
