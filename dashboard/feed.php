<?php
/**
 * Voxu — Main Feed · v3 · Socimo-inspired layout
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
$wallet   = getUserWallet($userId);
$myPlan   = getUserPlan($userId);
$settings = getPlatformSettings();
$appName  = clean($settings['app_name'] ?? 'Voxu');
$symbol   = clean($settings['currency_symbol'] ?? '$');
$recLimit = getUserRecordingLimit($userId);
$theme    = getTheme();
$lang     = getCurrentLang();

$tab     = sanitize($_GET['tab'] ?? 'for_you');
$hashTag = sanitize($_GET['tag'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page-1)*$perPage;

// Feed query
$params = [$userId];
$join   = '';
if ($tab === 'following') {
    $where = "p.status='active' AND p.user_id IN (SELECT following_id FROM followers WHERE follower_id=?)";
} elseif ($tab === 'hashtag' && $hashTag) {
    $join  = "JOIN post_hashtags ph ON ph.post_id=p.id";
    $where = "p.status='active' AND ph.hashtag=?";
    $params= [strtolower($hashTag)];
} elseif ($tab === 'voice') {
    $where = "p.status='active' AND p.audio_url IS NOT NULL";
} else {
    $where = "p.status='active'";
}

try {
    $posts = DB::query(
        "SELECT p.*, u.username, up.avatar,
            (SELECT COALESCE(SUM(amount),0) FROM energy_transactions WHERE post_id=p.id) AS energy_total,
            (SELECT COUNT(*) FROM replies WHERE post_id=p.id AND status='active') AS reply_count,
            (SELECT 1 FROM energy_transactions WHERE post_id=p.id AND giver_id=? LIMIT 1) AS user_gave_energy,
            (SELECT 1 FROM post_boosts WHERE post_id=p.id AND status='active' AND expires_at>NOW() LIMIT 1) AS is_boosted
         FROM posts p
         JOIN users u ON u.id=p.user_id
         LEFT JOIN user_profiles up ON up.user_id=p.user_id
         {$join}
         WHERE {$where}
         ORDER BY is_boosted DESC, p.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
} catch (Throwable) { $posts = []; }

// Sidebar data
try { $trending = DB::query("SELECT ph.hashtag, COUNT(*) AS cnt FROM post_hashtags ph JOIN posts p ON p.id=ph.post_id WHERE p.created_at > DATE_SUB(NOW(),INTERVAL 48 HOUR) AND p.status='active' GROUP BY ph.hashtag ORDER BY cnt DESC LIMIT 8"); } catch(Throwable){ $trending=[]; }
if (empty($trending)) { try { $trending = DB::query("SELECT ph.hashtag, COUNT(*) AS cnt FROM post_hashtags ph JOIN posts p ON p.id=ph.post_id WHERE p.status='active' GROUP BY ph.hashtag ORDER BY cnt DESC LIMIT 8"); } catch(Throwable){} }
try { $suggested = DB::query("SELECT u.id, u.username, up.avatar, (SELECT COUNT(*) FROM followers WHERE following_id=u.id) AS fc FROM users u LEFT JOIN user_profiles up ON up.user_id=u.id WHERE u.id!=? AND u.status='active' AND u.id NOT IN(SELECT following_id FROM followers WHERE follower_id=?) ORDER BY fc DESC LIMIT 4", [$userId,$userId]); } catch(Throwable){ $suggested=[]; }
try { $channels = DB::query('SELECT * FROM channels WHERE is_active=1 ORDER BY sort_order,name'); } catch(Throwable){ $channels=[]; }
try { $unreadNotifs = DB::count('notifications','user_id=? AND is_read=0',[$userId]); } catch(Throwable){ $unreadNotifs=0; }
try { $unreadMsgs = DB::first("SELECT COUNT(*) AS n FROM messages m JOIN message_conversations mc ON mc.id=m.conversation_id WHERE (mc.user_a=? OR mc.user_b=?) AND m.sender_id!=? AND m.is_read=0",[$userId,$userId,$userId])['n']??0; } catch(Throwable){ $unreadMsgs=0; }
try { $statuses = DB::query("SELECT sp.*, u.username, up.avatar FROM status_posts sp JOIN users u ON u.id=sp.user_id LEFT JOIN user_profiles up ON up.user_id=sp.user_id WHERE sp.status='active' AND sp.expires_at>NOW() AND sp.user_id!=? ORDER BY sp.created_at DESC LIMIT 10", [$userId]); } catch(Throwable){ $statuses=[]; }

// Boost tiers from settings
$boostTiers = [
    ['key'=>'starter', 'label'=>'Starter','c'=>'var(--text2)'],
    ['key'=>'standard','label'=>'Standard','c'=>'var(--blue)'],
    ['key'=>'pro',     'label'=>'Pro',     'c'=>'var(--purple)'],
    ['key'=>'cash',    'label'=>'Cash',    'c'=>'var(--warning)'],
];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" <?= isRtl()?'dir="rtl"':'' ?>>
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="csrf-token" content="<?= csrfToken() ?>"/>
  <title><?= $tab==='hashtag'&&$hashTag?'#'.clean($hashTag).' — ':'' ?><?= $appName ?></title>
  <link rel="manifest" href="/manifest.json"/>
  <meta name="theme-color" content="#6C3BFF"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
</head>
<body class="theme-<?= clean($theme) ?>">

<!-- TOP NAV -->
<nav class="sk-topnav">
  <a href="/dashboard/feed.php" class="sk-logo"><?= $appName ?><span class="dot">.</span></a>
  <div class="sk-nav-search">
    <svg class="sk-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" class="sk-search-input" id="topSearch" placeholder="Search <?= $appName ?>…" autocomplete="off" onkeyup="handleSearch(event)"/>
    <div id="searchResults" class="sk-search-results" style="display:none"></div>
  </div>
  <div class="sk-nav-actions">
    <!-- Language switcher -->
    <div style="position:relative">
      <button class="sk-nav-btn" id="langBtn" onclick="document.getElementById('langMenu').classList.toggle('hidden')" title="Language">
        <?= VOXU_LANGUAGES[$lang]['flag'] ?? '🌐' ?>
      </button>
      <div id="langMenu" class="sk-lang-menu hidden">
        <?php foreach(VOXU_LANGUAGES as $code=>$meta): ?>
        <div class="sk-lang-opt <?= $lang===$code?'active':'' ?>" onclick="setLang('<?= $code ?>')">
          <span style="font-size:18px"><?= $meta['flag'] ?></span>
          <div><div style="font-weight:600"><?= htmlspecialchars($meta['native']) ?></div><div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($meta['name']) ?></div></div>
          <?php if($lang===$code): ?><span style="margin-left:auto;color:var(--purple)">✓</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- Theme toggle -->
    <button class="sk-nav-btn" onclick="toggleTheme()" title="Toggle theme">
      <?php if($theme==='dark'): ?><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      <?php else: ?><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg><?php endif; ?>
    </button>
    <!-- Notifications -->
    <a href="/dashboard/notifications.php" class="sk-nav-btn" title="Notifications" style="text-decoration:none">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if($unreadNotifs>0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
    <!-- Avatar -->
    <a href="/dashboard/profile.php" style="flex-shrink:0">
      <div class="avatar avatar-sm">
        <?php if(!empty($user['avatar'])): ?><img src="<?= clean($user['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?>
      </div>
    </a>
  </div>
</nav>

<div class="sk-layout">

  <!-- LEFT SIDEBAR -->
  <aside class="sk-sidebar">
    <!-- Mini profile card -->
    <div class="sk-profile-card">
      <div class="sk-cover-thumb">
        <?php if(!empty($user['cover_photo'])): ?><img src="<?= clean($user['cover_photo']) ?>" alt=""/><?php endif; ?>
      </div>
      <div class="avatar avatar-md" style="margin:-24px auto 0;border:3px solid var(--bg)">
        <?php if(!empty($user['avatar'])): ?><img src="<?= clean($user['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?>
      </div>
      <div class="sk-profile-name"><?= clean($user['username']) ?></div>
      <div class="sk-profile-handle">@<?= clean($user['username']) ?></div>
      <div class="sk-profile-stats">
        <?php
          try {
            $followers  = DB::count('followers','following_id=?',[$userId]);
            $following  = DB::count('followers','follower_id=?',[$userId]);
            $postCount  = DB::count('posts','user_id=? AND status="active"',[$userId]);
          } catch(Throwable) { $followers=$following=$postCount=0; }
        ?>
        <div><span class="sk-stat-num"><?= number_format($followers) ?></span><span class="sk-stat-lbl">Followers</span></div>
        <div><span class="sk-stat-num"><?= number_format($following) ?></span><span class="sk-stat-lbl">Following</span></div>
        <div><span class="sk-stat-num"><?= number_format($postCount) ?></span><span class="sk-stat-lbl">Posts</span></div>
      </div>
    </div>
    <!-- Nav items -->
    <div class="sk-nav-section">
      <?php
      $navItems = [
        ['/dashboard/feed.php','home-icon','Home','home',0,0],
        ['/dashboard/notifications.php','bell-icon','Notifications','notif',$unreadNotifs,0],
        ['/dashboard/messages.php','msg-icon','Messages','msgs',$unreadMsgs,0],
        ['/dashboard/podcast.php','pod-icon','Podcasts','pod',0,0],
        ['/dashboard/status.php','status-icon','Status','status',0,0],
        ['/dashboard/wallet.php','wallet-icon','Wallet','wallet',0,0],
        ['/dashboard/profile.php','profile-icon','Profile','profile',0,0],
                ['/dashboard/premium.php','star-icon','Premium','premium',0,1],
        ['/dashboard/create_ad.php','ad-icon','Create Ad','create_ad',0,0],
      ];
      $icons = [
        'home-icon'  =>'<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'bell-icon'  =>'<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'msg-icon'   =>'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'pod-icon'   =>'<circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>',
        'status-icon'=>'<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/>',
        'wallet-icon'=>'<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>',
        'profile-icon'=>'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                'star-icon'  =>'<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'ad-icon'    =>'<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
      ];
      foreach($navItems as [$href,$ikey,$label,$key,$badge,$isPremium]):
        $isActive = strpos($_SERVER['REQUEST_URI']??'', $href)===0;
      ?>
      <a href="<?= $href ?>" class="sk-nav-item <?= $isActive?'active':'' ?>" <?= $isPremium?'style="color:var(--warning)"':'' ?>>
        <span class="sk-nav-icon" <?= $isPremium?'style="color:var(--warning)"':'' ?>>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $icons[$ikey] ?></svg>
        </span>
        <span class="sk-nav-label" data-i18n="<?= $key ?>"><?= $label ?></span>
        <?php if($badge>0): ?><span class="sk-nav-badge"><?= min($badge,99) ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <button onclick="Modal.open('compose-modal')" class="sk-post-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <span>Post</span>
    </button>
    <!-- Wallet strip -->
    <div style="margin:12px 8px 0;background:var(--purple-l);border:1px solid var(--purple);border-radius:var(--radius-sm);padding:12px">
      <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px">Balance</div>
      <div style="font-size:20px;font-weight:800;color:var(--purple)"><?= number_format((int)($wallet['points_balance']??0)) ?> <span style="font-size:11px;font-weight:400;color:var(--text3)">pts</span></div>
      <div style="font-size:12px;color:var(--text3)"><?= $symbol ?><?= number_format((float)($wallet['balance']??0),2) ?></div>
    </div>
  </aside>

  <!-- MAIN FEED -->
  <main class="sk-feed">
    <!-- Feed header -->
    <div class="sk-feed-header">
      <?php if($tab==='hashtag'&&$hashTag): ?>
        <div style="padding:14px 16px;display:flex;align-items:center;gap:10px">
          <a href="/dashboard/feed.php" style="color:var(--text3)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></a>
          <div><div style="font-size:18px;font-weight:800;color:var(--text)">#<?= clean($hashTag) ?></div><div style="font-size:12px;color:var(--text3)"><?= count($posts) ?> posts</div></div>
        </div>
      <?php else: ?>
        <div class="sk-tabs">
          <div class="sk-tab <?= $tab==='for_you'?'active':'' ?>" onclick="location.href='?tab=for_you'">For You</div>
          <div class="sk-tab <?= $tab==='following'?'active':'' ?>" onclick="location.href='?tab=following'">Following</div>
          <div class="sk-tab <?= $tab==='voice'?'active':'' ?>" onclick="location.href='?tab=voice'">🎙 Voice</div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Status/Story bar -->
    <?php if (!empty($statuses)): ?>
    <div class="sk-stories">
      <div class="sk-story-item" onclick="window.location='/dashboard/status.php?create=1'">
        <div class="sk-story-ring add"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>
        <div class="sk-story-label">Your Story</div>
      </div>
      <?php foreach($statuses as $st): ?>
      <div class="sk-story-item" onclick="openStatus(<?= $st['id'] ?>)">
        <div class="sk-story-ring">
          <div class="sk-story-img" style="<?= $st['bg_color']?'background:'.$st['bg_color'].';':'' ?>">
            <?php if($st['avatar']): ?><img src="<?= clean($st['avatar']) ?>" alt="<?= clean($st['username']) ?>"/>
            <?php else: ?><div class="avatar" style="width:100%;height:100%;border-radius:50%;font-size:12px"><?= avatarInitials($st['username']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="sk-story-label">@<?= clean($st['username']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Composer prompt (voice/video only) -->
    <div class="sk-composer" onclick="Modal.open('compose-modal')">
      <div class="avatar" style="flex-shrink:0;pointer-events:none">
        <?php if(!empty($user['avatar'])): ?><img src="<?= clean($user['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?>
      </div>
      <div class="sk-composer-body" style="pointer-events:none">
        <div class="sk-composer-prompt">Share your voice…</div>
        <div class="sk-composer-actions">
          <button class="sk-composer-btn" onclick="event.stopPropagation();Modal.open('voice-modal')" style="pointer-events:all">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>Voice
          </button>
          <?php if(($settings['allow_short_video']??'1')==='1'): ?>
          <button class="sk-composer-btn" onclick="event.stopPropagation();Modal.open('video-modal')" style="pointer-events:all">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>Video
          </button>
          <?php endif; ?>
          <button class="sk-composer-btn" onclick="event.stopPropagation();window.location='/dashboard/status.php?create=1'" style="pointer-events:all">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/></svg>Status
          </button>
          <div style="margin-left:auto;pointer-events:all">
            <button class="btn btn-primary btn-sm" style="border-radius:999px" onclick="event.stopPropagation();Modal.open('voice-modal')">🎙 Record</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Posts -->
    <div id="feedContainer">
      <?php if(empty($posts)): ?>
      <div class="sk-empty">
        <div class="sk-empty-icon">✦</div>
        <div class="sk-empty-title"><?= $tab==='following'?'No posts from people you follow':'Nothing here yet' ?></div>
        <p class="sk-empty-desc"><?= $tab==='following'?'Follow creators to see their posts here':'Be the first to post a voice!' ?></p>
        <button onclick="Modal.open('voice-modal')" class="btn btn-primary" style="border-radius:999px">🎙 Start Posting</button>
      </div>
      <?php else: ?>
        <?php $idx=0; foreach($posts as $p): $idx++;
          $reactions = getPostReactions((int)$p['id']);
          $myEmoji   = getUserReaction($userId, (int)$p['id']);
        ?>
        <?php if($idx>1 && $idx%8===0): ?><?= renderAds('feed_middle') ?><?php endif; ?>
        <article class="sk-post" onclick="openPost(<?= $p['id'] ?>,event)" data-id="<?= $p['id'] ?>">
          <a href="/dashboard/profile.php?u=<?= urlencode($p['username']) ?>" onclick="event.stopPropagation();event.preventDefault();window.location='/dashboard/profile.php?u=<?= urlencode($p['username']) ?>'">
            <div class="avatar">
              <?php if($p['avatar']): ?><img src="<?= clean($p['avatar']) ?>" alt="" loading="lazy"/><?php else: ?><?= avatarInitials($p['username']) ?><?php endif; ?>
            </div>
          </a>
          <div class="sk-post-right">
            <?php if($p['is_boosted']): ?><div class="sk-boost-badge"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Boosted</div><?php endif; ?>
            <div class="sk-post-header">
              <div class="sk-post-author">
                <a href="/dashboard/profile.php?u=<?= urlencode($p['username']) ?>" class="sk-display-name" onclick="event.stopPropagation()"><?= clean($p['username']) ?></a>
                <?= planBadge($myPlan) ?>
                <span class="sk-dot">·</span>
                <span class="sk-time"><?= timeAgo($p['created_at']) ?></span>
              </div>
              <?php if($p['user_id']==$userId): ?>
              <button class="sk-act" style="max-width:34px;flex:none;color:var(--text3);margin-left:auto" onclick="event.stopPropagation();deletePost(<?= $p['id'] ?>,this.closest('.sk-post'))">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6M9 6V4h6v2"/></svg>
              </button>
              <?php endif; ?>
            </div>
            <?php if($p['title']): ?><div class="sk-post-text"><?= linkifyHashtags($p['title']) ?></div><?php endif; ?>
            <?php if(!empty($p['image_url'])): ?>
            <div class="sk-post-media">
              <img src="<?= clean($p['image_url']) ?>" alt="" loading="lazy" onclick="event.stopPropagation();openLightbox('<?= clean($p['image_url']) ?>')"/>
            </div>
            <?php endif; ?>
            <?php if(!empty($p['audio_url'])): ?>
            <div class="sk-voice-player" onclick="event.stopPropagation()" data-voice-player data-src="<?= clean($p['audio_url']) ?>" data-post-id="<?= $p['id'] ?>">
              <button class="play-btn" style="width:36px;height:36px;flex-shrink:0" aria-label="Play">
                <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              </button>
              <div class="sk-voice-bars">
                <?php for($i=0;$i<36;$i++): ?><div class="waveform-bar" style="height:<?= rand(15,100) ?>%"></div><?php endfor; ?>
              </div>
              <span style="font-size:12px;white-space:nowrap;color:var(--text3)"><?= gmdate('i:s',(int)($p['duration']??0)) ?></span>
            </div>
            <?php endif; ?>
            <!-- Emoji Reactions -->
            <div class="sk-reactions" id="reactions-<?= $p['id'] ?>">
              <?php foreach($reactions as $r): ?>
              <button class="sk-reaction-chip <?= $myEmoji===$r['emoji']?'mine':'' ?>" onclick="event.stopPropagation();reactPost(<?= $p['id'] ?>,'<?= clean($r['emoji']) ?>')">
                <span class="emoji"><?= clean($r['emoji']) ?></span>
                <span class="sk-reaction-count"><?= number_format((int)$r['cnt']) ?></span>
              </button>
              <?php endforeach; ?>
              <div class="sk-add-reaction" onclick="event.stopPropagation();toggleEmojiPicker(<?= $p['id'] ?>,this)" title="React">+</div>
            </div>
            <!-- Action bar -->
            <div class="sk-post-actions" onclick="event.stopPropagation()">
              <button class="sk-act" onclick="openReplies(<?= $p['id'] ?>)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span><?= (int)$p['reply_count']>0?number_format((int)$p['reply_count']):'' ?></span>
              </button>
              <button class="sk-act <?= $p['user_gave_energy']?'energized':'' ?>" id="eact-<?= $p['id'] ?>" onclick="sendEnergy(<?= $p['id'] ?>,this)">
                <svg viewBox="0 0 24 24" fill="<?= $p['user_gave_energy']?'currentColor':'none' ?>" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span id="ecnt-<?= $p['id'] ?>"><?= (int)$p['energy_total']>0?number_format((int)$p['energy_total']):'' ?></span>
              </button>
              <?php if($p['user_id']!=$userId): ?>
              <button class="sk-act" onclick="openTip(<?= $p['id'] ?>)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              </button>
              <?php endif; ?>
              <button class="sk-act" onclick="openBoost(<?= $p['id'] ?>)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>
              <button class="sk-act" style="margin-left:auto" onclick="copyLink('/post/<?= $p['id'] ?>')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
              </button>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
        <?php if(count($posts)>=$perPage): ?>
        <button class="sk-load-more" onclick="loadMore(<?= $page+1 ?>)">Show more posts</button>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>

  <!-- RIGHT ASIDE -->
  <aside class="sk-aside">
    <?= renderAds('feed_top') ?>
    <!-- Trending -->
    <div class="sk-widget">
      <div class="sk-widget-head">Trending</div>
      <?php if(empty($trending)): ?>
        <div style="padding:14px 16px;font-size:14px;color:var(--text3)">No trending topics yet</div>
      <?php else: ?>
        <?php foreach($trending as $t): ?>
        <div class="sk-widget-row" onclick="location.href='?tab=hashtag&tag=<?= urlencode($t['hashtag']) ?>'">
          <div class="trend-meta">Trending</div>
          <div class="trend-tag">#<?= clean($t['hashtag']) ?></div>
          <div class="trend-cnt"><?= number_format($t['cnt']) ?> posts</div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <!-- Who to follow -->
    <?php if(!empty($suggested)): ?>
    <div class="sk-widget">
      <div class="sk-widget-head">Who to follow</div>
      <?php foreach($suggested as $sug): ?>
      <div class="sk-wtf">
        <a href="/dashboard/profile.php?u=<?= urlencode($sug['username']) ?>"><div class="avatar avatar-sm"><?php if($sug['avatar']): ?><img src="<?= clean($sug['avatar']) ?>" alt=""/><?php else: ?><?= avatarInitials($sug['username']) ?><?php endif; ?></div></a>
        <div class="sk-wtf-info">
          <div class="sk-wtf-name"><?= clean($sug['username']) ?></div>
          <div class="sk-wtf-handle"><?= number_format($sug['fc']) ?> followers</div>
        </div>
        <button class="btn btn-outline btn-sm" style="border-radius:999px" onclick="followUser(<?= $sug['id'] ?>,this)">Follow</button>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <!-- Wallet widget -->
    <div class="sk-wallet-widget">
      <div style="font-size:12px;color:var(--text2);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Your Balance</div>
      <div class="sk-wallet-pts"><?= number_format((int)($wallet['points_balance']??0)) ?> <span style="font-size:13px;font-weight:400;color:var(--text2)">pts</span></div>
      <div class="sk-wallet-cash"><?= $symbol ?><?= number_format((float)($wallet['balance']??0),2) ?> cash</div>
      <a href="/dashboard/wallet.php" class="btn btn-primary btn-sm" style="border-radius:999px;width:100%;font-size:13px">Go to Wallet</a>
    </div>
    <!-- Topics -->
    <?php if(!empty($channels)): ?>
    <div class="sk-widget">
      <div class="sk-widget-head">Topics</div>
      <div style="padding:10px 16px 14px;display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach($channels as $ch): ?>
        <a href="?tab=voice&channel=<?= urlencode($ch['slug']) ?>" class="badge badge-muted" style="font-size:12px;padding:5px 12px;cursor:pointer"><?= clean($ch['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <div style="font-size:11px;color:var(--text3);line-height:1.8;padding:0 4px">
      <a href="/terms.php" style="color:var(--text3)">Terms</a> · <a href="/privacy.php" style="color:var(--text3)">Privacy</a> · <a href="/about.php" style="color:var(--text3)">About</a><br/>
      © <?= date('Y') ?> <?= $appName ?>
    </div>
  </aside>
</div>

<!-- MOBILE BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="/dashboard/feed.php" class="bottom-nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>Home</a>
  <a href="/dashboard/status.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/></svg>Status</a>
  <a href="#" onclick="Modal.open('compose-modal');return false" class="bottom-nav-item"><div class="bottom-nav-create"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div></a>
  <a href="/dashboard/messages.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>DMs</a>
  <a href="/dashboard/profile.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Me</a>
</nav>

<!-- ═══ MODALS ═══ -->
<!-- Compose picker -->
<div class="modal-overlay" id="compose-modal">
  <div class="modal"><div class="modal-handle"></div><div class="modal-title">Create Post</div>
    <div class="create-options">
      <div class="create-option" onclick="Modal.close('compose-modal');Modal.open('voice-modal')"><div class="create-option-icon">🎙</div><div class="create-option-title">Voice Post</div><div class="create-option-desc">Record + optional cover image</div></div>
      <?php if(($settings['allow_short_video']??'1')==='1'): ?><div class="create-option" onclick="Modal.close('compose-modal');Modal.open('video-modal')"><div class="create-option-icon">🎬</div><div class="create-option-title">Short Video</div><div class="create-option-desc">Upload video ≤ 60 seconds</div></div><?php endif; ?>
      <div class="create-option" onclick="Modal.close('compose-modal');window.location='/dashboard/status.php?create=1'"><div class="create-option-icon">✨</div><div class="create-option-title">Status</div><div class="create-option-desc">24-hour story</div></div>
      <div class="create-option" onclick="Modal.close('compose-modal');window.location='/dashboard/podcast.php?create=1'"><div class="create-option-icon">🎧</div><div class="create-option-title">Podcast</div><div class="create-option-desc">Upload episode</div></div>
    </div>
  </div>
</div>

<!-- Voice modal -->
<div class="modal-overlay" id="voice-modal">
  <div class="modal">
    <div class="modal-handle"></div><div class="modal-title">🎙 New Voice Post</div>
    <form id="voiceForm">
      <div class="input-group mb-3">
        <label class="input-label">Caption / Title *</label>
        <textarea class="input" id="voiceTitle" rows="2" placeholder="Add a caption… #hashtags welcome" maxlength="280" required style="resize:none"></textarea>
      </div>
      <div class="input-group mb-3">
        <label class="input-label">Cover Image <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
        <label style="display:flex;align-items:center;gap:10px;background:var(--bg3);border:1px dashed var(--border2);border-radius:var(--radius-sm);padding:11px 14px;cursor:pointer">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <span id="voiceImgLabel" style="font-size:13px;color:var(--text2)">Upload cover image</span>
          <input type="file" id="coverImageInput" accept="image/*" style="display:none"/>
        </label>
        <div id="imagePreviewWrap" class="hidden" style="margin-top:8px;position:relative">
          <img id="voiceImagePreview" style="width:100%;max-height:160px;object-fit:cover;border-radius:var(--radius-sm)"/>
          <button type="button" onclick="clearCoverImage()" style="position:absolute;top:6px;right:6px;width:24px;height:24px;background:rgba(0,0,0,.65);border:none;border-radius:50%;color:#fff;cursor:pointer;font-size:12px">✕</button>
        </div>
      </div>
      <div class="recorder-card">
        <div class="recorder-circle" id="recCircle">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:32px;height:32px;color:var(--purple)"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
        </div>
        <div class="recording-time" id="recTime">0:00</div>
        <div class="recording-label">Tap to record · max <?= formatDuration($recLimit?:180) ?></div>
        <div class="waveform" id="recWave" style="margin-top:10px"></div>
        <!-- Replay before posting -->
        <audio id="recPreview" class="record-preview" controls></audio>
        <button type="button" id="rerecordBtn" style="display:none;margin:8px auto 0;background:var(--bg2);border:1px solid var(--border);color:var(--text2);padding:6px 16px;border-radius:999px;cursor:pointer;font-size:13px;font-family:inherit">🔄 Re-record</button>
      </div>
      <button type="submit" class="btn btn-primary w-full" id="submitVoice" disabled style="border-radius:999px">🎙 Post Voice</button>
    </form>
  </div>
</div>

<!-- Short video modal -->
<div class="modal-overlay" id="video-modal">
  <div class="modal">
    <div class="modal-handle"></div><div class="modal-title">🎬 Short Video Post</div>
    <form id="videoForm">
      <div class="input-group mb-3">
        <label class="input-label">Caption *</label>
        <textarea class="input" id="videoTitle" rows="2" placeholder="Describe your video… #hashtags welcome" maxlength="280" required style="resize:none"></textarea>
      </div>
      <div class="input-group mb-3">
        <label class="input-label">Video File * <span style="font-weight:400;color:var(--text3)">(MP4 or WebM, max 60s)</span></label>
        <label style="display:flex;align-items:center;gap:10px;background:var(--bg3);border:1px dashed var(--border2);border-radius:var(--radius-sm);padding:12px 14px;cursor:pointer" id="videoZoneLabel">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
          <div><div id="videoFileName" style="font-size:13px;color:var(--text2)">Tap to select video</div><div id="videoDurInfo" style="font-size:11px;color:var(--text3);margin-top:2px"></div></div>
          <input type="file" id="videoFileInput" accept="video/mp4,video/webm,video/quicktime" style="display:none"/>
        </label>
      </div>
      <div id="videoPreviewWrap" class="hidden" style="margin-bottom:12px">
        <video id="videoPreviewEl" controls style="width:100%;border-radius:var(--radius-sm);max-height:220px"></video>
      </div>
      <div id="videoUploadProgress" class="hidden" style="margin-bottom:12px">
        <div style="height:4px;background:var(--bg3);border-radius:4px;overflow:hidden"><div id="videoPBar" style="height:100%;background:var(--grad-main);width:0%;transition:width .3s"></div></div>
        <div id="videoPText" style="font-size:12px;color:var(--text3);text-align:center;margin-top:4px">Uploading…</div>
      </div>
      <button type="submit" class="btn btn-primary w-full" id="submitVideo" style="border-radius:999px">🎬 Post Video</button>
    </form>
  </div>
</div>

<!-- Replies modal (voice + text) -->
<div class="modal-overlay" id="replies-modal">
  <div class="modal" style="max-width:520px">
    <div class="modal-handle"></div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div class="modal-title" style="margin-bottom:0" id="repliesTitle">Replies</div>
      <div style="display:flex;gap:6px">
        <button id="replyModeTextBtn" class="btn btn-primary btn-sm" style="border-radius:999px;font-size:12px" onclick="setReplyMode('text')">✏ Text</button>
        <button id="replyModeVoiceBtn" class="btn btn-secondary btn-sm" style="border-radius:999px;font-size:12px" onclick="setReplyMode('voice')">🎙 Voice</button>
      </div>
    </div>
    <div id="repliesList" style="min-height:60px;max-height:280px;overflow-y:auto;border-bottom:1px solid var(--border);margin-bottom:12px"></div>
    <!-- Text reply -->
    <div id="replyPaneText">
      <div style="display:flex;gap:8px">
        <input class="input" id="replyInput" placeholder="Write a reply…" style="flex:1;border-radius:999px;font-size:14px" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendTextReply()}"/>
        <button class="btn btn-primary btn-sm" style="border-radius:999px" onclick="sendTextReply()">Send</button>
      </div>
    </div>
    <!-- Voice reply -->
    <div id="replyPaneVoice" class="hidden">
      <div class="recorder-card" style="padding:14px">
        <div style="display:flex;align-items:center;gap:12px">
          <div class="recorder-circle" id="replyRecCircle" style="width:52px;height:52px;margin:0;flex-shrink:0">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;color:var(--purple)"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:2px">Tap to record voice reply</div>
            <div id="replyRecTime" style="font-size:20px;font-weight:800;color:var(--text)">0:00</div>
            <div id="replyRecWave" class="waveform" style="height:26px;margin-top:6px"></div>
            <audio id="replyRecPreview" class="record-preview" controls></audio>
          </div>
        </div>
      </div>
      <button id="submitReplyVoice" class="btn btn-primary w-full" style="border-radius:999px" disabled onclick="submitVoiceReply()">🎙 Send Voice Reply</button>
    </div>
  </div>
</div>

<!-- Boost modal -->
<div class="modal-overlay" id="boost-modal">
  <div class="modal"><div class="modal-handle"></div><div class="modal-title">🚀 Boost Post</div>
    <input type="hidden" id="boostPostId"/>
    <p style="font-size:14px;color:var(--text2);margin-bottom:14px">Amplify your post reach for 24 hours.</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
      <?php foreach($boostTiers as $bt):
        $pts  = (int)($settings["boost_fee_{$bt['key']}"] ?? ['starter'=>50,'standard'=>150,'pro'=>400,'cash'=>0][$bt['key']]);
        $cash = (float)($settings["boost_fee_{$bt['key']}_cash"] ?? ['starter'=>0,'standard'=>0,'pro'=>0,'cash'=>2][$bt['key']]);
        $reach = clean($settings["boost_reach_{$bt['key']}"] ?? '~500 views');
      ?>
      <div class="card card-hover" style="border-color:<?= $bt['c'] ?>;padding:14px" onclick="selectBoost(<?= $pts ?>,<?= $cash ?>,'<?= clean($bt['label']) ?>')">
        <div style="font-size:14px;font-weight:700;color:<?= $bt['c'] ?>"><?= clean($bt['label']) ?></div>
        <div style="font-size:12px;color:var(--text2);margin:4px 0"><?= $reach ?></div>
        <div style="font-size:17px;font-weight:800;color:var(--text)"><?= $pts>0?number_format($pts).' pts':$symbol.number_format($cash,2) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div id="boostConfirm" class="hidden">
      <div class="card card-sm mb-3" style="background:var(--purple-l);border-color:var(--purple)"><div id="boostConfirmText" style="font-size:14px;color:var(--text)"></div></div>
      <button class="btn btn-primary w-full" style="border-radius:999px" onclick="confirmBoost()">Confirm Boost 🚀</button>
    </div>
  </div>
</div>

<!-- Tip modal -->
<div class="modal-overlay" id="tip-modal">
  <div class="modal"><div class="modal-handle"></div><div class="modal-title" id="tipTitle">💸 Send Tip</div>
    <input type="hidden" id="tipPostId"/>
    <p style="font-size:13px;color:var(--text2);margin-bottom:14px">Balance: <?= number_format((int)($wallet['points_balance']??0)) ?> pts</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      <?php foreach([10,25,50,100,250] as $a): ?>
      <button class="btn btn-secondary btn-sm" style="border-radius:999px" onclick="document.getElementById('tipAmt').value=<?=$a?>"><?=$a?> pts</button>
      <?php endforeach; ?>
    </div>
    <div class="input-group mb-4"><label class="input-label">Custom amount</label><input class="input" type="number" id="tipAmt" placeholder="Points" min="1"/></div>
    <button class="btn btn-primary w-full" style="border-radius:999px" onclick="sendTip()">Send Tip 💸</button>
  </div>
</div>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
window.VOXU_MAX_RECORD_SECS = <?= ($recLimit===0?99999:$recLimit) ?>;
const ALLOWED_EMOJIS = ['👍','❤️','🔥','😂','😮','😢','🎉','💯','🙏','👏','🎙','⚡'];

/* ── THEME ─────────────────────────── */
function toggleTheme() {
  const next = document.body.classList.contains('theme-light') ? 'dark' : 'light';
  document.body.className = 'theme-' + next;
  document.cookie = 'voxu_theme=' + next + ';path=/;max-age=31536000';
}

