<?php
/**
 * Voxu — User Profile Page · Extended info · Socimo-inspired
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/i18n.php';
requireAuth();

$me      = auth();
$myId    = (int)$me['id'];
$uParam  = sanitize($_GET['u'] ?? $me['username']);
$settings = getPlatformSettings();
$appName  = clean($settings['app_name'] ?? 'Voxu');
$theme    = getTheme();

try {
    $target = DB::first(
        "SELECT u.*, up.avatar, up.bio, up.website, up.cover_photo,
                up.occupation, up.company, up.city, up.country,
                up.education, up.skills, up.interests, up.relationship_status,
                up.gender, up.date_of_birth, up.twitter, up.instagram,
                up.facebook, up.linkedin, up.custom_url_slug
         FROM users u LEFT JOIN user_profiles up ON up.user_id=u.id
         WHERE u.username=? AND u.status='active'",
        [$uParam]
    );
} catch (Throwable) { $target = null; }

if (!$target) { http_response_code(404); die('<p style="padding:40px;color:#fff">User not found.</p>'); }

$targetId  = (int)$target['id'];
$isOwnProfile = $targetId === $myId;

try {
    $followers = DB::count('followers','following_id=?',[$targetId]);
    $following = DB::count('followers','follower_id=?',[$targetId]);
    $postCount = DB::count('posts','user_id=? AND status="active"',[$targetId]);
    $isFollowing = DB::count('followers','follower_id=? AND following_id=?',[$myId,$targetId]) > 0;
} catch (Throwable) { $followers=$following=$postCount=0; $isFollowing=false; }

try {
    $posts = DB::query(
        "SELECT p.*, u.username, up.avatar FROM posts p
         JOIN users u ON u.id=p.user_id LEFT JOIN user_profiles up ON up.user_id=p.user_id
         WHERE p.user_id=? AND p.status='active' ORDER BY p.created_at DESC LIMIT 20",
        [$targetId]
    );
} catch (Throwable) { $posts=[]; }

$tab = sanitize($_GET['tab'] ?? 'posts');
$plan = getUserPlan($targetId);
$age  = $target['date_of_birth'] ? floor((time()-strtotime($target['date_of_birth']))/31536000) : null;
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>" <?= isRtl()?'dir="rtl"':'' ?>>
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<meta name="csrf-token" content="<?= csrfToken() ?>"/>
<title>@<?= clean($target['username']) ?> — <?= $appName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="/assets/css/voxu.css"/>
</head>
<body class="theme-<?= clean($theme) ?>">

<nav class="sk-topnav">
  <a href="/dashboard/feed.php" class="sk-logo"><?= $appName ?><span class="dot">.</span></a>
  <div style="position:absolute;left:50%;transform:translateX(-50%);font-size:16px;font-weight:700;color:var(--text)">@<?= clean($target['username']) ?></div>
  <div class="sk-nav-actions">
    <a href="/dashboard/notifications.php" class="sk-nav-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></a>
    <a href="/dashboard/profile.php" style="flex-shrink:0"><div class="avatar avatar-sm"><?php if(!empty($me['avatar'])): ?><img src="<?= clean($me['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($me['username']) ?><?php endif; ?></div></a>
  </div>
</nav>

<div class="sk-layout" style="padding-top:var(--nav-h)">
  <main style="flex:1;min-width:0;max-width:680px;border-right:1px solid var(--border)">

    <!-- COVER PHOTO -->
    <div class="profile-cover">
      <?php if(!empty($target['cover_photo'])): ?>
        <img src="<?= clean($target['cover_photo']) ?>" alt="cover"/>
      <?php endif; ?>
      <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 40%,rgba(0,0,0,.5))"></div>
      <div class="profile-avatar-wrap">
        <div class="avatar avatar-xl <?= $isFollowing?'avatar-ring':'' ?>">
          <?php if(!empty($target['avatar'])): ?><img src="<?= clean($target['avatar']) ?>" alt="<?= clean($target['username']) ?>"/><?php else: ?><?= avatarInitials($target['username']) ?><?php endif; ?>
        </div>
      </div>
      <div class="profile-actions">
        <?php if($isOwnProfile): ?>
          <a href="/dashboard/settings.php" class="btn btn-secondary btn-sm" style="border-radius:999px">✏ Edit Profile</a>
        <?php else: ?>
          <button id="followBtn" onclick="toggleFollow(<?= $targetId ?>,this)" class="btn <?= $isFollowing?'btn-secondary':'btn-primary' ?> btn-sm" style="border-radius:999px"><?= $isFollowing?'Following':'Follow' ?></button>
          <a href="/dashboard/messages.php?new=<?= urlencode($target['username']) ?>" class="btn btn-secondary btn-sm" style="border-radius:999px">Message</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- PROFILE INFO -->
    <div class="profile-body">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <div>
          <div class="profile-name"><?= clean($target['username']) ?> <?= planBadge($plan) ?></div>
          <div class="profile-handle">@<?= clean($target['username']) ?><?php if($target['is_verified']): ?> <span style="color:var(--blue)">✓</span><?php endif; ?></div>
        </div>
      </div>
      <?php if($target['bio']): ?><div class="profile-bio"><?= clean($target['bio']) ?></div><?php endif; ?>

      <!-- EXTENDED META INFO -->
      <div class="profile-meta">
        <?php
        $metaItems = [];
        if($target['occupation']||$target['company']) $metaItems[] = ['briefcase','<path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>',  trim(($target['occupation']?clean($target['occupation']):'').($target['company']?' at '.clean($target['company']):''))];
        if($target['education']) $metaItems[] = ['book','<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 0 3-3h7z"/>',clean($target['education'])];
        if($target['city']||$target['country']) $metaItems[] = ['map-pin','<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',trim(($target['city']?clean($target['city']):'').($target['country']?', '.clean($target['country']):''))];
        if($target['website']) $metaItems[] = ['link','<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',"<a href='".clean($target['website'])."' target='_blank' style='color:var(--purple)'>".clean(parse_url($target['website'],PHP_URL_HOST))."</a>"];
        if($age) $metaItems[] = ['user','<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',"{$age} years old".($target['gender']&&$target['gender']!='prefer_not'?' · '.ucfirst(str_replace('_',' ',$target['gender'])):'')];
        if($target['relationship_status']&&$target['relationship_status']!='prefer_not') $metaItems[] = ['heart','<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',ucfirst(str_replace('_',' ',$target['relationship_status']))];
        foreach($metaItems as [$key,$svg,$label]):
        ?>
        <div class="profile-meta-item">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $svg ?></svg>
          <?= $label ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- SOCIAL LINKS -->
      <?php
      $socials = [];
      if($target['twitter'])   $socials[] = ['https://twitter.com/'.ltrim($target['twitter'],'@'),  '𝕏 '.$target['twitter'],'#000'];
      if($target['instagram'])  $socials[] = ['https://instagram.com/'.ltrim($target['instagram'],'@'),'IG '.$target['instagram'],'#E1306C'];
      if($target['facebook'])   $socials[] = ['https://facebook.com/'.$target['facebook'],'FB '.$target['facebook'],'#1877F2'];
      if($target['linkedin'])   $socials[] = ['https://linkedin.com/in/'.$target['linkedin'],'in '.$target['linkedin'],'#0A66C2'];
      if(!empty($socials)):
      ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <?php foreach($socials as [$url,$label,$color]): ?>
        <a href="<?= $url ?>" target="_blank" rel="noopener" class="badge" style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>44"><?= clean($label) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- SKILLS / INTERESTS -->
      <?php if($target['skills']||$target['interests']): ?>
      <div style="margin-top:14px">
        <?php if($target['skills']): ?>
        <div style="margin-bottom:8px">
          <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:6px">Skills</div>
          <div style="display:flex;flex-wrap:wrap;gap:6px">
            <?php foreach(array_slice(explode(',',$target['skills']),0,10) as $skill): ?>
            <span class="badge badge-purple"><?= clean(trim($skill)) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if($target['interests']): ?>
        <div>
          <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:6px">Interests</div>
          <div style="display:flex;flex-wrap:wrap;gap:6px">
            <?php foreach(array_slice(explode(',',$target['interests']),0,10) as $int): ?>
            <span class="badge badge-muted"><?= clean(trim($int)) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- STATS BAR -->
    <div class="profile-stats">
      <div><div class="profile-stat-num"><?= number_format($postCount) ?></div><div class="profile-stat-lbl">Posts</div></div>
      <div style="cursor:pointer" onclick="showFollowers(<?= $targetId ?>)"><div class="profile-stat-num"><?= number_format($followers) ?></div><div class="profile-stat-lbl">Followers</div></div>
      <div><div class="profile-stat-num"><?= number_format($following) ?></div><div class="profile-stat-lbl">Following</div></div>
    </div>

    <!-- PROFILE TABS -->
    <div class="profile-tabs">
      <div class="profile-tab <?= $tab==='posts'?'active':'' ?>" onclick="switchTab('posts')">Posts</div>
      <div class="profile-tab <?= $tab==='voice'?'active':'' ?>" onclick="switchTab('voice')">🎙 Voice</div>
      <div class="profile-tab <?= $tab==='about'?'active':'' ?>" onclick="switchTab('about')">About</div>
    </div>

    <!-- POSTS TAB -->
    <div id="tabPosts" <?= $tab==='about'?'style="display:none"':'' ?>>
      <?php $filteredPosts = $tab==='voice' ? array_filter($posts,fn($p)=>!empty($p['audio_url'])) : $posts;
      if(empty($filteredPosts)): ?>
        <div class="sk-empty"><div class="sk-empty-icon">🎙</div><div class="sk-empty-title">No posts yet</div></div>
      <?php else: foreach($filteredPosts as $p): ?>
      <article class="sk-post" onclick="window.location='/post/<?= $p['id'] ?>'" style="cursor:pointer">
        <div class="avatar"><img src="<?= $p['avatar']?clean($p['avatar']):'data:image/svg+xml,' ?>" onerror="this.style.display='none'" alt="" loading="lazy"/><?= !$p['avatar']?avatarInitials($p['username']):'' ?></div>
        <div class="sk-post-right">
          <div class="sk-post-header">
            <div class="sk-post-author"><span class="sk-display-name"><?= clean($p['username']) ?></span><span class="sk-dot">·</span><span class="sk-time"><?= timeAgo($p['created_at']) ?></span></div>
          </div>
          <?php if($p['title']): ?><div class="sk-post-text"><?= linkifyHashtags($p['title']) ?></div><?php endif; ?>
          <?php if(!empty($p['image_url'])): ?><div class="sk-post-media"><img src="<?= clean($p['image_url']) ?>" alt="" loading="lazy"/></div><?php endif; ?>
          <?php if(!empty($p['audio_url'])): ?>
          <div class="sk-voice-player" onclick="event.stopPropagation()" data-voice-player data-src="<?= clean($p['audio_url']) ?>">
            <button class="play-btn" style="width:34px;height:34px;flex-shrink:0"><svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></button>
            <div class="sk-voice-bars"><?php for($i=0;$i<28;$i++): ?><div class="waveform-bar" style="height:<?=rand(15,100)?>%"></div><?php endfor; ?></div>
            <span style="font-size:12px;color:var(--text3)"><?= gmdate('i:s',(int)($p['duration']??0)) ?></span>
          </div>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; endif; ?>
    </div>

    <!-- ABOUT TAB -->
    <?php if($tab==='about'): ?>
    <div id="tabAbout" style="padding:20px">
      <div class="card" style="margin-bottom:14px">
        <div class="card-title">About <?= clean($target['username']) ?></div>
        <?php if($target['bio']): ?><p style="font-size:14px;color:var(--text2);line-height:1.7;margin-bottom:12px"><?= clean($target['bio']) ?></p><?php endif; ?>
        <div style="display:flex;flex-direction:column;gap:10px">
          <?php
          $aboutFields = [
            ['Occupation',$target['occupation']??null],
            ['Company',$target['company']??null],
            ['Education',$target['education']??null],
            ['Location',trim(($target['city']??'').', '.($target['country']??''),', ')],
            ['Gender',$target['gender']&&$target['gender']!='prefer_not'?ucfirst(str_replace('_',' ',$target['gender'])):null],
            ['Relationship',($target['relationship_status']&&$target['relationship_status']!='prefer_not')?ucfirst(str_replace('_',' ',$target['relationship_status'])):null],
            ['Website',$target['website']??null],
          ];
          foreach($aboutFields as [$lbl,$val]):
            if(!$val) continue;
          ?>
          <div style="display:flex;gap:10px;font-size:14px">
            <span style="color:var(--text3);min-width:110px"><?= $lbl ?></span>
            <span style="color:var(--text);font-weight:500"><?= $lbl==='Website'?"<a href='".clean($val)."' target='_blank' style='color:var(--purple)'>".clean($val)."</a>":clean($val) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if($target['skills']): ?>
      <div class="card" style="margin-bottom:14px">
        <div class="card-title">Skills</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <?php foreach(explode(',',$target['skills']) as $s): ?><span class="badge badge-purple" style="font-size:13px"><?= clean(trim($s)) ?></span><?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </main>

  <!-- RIGHT: Who else to follow / plan info -->
  <aside class="sk-aside">
    <div class="sk-wallet-widget" style="margin-bottom:16px">
      <div style="font-size:12px;color:var(--text2);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Plan</div>
      <div style="font-size:20px;font-weight:800;color:#fff"><?= clean($plan['name']??'Free') ?></div>
      <div style="font-size:13px;color:var(--text2);margin-top:4px"><?= number_format((int)($plan['max_recording_secs']??180)/60) ?> min recording limit</div>
      <?php if(($plan['slug']??'free')==='free'): ?>
      <a href="/dashboard/premium.php" class="btn btn-primary btn-sm" style="border-radius:999px;width:100%;margin-top:10px;font-size:13px">⭐ Upgrade Plan</a>
      <?php endif; ?>
    </div>
  </aside>
</div>

<nav class="bottom-nav">
  <a href="/dashboard/feed.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>Home</a>
  <a href="/dashboard/status.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/></svg>Status</a>
  <a href="/dashboard/messages.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>DMs</a>
  <a href="/dashboard/wallet.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Wallet</a>
  <a href="/dashboard/profile.php" class="bottom-nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Me</a>
</nav>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
function switchTab(t){window.location.href='?u=<?= urlencode($uParam) ?>&tab='+t;}
async function toggleFollow(id,btn){const res=await API.post('/follow',{user_id:id});if(res?.success){const isNow=btn.textContent==='Follow';btn.textContent=isNow?'Following':'Follow';btn.className='btn '+(isNow?'btn-secondary':'btn-primary')+' btn-sm';btn.style.borderRadius='999px';}}
VoicePlayer.init();
VoxuI18n.init();
</script>
</body></html>
