<?php
/**
 * Voxu — Notifications · Socimo-inspired
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/i18n.php';
requireAuth();

$user    = auth();
$userId  = (int)$user['id'];
$settings = getPlatformSettings();
$appName  = clean($settings['app_name'] ?? 'Voxu');
$theme    = getTheme();

if ($_GET['mark_all'] ?? false) {
    try { DB::exec("UPDATE notifications SET is_read=1 WHERE user_id=?", [$userId]); } catch(Throwable){}
    redirect('/dashboard/notifications.php');
}

$page    = max(1,(int)($_GET['page']??1));
$perPage = 30;
$offset  = ($page-1)*$perPage;

try {
    $notifs = DB::query(
        "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
        [$userId]
    );
    $total  = DB::count('notifications','user_id=?',[$userId]);
    $unread = DB::count('notifications','user_id=? AND is_read=0',[$userId]);
} catch(Throwable){ $notifs=[]; $total=0; $unread=0; }

// Mark all as read on page load
try { DB::exec("UPDATE notifications SET is_read=1 WHERE user_id=?",[$userId]); } catch(Throwable){}

$NOTIF_ICONS = [
    'reply'          => ['💬','var(--blue-l)'],
    'follow'         => ['👤','var(--green-l)'],
    'energy'         => ['⚡','var(--warning-l)'],
    'boost'          => ['🚀','var(--purple-l)'],
    'tip'            => ['💸','var(--green-l)'],
    'message'        => ['💌','var(--blue-l)'],
    'message_accepted'=>['✅','var(--green-l)'],
    'withdrawal'     => ['💰','var(--green-l)'],
    'custom_url'     => ['🔗','var(--purple-l)'],
    'info'           => ['ℹ️','var(--blue-l)'],
    'warning'        => ['⚠️','var(--warning-l)'],
    'system'         => ['🔔','var(--purple-l)'],
];

$totalPages = max(1, ceil($total/$perPage));
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>" <?= isRtl()?'dir="rtl"':'' ?>>
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<meta name="csrf-token" content="<?= csrfToken() ?>"/>
<title>Notifications — <?= $appName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="/assets/css/voxu.css"/>
</head>
<body class="theme-<?= clean($theme) ?>">

<nav class="sk-topnav">
  <a href="/dashboard/feed.php" class="sk-logo"><?= $appName ?><span class="dot">.</span></a>
  <div style="position:absolute;left:50%;transform:translateX(-50%);font-size:16px;font-weight:700;color:var(--text)">Notifications <?php if($unread>0): ?><span class="badge badge-purple"><?= $unread ?></span><?php endif; ?></div>
  <div class="sk-nav-actions">
    <?php if($unread>0): ?><a href="?mark_all=1" class="btn btn-ghost btn-sm" style="font-size:13px">Mark all read</a><?php endif; ?>
    <a href="/dashboard/profile.php" style="flex-shrink:0"><div class="avatar avatar-sm"><?php if(!empty($user['avatar'])): ?><img src="<?= clean($user['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?></div></a>
  </div>
</nav>

<div class="sk-layout" style="padding-top:var(--nav-h)">
  <main style="flex:1;min-width:0;max-width:700px">

    <!-- Filter tabs -->
    <div class="sk-feed-header">
      <div class="sk-tabs">
        <div class="sk-tab active">All</div>
        <div class="sk-tab" onclick="filterType('reply')">Replies</div>
        <div class="sk-tab" onclick="filterType('follow')">Follows</div>
        <div class="sk-tab" onclick="filterType('energy')">Energy</div>
      </div>
    </div>

    <?php if(empty($notifs)): ?>
    <div class="sk-empty">
      <div class="sk-empty-icon">🔔</div>
      <div class="sk-empty-title">No notifications yet</div>
      <p class="sk-empty-desc">When someone follows you, replies or sends energy, you'll see it here.</p>
    </div>
    <?php else: ?>
      <?php foreach($notifs as $n):
        [$icon,$bg] = $NOTIF_ICONS[$n['type']] ?? ['🔔','var(--purple-l)'];
      ?>
      <div class="notif-item <?= $n['is_read']?'':'unread' ?>" data-type="<?= clean($n['type']) ?>">
        <div class="notif-icon-wrap" style="background:<?= $bg ?>"><?= $icon ?></div>
        <div class="notif-body">
          <div class="notif-text"><?= clean($n['message']) ?></div>
          <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
        </div>
        <?php if(!$n['is_read']): ?><div class="notif-unread-dot"></div><?php endif; ?>
      </div>
      <?php endforeach; ?>

      <?php if($totalPages>1): ?>
      <div style="display:flex;justify-content:center;gap:8px;padding:20px">
        <?php for($p=1;$p<=$totalPages;$p++): ?>
        <a href="?page=<?=$p?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>" style="border-radius:999px"><?=$p?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <aside class="sk-aside">
    <div class="sk-widget">
      <div class="sk-widget-head">Notification Settings</div>
      <div class="sk-widget-row">
        <label class="toggle" style="justify-content:space-between">
          <span style="font-size:14px;color:var(--text)">Replies</span>
          <input type="checkbox" checked/><span class="toggle-track"></span>
        </label>
      </div>
      <div class="sk-widget-row">
        <label class="toggle" style="justify-content:space-between">
          <span style="font-size:14px;color:var(--text)">New followers</span>
          <input type="checkbox" checked/><span class="toggle-track"></span>
        </label>
      </div>
      <div class="sk-widget-row">
        <label class="toggle" style="justify-content:space-between">
          <span style="font-size:14px;color:var(--text)">Energy & tips</span>
          <input type="checkbox" checked/><span class="toggle-track"></span>
        </label>
      </div>
    </div>
  </aside>
</div>

<nav class="bottom-nav">
  <a href="/dashboard/feed.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>Home</a>
  <a href="/dashboard/status.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/></svg>Status</a>
  <a href="/dashboard/notifications.php" class="bottom-nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>Notifs</a>
  <a href="/dashboard/messages.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>DMs</a>
  <a href="/dashboard/profile.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Me</a>
</nav>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
function filterType(t) {
  document.querySelectorAll('.notif-item').forEach(el => {
    el.style.display = (!t || el.dataset.type===t) ? 'flex' : 'none';
  });
  document.querySelectorAll('.sk-tab').forEach((el,i)=>{
    el.classList.toggle('active', i===(['all','reply','follow','energy'].indexOf(t)||0));
  });
}
VoxuI18n.init();
</script>
</body></html>