/* ── LANG ──────────────────────────── */
document.addEventListener('click', e => {
  if (!e.target.closest('[style*="position:relative"]') || !e.target.closest('#langBtn')) {
    const m = document.getElementById('langMenu');
    if (m && !m.classList.contains('hidden')) m.classList.add('hidden');
  }
});

/* ── POST OPEN ─────────────────────── */
function openPost(id, e) { if (e.target.closest('button,a,.sk-voice-player,.sk-reaction-chip,.sk-add-reaction,.sk-emoji-picker')) return; window.location.href='/post/'+id; }

/* ── LIGHTBOX ──────────────────────── */
function openLightbox(src) {
  const d=document.createElement('div'); d.className='sk-lightbox';
  d.innerHTML=`<button class="sk-lightbox-close" onclick="this.parentNode.remove()">✕</button><img src="${src}" onclick="event.stopPropagation()"/>`;
  d.onclick=()=>d.remove(); document.body.appendChild(d);
}

/* ── EMOJI REACTIONS ────────────────── */
let openPickerPostId = null;
function toggleEmojiPicker(postId, btn) {
  document.querySelectorAll('.sk-emoji-picker').forEach(p=>p.remove());
  if (openPickerPostId===postId) { openPickerPostId=null; return; }
  openPickerPostId = postId;
  const picker = document.createElement('div');
  picker.className = 'sk-emoji-picker';
  picker.style.cssText = 'position:absolute;bottom:40px;left:0;';
  ALLOWED_EMOJIS.forEach(em => {
    const b=document.createElement('button'); b.textContent=em; b.type='button';
    b.onclick=()=>{ reactPost(postId,em); picker.remove(); openPickerPostId=null; };
    picker.appendChild(b);
  });
  btn.style.position='relative';
  btn.appendChild(picker);
  setTimeout(()=>document.addEventListener('click',()=>{picker.remove();openPickerPostId=null;},{once:true}),100);
}

