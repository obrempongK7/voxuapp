<?php
/**
 * Voxu — About Us Page
 * Content editable from Admin → Pages
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$settings = getPlatformSettings();
$appName  = $settings['app_name']    ?? 'Voxu';
$tagline  = $settings['app_tagline'] ?? 'Speak. Be Seen. Earn.';
$me       = auth();

// Editable content blocks (managed from admin/pages.php)
$aboutHero    = $settings['page_about_hero']    ?? "We built {$appName} because voices deserve to be heard — and rewarded.";
$aboutMission = $settings['page_about_mission'] ?? "Our mission is to give every person a platform to speak freely, connect genuinely, and earn from their authentic voice and presence.";
$aboutVision  = $settings['page_about_vision']  ?? "A world where attention has real value — where every view, every click, and every conversation creates tangible opportunity for the people involved.";
$foundedYear  = $settings['page_about_founded'] ?? '2024';
$teamDesc     = $settings['page_about_team']    ?? "We are a small, passionate team of builders, creators, and communicators who believe the next generation of social media must reward its users — not exploit them.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>About Us — <?= clean($appName) ?></title>
  <meta name="description" content="Learn about <?= clean($appName) ?> — <?= clean($tagline) ?>"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
  <style>
    body{background:var(--bg)}
    .about-page{padding-top:64px}

    /* HERO */
    .ab-hero{min-height:60vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:80px 24px 60px;position:relative;overflow:hidden}
    .ab-hero-bg{position:absolute;inset:0;background:radial-gradient(ellipse 70% 70% at 50% 50%,rgba(108,59,255,.1) 0%,transparent 70%)}
    .ab-hero-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(108,59,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(108,59,255,.04) 1px,transparent 1px);background-size:48px 48px;mask-image:radial-gradient(ellipse 80% 80% at 50% 50%,black,transparent)}
    .ab-hero-inner{position:relative;z-index:1;max-width:760px;margin:0 auto}
    .ab-label{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.12em;color:var(--purple);margin-bottom:14px}
    .ab-title{font-family:'Poppins',sans-serif;font-size:clamp(32px,6vw,58px);font-weight:800;color:#fff;line-height:1.15;margin-bottom:20px}
    .ab-title span{color:var(--purple)}
    .ab-desc{font-size:17px;color:var(--text2);line-height:1.75;max-width:600px;margin:0 auto}

    /* SECTIONS */
    .ab-section{max-width:1000px;margin:0 auto;padding:60px 24px}
    .ab-section-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.12em;color:var(--purple);margin-bottom:10px}
    .ab-section-title{font-family:'Poppins',sans-serif;font-size:clamp(24px,4vw,38px);font-weight:800;color:#fff;margin-bottom:16px;line-height:1.2}
    .ab-section-body{font-size:16px;color:var(--text2);line-height:1.8;max-width:680px}

    /* MISSION / VISION SPLIT */
    .mv-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:48px}
    .mv-card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:32px;position:relative;overflow:hidden}
    .mv-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--c,var(--purple))}
    .mv-icon{font-size:36px;margin-bottom:16px}
    .mv-title{font-size:20px;font-weight:700;color:#fff;margin-bottom:10px}
    .mv-body{font-size:15px;color:var(--text2);line-height:1.75}

    /* VALUES */
    .values-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:40px}
    .value-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:22px;transition:.2s}
    .value-card:hover{border-color:rgba(108,59,255,.4);transform:translateY(-3px)}
    .value-icon{font-size:28px;margin-bottom:10px}
    .value-title{font-size:15px;font-weight:700;color:#fff;margin-bottom:6px}
    .value-body{font-size:13px;color:var(--text2);line-height:1.65}

    /* STATS BAND */
    .stats-band{background:var(--bg2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:48px 24px}
    .stats-inner{max-width:900px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:24px;text-align:center}
    .stat-num{font-family:'Poppins',sans-serif;font-size:36px;font-weight:800;color:#fff;margin-bottom:4px}
    .stat-num span{color:var(--purple)}
    .stat-lbl{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--text2)}

    /* TEAM SECTION */
    .team-section{background:var(--bg2);padding:60px 24px;border-top:1px solid var(--border)}
    .team-inner{max-width:900px;margin:0 auto}

    /* APP DOWNLOAD */
    .app-band{background:linear-gradient(135deg,#13103a,var(--bg2));border-top:1px solid rgba(108,59,255,.2);border-bottom:1px solid rgba(108,59,255,.2);padding:60px 24px;text-align:center}
    .app-band-inner{max-width:600px;margin:0 auto}
    .app-band-title{font-family:'Poppins',sans-serif;font-size:clamp(22px,4vw,36px);font-weight:800;color:#fff;margin-bottom:10px}
    .app-band-desc{font-size:15px;color:var(--text2);margin-bottom:28px}
    .app-btns{display:flex;justify-content:center;gap:14px;flex-wrap:wrap}
    .app-btn{display:inline-flex;align-items:center;gap:10px;padding:12px 22px;background:var(--bg3);border:1px solid var(--border2);border-radius:12px;color:#fff;text-decoration:none;transition:.2s;min-width:150px}
    .app-btn:hover{background:var(--purple-l);border-color:var(--purple)}
    .app-btn-icon{font-size:22px;flex-shrink:0}
    .app-btn-text{text-align:left}
    .app-btn-sub{font-size:10px;color:var(--text2)}
    .app-btn-name{font-size:15px;font-weight:700}

    /* CTA */
    .ab-cta{text-align:center;padding:80px 24px;position:relative;overflow:hidden}
    .ab-cta::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 60% 60% at 50% 50%,rgba(108,59,255,.08),transparent)}
    .ab-cta-title{font-family:'Poppins',sans-serif;font-size:clamp(28px,5vw,48px);font-weight:800;color:#fff;position:relative;z-index:1;margin-bottom:12px}
    .ab-cta-title span{color:var(--purple)}
    .ab-cta-sub{font-size:15px;color:var(--text2);max-width:440px;margin:0 auto 28px;position:relative;z-index:1}

    @media(max-width:700px){.mv-grid{grid-template-columns:1fr}}
  </style>
</head>
<body class="about-page">

<!-- NAV -->
<nav style="position:fixed;top:0;left:0;right:0;height:64px;background:rgba(11,11,15,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);z-index:200;display:flex;align-items:center;padding:0 24px;gap:16px">
  <a href="/" style="font-family:'Poppins',sans-serif;font-size:22px;font-weight:800;color:#fff;text-decoration:none">
    Vo<span style="color:var(--purple)">xu</span>
  </a>
  <nav style="display:flex;gap:4px;margin-left:auto;margin-right:auto">
    <a href="/"          style="padding:7px 14px;border-radius:8px;font-size:14px;color:var(--text2);transition:.2s;text-decoration:none">Home</a>
    <a href="/about.php" style="padding:7px 14px;border-radius:8px;font-size:14px;color:#fff;background:rgba(255,255,255,.07);text-decoration:none">About</a>
    <a href="/#earn"     style="padding:7px 14px;border-radius:8px;font-size:14px;color:var(--text2);transition:.2s;text-decoration:none">Earn</a>
    <a href="/contact.php" style="padding:7px 14px;border-radius:8px;font-size:14px;color:var(--text2);transition:.2s;text-decoration:none">Contact</a>
  </nav>
  <div style="display:flex;gap:10px">
    <?php if ($me): ?>
      <a href="/dashboard/" class="btn btn-primary btn-sm">Dashboard</a>
    <?php else: ?>
      <a href="/auth/login.php"    class="btn btn-secondary btn-sm">Log In</a>
      <a href="/auth/register.php" class="btn btn-primary btn-sm">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<!-- HERO -->
<section class="ab-hero">
  <div class="ab-hero-bg"></div>
  <div class="ab-hero-grid"></div>
  <div class="ab-hero-inner">
    <div class="ab-label">Our Story</div>
    <h1 class="ab-title">About <span><?= clean($appName) ?></span></h1>
    <p class="ab-desc"><?= clean($aboutHero) ?></p>
  </div>
</section>

<!-- MISSION & VISION -->
<div style="background:var(--bg)">
  <div class="ab-section">
    <div class="mv-grid">
      <div class="mv-card" style="--c:var(--purple)">
        <div class="mv-icon">🎯</div>
        <div class="mv-title">Our Mission</div>
        <div class="mv-body"><?= clean($aboutMission) ?></div>
      </div>
      <div class="mv-card" style="--c:var(--blue)">
        <div class="mv-icon">🔭</div>
        <div class="mv-title">Our Vision</div>
        <div class="mv-body"><?= clean($aboutVision) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- STATS -->
<?php
$totalUsers   = DB::count('users');
$totalPosts   = DB::count('posts');
$totalStatuses= DB::count('status_posts');
?>
<div class="stats-band">
  <div class="stats-inner">
    <div>
      <div class="stat-num"><?= number_format($totalUsers) ?><span>+</span></div>
      <div class="stat-lbl">Registered Users</div>
    </div>
    <div>
      <div class="stat-num"><?= number_format($totalPosts) ?><span>+</span></div>
      <div class="stat-lbl">Voice Posts</div>
    </div>
    <div>
      <div class="stat-num"><?= number_format($totalStatuses) ?><span>+</span></div>
      <div class="stat-lbl">Statuses Posted</div>
    </div>
    <div>
      <div class="stat-num"><?= $foundedYear ?></div>
      <div class="stat-lbl">Year Founded</div>
    </div>
  </div>
</div>

<!-- VALUES -->
<div style="background:var(--bg)">
  <div class="ab-section">
    <div class="ab-section-label">What We Stand For</div>
    <h2 class="ab-section-title">Our Core Values</h2>
    <div class="values-grid">
      <?php
      $values = [
        ['🎙','Voice First',       'Every person deserves a platform to speak. We put voice at the center of every interaction.'],
        ['💰','Earn Fairly',       'Your attention and effort have real value. We ensure you get a meaningful share of what you generate.'],
        ['🛡','Safe & Honest',     'We build with transparency. No hidden algorithms, no invisible data exploitation.'],
        ['⚡','Move Fast',         'We ship quickly, iterate often, and listen closely to what our community needs.'],
        ['🌍','Inclusive',         'Built for mobile-first, low-bandwidth users everywhere — especially in emerging markets.'],
        ['🤝','Community-Driven',  'Our users shape our product. Their feedback determines our roadmap.'],
      ];
      foreach ($values as $v):
      ?>
      <div class="value-card">
        <div class="value-icon"><?= $v[0] ?></div>
        <div class="value-title"><?= $v[1] ?></div>
        <div class="value-body"><?= $v[2] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- TEAM -->
<div class="team-section">
  <div class="team-inner">
    <div class="ab-section-label">The People Behind Voxu</div>
    <h2 class="ab-section-title">Our Team</h2>
    <p class="ab-section-body"><?= clean($teamDesc) ?></p>
    <div style="margin-top:28px;padding:20px 24px;background:var(--card);border:1px solid var(--border);border-radius:12px;display:inline-flex;align-items:center;gap:14px">
      <span style="font-size:32px">👨‍💻</span>
      <div>
        <div style="font-size:15px;font-weight:600;color:#fff">Want to join us?</div>
        <div style="font-size:13px;color:var(--text2)">We're always looking for talented people. <a href="/contact.php" style="color:var(--purple)">Reach out →</a></div>
      </div>
    </div>
  </div>
</div>

<!-- APP DOWNLOAD -->
<?php
$iosLink     = $settings['app_link_ios']     ?? '';
$androidLink = $settings['app_link_android'] ?? '';
$pwaLink     = APP_URL . '/dashboard/';
if ($iosLink || $androidLink):
?>
<div class="app-band">
  <div class="app-band-inner">
    <div class="app-band-title">Get the <?= clean($appName) ?> App</div>
    <p class="app-band-desc">Available on iOS, Android, and as a Progressive Web App — right from your browser.</p>
    <div class="app-btns">
      <?php if ($iosLink): ?>
      <a href="<?= clean($iosLink) ?>" target="_blank" class="app-btn">
        <span class="app-btn-icon">🍎</span>
        <div class="app-btn-text">
          <div class="app-btn-sub">Download on the</div>
          <div class="app-btn-name">App Store</div>
        </div>
      </a>
      <?php endif; ?>
      <?php if ($androidLink): ?>
      <a href="<?= clean($androidLink) ?>" target="_blank" class="app-btn">
        <span class="app-btn-icon">🤖</span>
        <div class="app-btn-text">
          <div class="app-btn-sub">Get it on</div>
          <div class="app-btn-name">Google Play</div>
        </div>
      </a>
      <?php endif; ?>
      <a href="<?= clean($pwaLink) ?>" class="app-btn">
        <span class="app-btn-icon">🌐</span>
        <div class="app-btn-text">
          <div class="app-btn-sub">Open as</div>
          <div class="app-btn-name">Web App (PWA)</div>
        </div>
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- CTA -->
<div class="ab-cta">
  <h2 class="ab-cta-title">Ready to <span>Speak & Earn?</span></h2>
  <p class="ab-cta-sub">Join <?= number_format($totalUsers) ?>+ users already on <?= clean($appName) ?>. Free to join, free to earn.</p>
  <a href="/auth/register.php" class="btn btn-primary btn-lg" style="display:inline-flex">Create Free Account →</a>
</div>

<!-- FOOTER -->
<footer style="border-top:1px solid var(--border);padding:28px 24px;text-align:center">
  <div style="font-size:13px;color:var(--text3)">
    © <?= date('Y') ?> <?= clean($appName) ?> ·
    <a href="/privacy.php" style="color:var(--text2);text-decoration:none;margin:0 6px">Privacy</a>·
    <a href="/terms.php"   style="color:var(--text2);text-decoration:none;margin:0 6px">Terms</a>·
    <a href="/contact.php" style="color:var(--text2);text-decoration:none;margin:0 6px">Contact</a>
  </div>
</footer>
</body>
</html>