async function reactPost(postId, emoji) {
  const res = await API.post('/posts/'+postId+'/react', {emoji});
  if (res?.success) {
    const container = document.getElementById('reactions-'+postId);
    if (!container) return;
    // Rebuild reaction chips
    container.innerHTML = res.reactions.map(r =>
      `<button class="sk-reaction-chip ${res.result?.emoji===r.emoji&&res.result?.action==='added'?'mine':''}" onclick="event.stopPropagation();reactPost(${postId},'${r.emoji}')">
        <span class="emoji">${r.emoji}</span><span class="sk-reaction-count">${r.cnt}</span>
      </button>`
    ).join('') + `<div class="sk-add-reaction" onclick="event.stopPropagation();toggleEmojiPicker(${postId},this)" title="React">+</div>`;
  }
}

/* ── ENERGY ────────────────────────── */
async function sendEnergy(id,btn) {
  if(btn.classList.contains('energized')){Toast.info('Already energized!');return;}
  const res=await API.post('/posts/'+id+'/energy',{amount:1});
  if(res?.success){btn.classList.add('energized');btn.querySelector('svg').setAttribute('fill','currentColor');const c=document.getElementById('ecnt-'+id);if(c)c.textContent=res.total_energy>0?Number(res.total_energy).toLocaleString():'';Toast.success('⚡ Energy sent!');}
}

/* ── FOLLOW ────────────────────────── */
async function followUser(id,btn){const res=await API.post('/follow',{user_id:id});if(res?.success){btn.textContent='Following';btn.className='btn btn-primary btn-sm';btn.style.borderRadius='999px';Toast.success('Following!');}}

/* ── DELETE POST ───────────────────── */
async function deletePost(id,el){if(!confirm('Delete this post?'))return;const res=await API.del('/posts/'+id);if(res?.success){el?.remove();Toast.success('Deleted');}}

/* ── REPLIES — Voice + Text ─────────── */
let activePost=null;
const ReplyVR=Object.create(VoiceRecorder);

function setReplyMode(mode) {
  const isVoice=mode==='voice';
  document.getElementById('replyPaneText').classList.toggle('hidden',isVoice);
  document.getElementById('replyPaneVoice').classList.toggle('hidden',!isVoice);
  document.getElementById('replyModeTextBtn').className='btn btn-sm '+(isVoice?'btn-secondary':'btn-primary');
  document.getElementById('replyModeVoiceBtn').className='btn btn-sm '+(isVoice?'btn-primary':'btn-secondary');
  document.getElementById('replyModeTextBtn').style.borderRadius='999px';
  document.getElementById('replyModeVoiceBtn').style.borderRadius='999px';
  if(isVoice&&!ReplyVR._inited){
    ReplyVR.init('replyRecCircle','replyRecTime','replyRecWave','submitReplyVoice');
    ReplyVR.initPreview('replyRecPreview','');
    ReplyVR._inited=true;
  }
}

async function openReplies(id) {
  activePost=id; Modal.open('replies-modal'); setReplyMode('text');
  document.getElementById('repliesList').innerHTML='<div style="text-align:center;padding:20px;color:var(--text3)">Loading…</div>';
  const data=await API.get('/posts/'+id+'/replies');
  if(!data?.replies?.length){document.getElementById('repliesList').innerHTML='<div style="text-align:center;padding:20px;color:var(--text3)">No replies yet. Be first!</div>';return;}
  document.getElementById('repliesList').innerHTML=data.replies.map(r=>{
    const init=(r.username||'?').substring(0,2).toUpperCase();
    const voiceEl=r.audio_url?`<div data-voice-player data-src="${r.audio_url}" style="display:flex;align-items:center;gap:8px;background:var(--bg3);border-radius:8px;padding:8px 10px;margin-top:6px"><button class="play-btn" style="width:28px;height:28px;flex-shrink:0"><svg width="11" height="11" fill="currentColor" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></button><div class="waveform" style="flex:1;height:22px">${Array.from({length:16},()=>`<div class="waveform-bar" style="height:${Math.round(Math.random()*80+10)}%"></div>`).join('')}</div><span style="font-size:11px;color:var(--text3)">${r.duration||0}s</span></div>`:'';
    return `<div style="display:flex;gap:10px;padding:12px 0;border-bottom:1px solid var(--border)"><div class="avatar avatar-sm" style="flex-shrink:0;font-size:10px">${init}</div><div style="flex:1;min-width:0"><div style="font-size:13px;font-weight:700;color:var(--text)">@${r.username}</div>${r.text?`<div style="font-size:14px;color:var(--text2);margin-top:3px">${r.text}</div>`:''}${voiceEl}<div style="font-size:11px;color:var(--text3);margin-top:4px">${r.created_at||''}</div></div></div>`;
  }).join('');
  document.querySelectorAll('#repliesList [data-voice-player]').forEach(el=>VoicePlayer.setup(el));
}

async function sendTextReply(){const txt=document.getElementById('replyInput').value.trim();if(!txt||!activePost)return;const res=await API.post('/posts/'+activePost+'/reply',{text:txt});if(res?.success){document.getElementById('replyInput').value='';Toast.success('Reply sent!');openReplies(activePost);}}

async function submitVoiceReply(){if(!ReplyVR.blob){Toast.error('Record first');return;}const btn=document.getElementById('submitReplyVoice');setLoading(btn,true);const ext=ReplyVR._ext||'webm';const fd=new FormData();fd.append('audio',ReplyVR.blob,'voice-reply.'+ext);fd.append('post_id',String(activePost));fd.append('duration',String(ReplyVR.duration));const csrf=getCsrfToken();let res;try{const raw=await fetch('/api/v1/voice/reply',{method:'POST',credentials:'same-origin',headers:csrf?{'X-CSRF-Token':csrf}:{},body:fd});res=await raw.json();}catch{res={success:false};}setLoading(btn,false);if(res?.success){Toast.success('Voice reply sent!');ReplyVR.blob=null;btn.disabled=true;openReplies(activePost);setReplyMode('text');}else{Toast.error(res?.message||'Upload failed');}}

/* ── BOOST ─────────────────────────── */
let boostData=null;
function openBoost(id){document.getElementById('boostPostId').value=id;document.getElementById('boostConfirm').classList.add('hidden');boostData=null;Modal.open('boost-modal');}
function selectBoost(pts,cash,label){boostData={pts,cash,label};document.getElementById('boostConfirmText').innerHTML=`<strong>${label}</strong> — ${pts>0?pts+' points':'$'+cash}`;document.getElementById('boostConfirm').classList.remove('hidden');}
async function confirmBoost(){if(!boostData)return;const id=document.getElementById('boostPostId').value;const res=await API.post('/posts/boost',{post_id:id,points:boostData.pts,cash:boostData.cash});if(res?.success){Toast.success('🚀 Boosted!');Modal.close('boost-modal');setTimeout(()=>location.reload(),800);}else{Toast.error(res?.message||'Boost failed');}}

/* ── TIP ───────────────────────────── */
function openTip(id){document.getElementById('tipPostId').value=id;Modal.open('tip-modal');}
async function sendTip(){const id=document.getElementById('tipPostId').value;const amt=parseInt(document.getElementById('tipAmt').value);if(!amt||amt<1){Toast.error('Enter an amount');return;}const res=await API.post('/tips/send',{post_id:id,amount:amt});if(res?.success){Toast.success('💸 Tip sent!');Modal.close('tip-modal');}else{Toast.error(res?.message||'Tip failed');}}

/* ── SHARE ─────────────────────────── */
function copyLink(path){navigator.clipboard?.writeText(window.location.origin+path).then(()=>Toast.success('Link copied!'));}

/* ── VOICE FORM ────────────────────── */
const coverInput=document.getElementById('coverImageInput');
let coverFile=null;
document.getElementById('voiceImgLabel')?.parentElement?.addEventListener('click',()=>coverInput.click());
coverInput?.addEventListener('change',e=>{if(e.target.files[0]){coverFile=e.target.files[0];const r=new FileReader();r.onload=ev=>{document.getElementById('voiceImagePreview').src=ev.target.result;document.getElementById('imagePreviewWrap').classList.remove('hidden');document.getElementById('voiceImgLabel').textContent=e.target.files[0].name;};r.readAsDataURL(coverFile);}});
function clearCoverImage(){coverFile=null;if(coverInput)coverInput.value='';document.getElementById('imagePreviewWrap').classList.add('hidden');document.getElementById('voiceImgLabel').textContent='Upload cover image';}

VoiceRecorder.init('recCircle','recTime','recWave','submitVoice');
VoiceRecorder.initPreview('recPreview','rerecordBtn');

document.getElementById('voiceForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const btn=document.getElementById('submitVoice');
  const title=document.getElementById('voiceTitle').value.trim();
  if(!title){Toast.error('Add a caption first');return;}
  setLoading(btn,true);
  const res=await VoiceRecorder.upload(title,{},null,coverFile);
  setLoading(btn,false);
  if(res?.success){Toast.success('Voice posted!');Modal.close('voice-modal');setTimeout(()=>location.reload(),800);}
});

/* ── VIDEO FORM ────────────────────── */
const videoInput = document.getElementById('videoFileInput');
let videoDuration = 0;
let _vidUrl = null;
document.getElementById('videoZoneLabel')?.addEventListener('click', () => videoInput?.click());
videoInput?.addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  document.getElementById('videoFileName').textContent = file.name;
  if (_vidUrl) URL.revokeObjectURL(_vidUrl);
  _vidUrl = URL.createObjectURL(file);
  const prev = document.getElementById('videoPreviewEl');
  prev.src = _vidUrl; prev.load();
  prev.addEventListener('loadedmetadata', function onMeta() {
    prev.removeEventListener('loadedmetadata', onMeta);
    videoDuration = Math.round(prev.duration) || 0;
    const info = document.getElementById('videoDurInfo');
    if (info) {
      info.style.color = videoDuration > 60 ? 'var(--danger)' : 'var(--green)';
      info.textContent = videoDuration > 60 ? '⚠ '+videoDuration+'s — exceeds 60s' : '✓ '+videoDuration+'s';
    }
    document.getElementById('videoPreviewWrap')?.classList.remove('hidden');
  }, {once:true});
});
document.getElementById('videoForm')?.addEventListener('submit', function(e) {
  e.preventDefault();
  const file  = videoInput?.files?.[0];
  const title = document.getElementById('videoTitle')?.value.trim();
  if (!file)  { Toast.error('Select a video file'); return; }
  if (!title) { Toast.error('Add a caption'); return; }
  if (videoDuration > 60) { Toast.error('Video must be 60 seconds or less'); return; }
  const btn = document.getElementById('submitVideo');
  setLoading(btn, true);
  document.getElementById('videoUploadProgress')?.classList.remove('hidden');
  const fd = new FormData();
  fd.append('audio', file, file.name);
  fd.append('title', title);
  fd.append('duration', String(videoDuration));
  fd.append('post_type', 'video');
  const csrf = getCsrfToken();
  const xhr  = new XMLHttpRequest();
  xhr.upload.addEventListener('progress', ev => {
    if (!ev.lengthComputable) return;
    const pct = Math.round(ev.loaded / ev.total * 100);
    const bar = document.getElementById('videoPBar');
    const txt = document.getElementById('videoPText');
    if (bar) bar.style.width = pct + '%';
    if (txt) txt.textContent  = 'Uploading… ' + pct + '%';
  });
  xhr.addEventListener('load', () => {
    setLoading(btn, false);
    try {
      const d = JSON.parse(xhr.responseText);
      if (d.success) { Toast.success('Video posted!'); Modal.close('video-modal'); setTimeout(() => location.reload(), 800); }
      else { Toast.error(d.message || 'Upload failed'); document.getElementById('videoUploadProgress')?.classList.add('hidden'); }
    } catch { Toast.error('Server error during upload'); }
  });
  xhr.addEventListener('error', () => { setLoading(btn, false); Toast.error('Network error — check connection'); });
  xhr.open('POST', '/api/v1/voice/create');
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
  if (csrf) xhr.setRequestHeader('X-CSRF-Token', csrf);
  xhr.send(fd);
});

/* ── SEARCH ────────────────────────── */
let searchTimer;
async function handleSearch(e){
  if(e.key==='Enter'){const q=e.target.value.trim();if(q.startsWith('#'))return location.href='?tab=hashtag&tag='+encodeURIComponent(q.slice(1));return;}
  clearTimeout(searchTimer);
  const q=e.target.value.trim().replace('@','');
  const r=document.getElementById('searchResults');
  if(q.length<2){r.style.display='none';return;}
  searchTimer=setTimeout(async()=>{const data=await API.get('/admin/search?q='+encodeURIComponent(q));if(!data?.results?.length){r.style.display='none';return;}r.innerHTML=data.results.slice(0,6).map(u=>`<div class="sk-search-result" onclick="window.location='/dashboard/profile.php?u=${u.username}'"><div class="avatar avatar-sm" style="flex-shrink:0;font-size:10px">${(u.username||'?').substring(0,2).toUpperCase()}</div><div style="font-size:14px;font-weight:600;color:var(--text)">@${u.username}</div></div>`).join('');r.style.display='block';},280);
}
document.addEventListener('click',e=>{if(!e.target.closest('.sk-nav-search'))document.getElementById('searchResults').style.display='none';});

function loadMore(p){window.location.href='?tab=<?= urlencode($tab) ?>&page='+p+(<?= $hashTag?'"&tag="+encodeURIComponent("'.clean($hashTag).'")':'"" '?>);}

function openStatus(id){window.location='/dashboard/status.php?view='+id;}

/* ── INIT ──────────────────────────── */
VoicePlayer.init();
VoxuI18n.init();
</script>
</body>
</html>
