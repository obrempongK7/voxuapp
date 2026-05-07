<?php
// @author  Jcode | ObrempongK
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect logged-in users to dashboard
if (auth()) redirect('/dashboard/');

$settings = getPlatformSettings();
$appName  = $settings['app_name']    ?? 'Voxu';
$tagline  = $settings['app_tagline'] ?? 'Speak. Be Seen. Earn.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= clean($appName) ?> — Share your voice, post your status, and earn from every interaction." />
  <title><?= clean($appName) ?> — <?= clean($tagline) ?></title>
  <link rel="manifest" href="/manifest.json" />
  <meta name="theme-color" content="#6C3BFF" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet" />
  <style>
    :root{--bg:#0B0B0F;--bg2:#1A1A22;--purple:#6C3BFF;--purple-d:#5A30D0;--blue:#00D1FF;--green:#00FF9C;--text:#fff;--text2:#A0A0B0;--border:rgba(255,255,255,0.08)}
    *{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;overflow-x:hidden}
    a{color:inherit;text-decoration:none}
    .container{max-width:1100px;margin:0 auto;padding:0 24px}

    /* NAV */
    nav{position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(11,11,15,0.88);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);height:64px;display:flex;align-items:center}
    .nav-inner{display:flex;align-items:center;gap:16px;width:100%;max-width:1100px;margin:0 auto;padding:0 24px}
    .nav-logo{font-family:'Poppins',sans-serif;font-size:24px;font-weight:800;letter-spacing:-0.02em}
    .nav-logo span{color:var(--purple)}
    .nav-links{display:flex;gap:6px;margin:0 auto}
    .nav-links a{padding:7px 14px;border-radius:8px;font-size:14px;color:var(--text2);transition:.2s}
    .nav-links a:hover{color:#fff;background:rgba(255,255,255,0.06)}
    .nav-cta{display:flex;gap:10px}
    .btn-nav{padding:9px 20px;border-radius:8px;font-size:14px;font-weight:600;transition:.2s;cursor:pointer;border:none;font-family:inherit}
    .btn-outline{background:transparent;color:#fff;border:1px solid rgba(255,255,255,0.2)}
    .btn-outline:hover{background:rgba(255,255,255,0.06)}
    .btn-fill{background:var(--purple);color:#fff}
    .btn-fill:hover{background:var(--purple-d)}

    /* HERO */
    .hero{min-height:100vh;display:flex;align-items:center;position:relative;overflow:hidden;padding:80px 0 60px}
    .hero-bg{position:absolute;inset:0;background:radial-gradient(ellipse 70% 70% at 60% 50%,rgba(108,59,255,0.12) 0%,transparent 70%),radial-gradient(ellipse 40% 40% at 20% 80%,rgba(0,209,255,0.06) 0%,transparent 60%)}
    .hero-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(108,59,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(108,59,255,0.04) 1px,transparent 1px);background-size:48px 48px;mask-image:radial-gradient(ellipse 80% 80% at 50% 50%,black,transparent)}
    .hero-inner{display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:0 24px;width:100%}
    .hero-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(108,59,255,0.12);border:1px solid rgba(108,59,255,0.3);border-radius:20px;padding:6px 14px;font-size:12px;font-weight:600;color:var(--purple);text-transform:uppercase;letter-spacing:.06em;margin-bottom:20px}
    .hero-title{font-family:'Poppins',sans-serif;font-size:clamp(40px,5vw,68px);font-weight:800;line-height:1.1;letter-spacing:-0.02em;margin-bottom:20px}
    .hero-title .highlight{color:var(--purple)}
    .hero-title .hl2{background:linear-gradient(90deg,var(--blue),var(--green));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
    .hero-desc{font-size:16px;color:var(--text2);line-height:1.7;margin-bottom:32px;max-width:480px}
    .hero-btns{display:flex;gap:12px;flex-wrap:wrap}
    .btn-hero-primary{padding:14px 28px;background:var(--purple);color:#fff;border-radius:10px;font-size:16px;font-weight:700;transition:.2s;display:inline-flex;align-items:center;gap:8px}
    .btn-hero-primary:hover{background:var(--purple-d);transform:translateY(-2px);box-shadow:0 8px 30px rgba(108,59,255,0.4)}
    .btn-hero-secondary{padding:14px 28px;background:transparent;color:#fff;border:1px solid rgba(255,255,255,0.15);border-radius:10px;font-size:16px;font-weight:600;transition:.2s}
    .btn-hero-secondary:hover{background:rgba(255,255,255,0.06)}
    .hero-trust{display:flex;align-items:center;gap:10px;margin-top:24px;font-size:13px;color:var(--text2)}
    .trust-dots{display:flex}
    .trust-dots span{width:28px;height:28px;border-radius:50%;background:var(--bg2);border:2px solid var(--bg);margin-left:-8px;font-size:11px;display:flex;align-items:center;justify-content:center}
    /* PHONE MOCKUP */
    .hero-visual{display:flex;justify-content:center}
    .phone{width:240px;background:#0d0d14;border-radius:36px;border:6px solid #2a2a3a;box-shadow:0 40px 80px rgba(0,0,0,0.6),0 0 0 1px rgba(255,255,255,0.04);overflow:hidden;position:relative}
    .phone-screen{padding:16px;background:var(--bg)}
    .phone-notch{width:80px;height:20px;background:#0d0d14;border-radius:0 0 14px 14px;margin:0 auto -4px}
    .phone-status{display:flex;justify-content:space-between;align-items:center;padding:8px 4px 12px;font-size:10px;color:var(--text2)}
    .phone-card{background:#16161E;border-radius:12px;padding:12px;margin-bottom:8px;border:1px solid rgba(255,255,255,0.06)}
    .phone-card-row{display:flex;align-items:center;gap:8px}
    .phone-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--purple),var(--blue));flex-shrink:0}
    .phone-name{font-size:10px;font-weight:600}
    .phone-time{font-size:9px;color:var(--text2)}
    .phone-wave{display:flex;align-items:center;gap:1.5px;height:24px;padding:4px 0;margin:6px 0}
    .phone-wave span{width:3px;background:var(--purple);border-radius:2px;flex:1}
    .phone-energy{font-size:9px;color:var(--green);font-weight:600}
    .phone-bottom{display:flex;justify-content:space-around;padding:10px 4px 0;border-top:1px solid rgba(255,255,255,0.06)}
    .phone-nav-dot{width:5px;height:5px;border-radius:50%;background:var(--text2)}
    .phone-nav-dot.active{background:var(--purple)}
    .glow-purple{position:absolute;width:180px;height:180px;background:radial-gradient(var(--purple),transparent);opacity:.15;border-radius:50%;top:-40px;right:-40px;pointer-events:none}

    /* SECTIONS */
    section{padding:80px 0}
    .section-label{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:var(--purple);margin-bottom:10px}
    .section-title{font-family:'Poppins',sans-serif;font-size:clamp(28px,4vw,44px);font-weight:800;line-height:1.15;margin-bottom:14px}
    .section-desc{font-size:16px;color:var(--text2);max-width:560px;line-height:1.7}
    .section-center{text-align:center}
    .section-center .section-desc{margin:0 auto}

    /* HOW IT WORKS */
    .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:48px;position:relative}
    .steps::before{content:'';position:absolute;top:32px;left:16%;right:16%;height:1px;background:linear-gradient(90deg,transparent,var(--purple),transparent);z-index:0}
    .step-card{background:#16161E;border:1px solid var(--border);border-radius:16px;padding:28px 22px;text-align:center;position:relative;z-index:1;transition:.3s}
    .step-card:hover{border-color:rgba(108,59,255,0.4);transform:translateY(-4px)}
    .step-num{width:52px;height:52px;background:var(--purple);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Poppins',sans-serif;font-size:22px;font-weight:800;margin:0 auto 16px}
    .step-title{font-size:18px;font-weight:700;margin-bottom:8px}
    .step-desc{font-size:14px;color:var(--text2);line-height:1.6}

    /* FEATURES */
    .features-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:48px}
    .feature-card{background:#16161E;border:1px solid var(--border);border-radius:20px;padding:32px;transition:.3s;position:relative;overflow:hidden}
    .feature-card::before{content:'';position:absolute;inset:0;background:var(--feat-bg,radial-gradient(ellipse 60% 60% at 80% 20%,rgba(108,59,255,0.08),transparent));pointer-events:none}
    .feature-card:hover{border-color:rgba(108,59,255,0.3);transform:translateY(-4px);box-shadow:0 16px 48px rgba(0,0,0,0.4)}
    .feat-icon{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:26px;margin-bottom:20px}
    .feat-title{font-size:22px;font-weight:700;margin-bottom:12px}
    .feat-list{list-style:none;display:flex;flex-direction:column;gap:10px;margin-top:16px}
    .feat-list li{display:flex;align-items:flex-start;gap:10px;font-size:14px;color:var(--text2)}
    .feat-list li::before{content:'✓';color:var(--green);font-weight:700;flex-shrink:0;margin-top:1px}

    /* EARNINGS */
    .earn-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-top:40px}
    .earn-card{background:#16161E;border:1px solid var(--border);border-radius:16px;padding:20px;text-align:center;transition:.3s}
    .earn-card:hover{border-color:rgba(0,255,156,0.3);transform:translateY(-3px)}
    .earn-icon{font-size:32px;margin-bottom:10px}
    .earn-label{font-size:14px;font-weight:600;color:#fff;margin-bottom:4px}
    .earn-pts{font-size:20px;font-weight:800;color:var(--green)}
    .earn-pts span{font-size:12px;font-weight:400;color:var(--text2)}
    .withdraw-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(0,255,156,0.1);border:1px solid rgba(0,255,156,0.3);border-radius:24px;padding:10px 20px;font-size:14px;font-weight:600;color:var(--green);margin-top:24px}

    /* USE CASES */
    .usecase-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:40px}
    .usecase-card{background:#16161E;border:1px solid var(--border);border-radius:16px;padding:24px;transition:.3s}
    .usecase-card:hover{border-color:rgba(0,209,255,0.3);transform:translateY(-3px)}
    .uc-icon{font-size:36px;margin-bottom:12px}
    .uc-title{font-size:17px;font-weight:700;margin-bottom:8px}
    .uc-desc{font-size:14px;color:var(--text2);line-height:1.6}

    /* WHY VOXU */
    .why-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:40px}
    .why-item{display:flex;align-items:flex-start;gap:12px;background:#16161E;border:1px solid var(--border);border-radius:12px;padding:16px}
    .why-dot{width:8px;height:8px;border-radius:50%;background:var(--purple);flex-shrink:0;margin-top:5px}
    .why-text{font-size:14px;color:var(--text2)}
    .why-text strong{color:#fff;display:block;margin-bottom:3px}

    /* FAQ */
    .faq{display:flex;flex-direction:column;gap:12px;margin-top:40px;max-width:680px;margin-left:auto;margin-right:auto}
    .faq-item{background:#16161E;border:1px solid var(--border);border-radius:12px;overflow:hidden}
    .faq-q{padding:16px 20px;font-size:15px;font-weight:600;cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition:.2s;user-select:none}
    .faq-q:hover{background:rgba(255,255,255,0.03)}
    .faq-q .arrow{transition:transform .2s;color:var(--text2)}
    .faq-item.open .faq-q .arrow{transform:rotate(180deg)}
    .faq-a{max-height:0;overflow:hidden;transition:max-height .3s ease;font-size:14px;color:var(--text2);line-height:1.7}
    .faq-item.open .faq-a{max-height:200px}
    .faq-a-inner{padding:0 20px 16px}

    /* CTA */
    .cta-section{text-align:center;position:relative;overflow:hidden;padding:100px 0}
    .cta-section::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 80% at 50% 50%,rgba(108,59,255,0.1) 0%,transparent 70%)}
    .cta-section h2{font-family:'Poppins',sans-serif;font-size:clamp(32px,5vw,56px);font-weight:800;position:relative;z-index:1;margin-bottom:14px}
    .cta-section h2 span{color:var(--purple)}
    .cta-section p{color:var(--text2);font-size:16px;position:relative;z-index:1;max-width:440px;margin:0 auto 32px}
    .cta-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;position:relative;z-index:1}
    .btn-cta{padding:16px 32px;border-radius:12px;font-size:16px;font-weight:700;transition:.2s}
    .btn-cta.primary{background:var(--purple);color:#fff}
    .btn-cta.primary:hover{background:var(--purple-d);transform:translateY(-2px);box-shadow:0 10px 40px rgba(108,59,255,0.4)}
    .btn-cta.ghost{background:transparent;color:#fff;border:1px solid rgba(255,255,255,0.15)}
    .btn-cta.ghost:hover{background:rgba(255,255,255,0.05)}

    /* FOOTER */
    footer{border-top:1px solid var(--border);padding:40px 0 32px}
    .footer-inner{display:flex;flex-wrap:wrap;gap:32px;justify-content:space-between;align-items:flex-start}
    .footer-brand .logo-text{font-family:'Poppins',sans-serif;font-size:22px;font-weight:800}
    .footer-brand .logo-text span{color:var(--purple)}
    .footer-brand p{font-size:13px;color:var(--text2);margin-top:6px;max-width:220px}
    .footer-links{display:flex;gap:40px;flex-wrap:wrap}
    .footer-col h4{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--text2);margin-bottom:14px}
    .footer-col a{display:block;font-size:14px;color:var(--text2);margin-bottom:8px;transition:.2s}
    .footer-col a:hover{color:#fff}
    .footer-bottom{display:flex;justify-content:space-between;align-items:center;padding-top:24px;margin-top:24px;border-top:1px solid var(--border);font-size:13px;color:var(--text2)}
    .social-links{display:flex;gap:10px}
    .social-btn{width:34px;height:34px;background:rgba(255,255,255,0.05);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--text2);transition:.2s;font-size:14px}
    .social-btn:hover{background:var(--purple);color:#fff}

    @media(max-width:900px){
      .hero-inner{grid-template-columns:1fr;gap:40px}
      .hero-visual{display:none}
      .features-grid{grid-template-columns:1fr}
      .steps{grid-template-columns:1fr}
      .steps::before{display:none}
      .usecase-grid{grid-template-columns:1fr}
      .why-grid{grid-template-columns:1fr}
      .nav-links{display:none}
    }
    @media(max-width:600px){
      .footer-inner{flex-direction:column}
      .footer-bottom{flex-direction:column;gap:12px;text-align:center}
      section{padding:60px 0}
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <div class="nav-inner">
    <a href="/" class="nav-logo">Vo<span>xu</span></a>
    <div class="nav-links">
      <a href="#features">Features</a>
      <a href="#earn">Earn</a>
      <a href="#why">Why Voxu</a>
    <a href="/dashboard/premium.php" style="color:var(--purple);font-weight:600">⭐ Premium</a>
      <a href="#faq">FAQ</a>
    </div>
    <div class="nav-cta">
      <a href="/auth/login.php"  class="btn-nav btn-outline">Log In</a>
      <a href="/auth/register.php" class="btn-nav btn-fill">Get Started</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">
    <div>
      <div class="hero-tag">🎙 Voice · Status · Earnings</div>
      <h1 class="hero-title">
        <span class="highlight">Speak.</span><br/>
        Be <span class="hl2">Seen.</span><br/>
        Earn.
      </h1>
      <p class="hero-desc"><?= clean($settings['hero_subtext'] ?? 'Share your voice, post your status, and earn real money from every view, reply, and click.') ?></p>
      <div class="hero-btns">
        <a href="/auth/register.php" class="btn-hero-primary">Get Started Free →</a>
        <a href="#features" class="btn-hero-secondary">See How It Works</a>
      </div>
      <div class="hero-trust">
        <div class="trust-dots">
          <span>😊</span><span>🎤</span><span>💰</span>
        </div>
        <?= clean($settings['hero_trust_line'] ?? 'Join thousands of users earning daily') ?>
      </div>
    </div>
    <div class="hero-visual">
      <div class="phone" style="position:relative">
        <div class="glow-purple"></div>
        <div class="phone-notch"></div>
        <div class="phone-screen">
          <div class="phone-status">
            <span>9:41</span>
            <span>●●● 📶</span>
          </div>
          <div style="font-size:13px;font-weight:700;margin-bottom:10px">🎙 Voice Hub</div>
          <?php for($i=0;$i<3;$i++): ?>
          <div class="phone-card">
            <div class="phone-card-row">
              <div class="phone-avatar"></div>
              <div>
                <div class="phone-name">User_<?= $i+1 ?>X</div>
                <div class="phone-time"><?= $i*5+2 ?>m ago</div>
              </div>
            </div>
            <div class="phone-wave">
              <?php for($j=0;$j<18;$j++): $h=rand(20,80); ?>
                <span style="height:<?=$h?>%"></span>
              <?php endfor; ?>
            </div>
            <div class="phone-energy">⚡ <?= rand(12,80) ?> energy</div>
          </div>
          <?php endfor; ?>
          <div class="phone-bottom">
            <div class="phone-nav-dot active"></div>
            <div class="phone-nav-dot"></div>
            <div class="phone-nav-dot"></div>
            <div class="phone-nav-dot"></div>
            <div class="phone-nav-dot"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="how">
  <div class="container">
    <div class="section-center">
      <div class="section-label">Simple Process</div>
      <h2 class="section-title">How Voxu Works</h2>
      <p class="section-desc">Three steps to start earning from your voice and content.</p>
    </div>
    <div class="steps">
      <div class="step-card">
        <div class="step-num">1</div>
        <div class="step-title">Speak or Post</div>
        <p class="step-desc">Record your voice opinion or share a status update with your audience.</p>
      </div>
      <div class="step-card">
        <div class="step-num" style="background:var(--blue);color:#000">2</div>
        <div class="step-title">Get Engagement</div>
        <p class="step-desc">People listen, watch, reply, and interact with your content in real time.</p>
      </div>
      <div class="step-card">
        <div class="step-num" style="background:var(--green);color:#000">3</div>
        <div class="step-title">Earn Rewards</div>
        <p class="step-desc">Every interaction converts to points — withdraw as real cash anytime.</p>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section id="features" style="background:#0e0e14">
  <div class="container">
    <div class="section-label">Core Features</div>
    <h2 class="section-title">Two Powerful Hubs</h2>
    <div class="features-grid">
      <div class="feature-card" style="--feat-bg:radial-gradient(ellipse 60% 60% at 80% 20%,rgba(108,59,255,0.1),transparent)">
        <div class="feat-icon" style="background:rgba(108,59,255,0.12)">🎙</div>
        <div class="feat-title">Voice Hub</div>
        <p style="color:var(--text2);font-size:14px">Your Voice Matters — share opinions, join live conversations, and build your voice identity.</p>
        <ul class="feat-list">
          <li>Share opinions instantly with voice recording</li>
          <li>Join trending conversations with voice replies</li>
          <li>Reply with voice, text, or video</li>
          <li>Send energy to creators you love</li>
          <li>Build your voice identity and audience</li>
        </ul>
      </div>
      <div class="feature-card" style="--feat-bg:radial-gradient(ellipse 60% 60% at 20% 80%,rgba(0,209,255,0.08),transparent)">
        <div class="feat-icon" style="background:rgba(0,209,255,0.1)">✨</div>
        <div class="feat-title">Status Hub</div>
        <p style="color:var(--text2);font-size:14px">Turn Views Into Contacts — post stunning statuses with direct contact links that drive real leads.</p>
        <ul class="feat-list">
          <li>Post image, video, or text statuses</li>
          <li>Get discovered by thousands of users</li>
          <li>Add WhatsApp, Instagram, or website links</li>
          <li>Earn from every view and contact click</li>
          <li>Statuses expire — creating urgency and replay</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- EARNINGS -->
<section id="earn">
  <div class="container">
    <div class="section-center">
      <div class="section-label">Reward System</div>
      <h2 class="section-title">Earn From Every Action</h2>
      <p class="section-desc">Every post, reply, view, and click puts points in your wallet. Points convert to real cash.</p>
    </div>
    <div class="earn-grid">
      <?php
      $actions = [
        ['🎙','Post Voice','post', '+5 pts / post'],
        ['💬','Reply','reply','+2 pts / reply'],
        ['👁','Status View','view','+1 pt / view'],
        ['📞','Contact Click','click','+3 pts / click'],
        ['⚡','Get Energy','energy','+1 pt / energy'],
        ['🎯','Join Campaign','campaign','Custom pts'],
      ];
      foreach($actions as $a):
      ?>
      <div class="earn-card">
        <div class="earn-icon"><?= $a[0] ?></div>
        <div class="earn-label"><?= $a[1] ?></div>
        <div class="earn-pts"><?= $a[3] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="section-center" style="margin-top:32px">
      <div class="withdraw-badge">💸 Points convert to cash · Withdraw anytime</div>
    </div>
  </div>
</section>

<!-- USE CASES -->
<section style="background:#0e0e14">
  <div class="container">
    <div class="section-label">Who It's For</div>
    <h2 class="section-title">Built For Everyone</h2>
    <div class="usecase-grid">
      <div class="usecase-card">
        <div class="uc-icon">🎓</div>
        <div class="uc-title">Students</div>
        <p class="uc-desc">Promote your hustle, reach potential customers, and earn while you study. Your voice is your brand.</p>
      </div>
      <div class="usecase-card">
        <div class="uc-icon">🎨</div>
        <div class="uc-title">Creators</div>
        <p class="uc-desc">Grow your audience organically, earn from engagement, and convert followers into paying customers.</p>
      </div>
      <div class="usecase-card">
        <div class="uc-icon">🏢</div>
        <div class="uc-title">Businesses</div>
        <p class="uc-desc">Reach real active users, get direct leads through status contact links, and measure ROI on every post.</p>
      </div>
    </div>
  </div>
</section>

<!-- WHY VOXU -->
<section id="why">
  <div class="container">
    <div class="section-label">Why Us</div>
    <h2 class="section-title">Built Different</h2>
    <div class="why-grid">
      <?php
      $whys = [
        ['Voice-First Interaction','Unlike text platforms, Voxu puts your real voice at the center of every conversation.'],
        ['Status + Earning System','The first platform where your status posts generate real income from views and clicks.'],
        ['Direct Contact Feature','Turn viewers into contacts instantly — WhatsApp, Instagram, and more.'],
        ['Low Data Usage','Optimized for mobile-first users with slower connections. Fast, efficient, inclusive.'],
        ['Mobile-First Design','Built as a PWA for seamless app-like experience on any device.'],
        ['Transparent Earnings','See exactly how you earn, track points in real time, withdraw on your schedule.'],
      ];
      foreach($whys as $w):
      ?>
      <div class="why-item">
        <div class="why-dot"></div>
        <div class="why-text">
          <strong><?= $w[0] ?></strong>
          <?= $w[1] ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<section id="faq" style="background:#0e0e14">
  <div class="container">
    <div class="section-center">
      <div class="section-label">Questions</div>
      <h2 class="section-title">FAQ</h2>
    </div>
    <div class="faq">
      <?php
      $faqs = [
        ['Is Voxu free to use?','Yes! Voxu is completely free to join and use. You earn points and withdraw real cash from your activity — no subscription required.'],
        ['How do I earn on Voxu?','You earn points by posting voice content, getting replies, having your status viewed, getting contact clicks, and participating in campaigns. Points convert to real money.'],
        ['How do I withdraw my earnings?','Go to your Wallet, select Withdraw, choose your payment method (mobile money, bank transfer, etc.), and follow the simple process.'],
        ['What kind of content can I post?','You can post voice recordings (your opinions, stories, questions), and status updates (images, videos, or text with contact links).'],
        ['Is there a minimum withdrawal amount?','Yes, the minimum withdrawal is 500 points (equivalent to your local currency based on the conversion rate). This keeps transactions efficient.'],
        ['Is Voxu available as a mobile app?','Voxu is a Progressive Web App (PWA). You can add it to your home screen from your browser for a full app-like experience — no app store required.'],
      ];
      foreach($faqs as $i => $faq):
      ?>
      <div class="faq-item" id="faq-<?=$i?>">
        <div class="faq-q" onclick="toggleFaq(<?=$i?>)">
          <?= clean($faq[0]) ?>
          <span class="arrow">▾</span>
        </div>
        <div class="faq-a"><div class="faq-a-inner"><?= clean($faq[1]) ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- APP DOWNLOAD -->
<?php
$iosLink     = $settings['app_link_ios']          ?? '';
$androidLink = $settings['app_link_android']      ?? '';
$huaweiLink  = $settings['app_link_huawei']       ?? '';
$dlHeadline  = $settings['app_download_headline'] ?? 'Get the ' . ($settings['app_name'] ?? 'Voxu') . ' App';
$dlDesc      = $settings['app_download_desc']     ?? 'Available on iOS, Android, and as a Progressive Web App.';
if ($iosLink || $androidLink || $huaweiLink):
?>
<section style="background:linear-gradient(135deg,#13103a,var(--bg2));border-top:1px solid rgba(108,59,255,.2);border-bottom:1px solid rgba(108,59,255,.2);padding:72px 0">
  <div class="container" style="text-align:center">
    <div class="section-label" style="justify-content:center">📱 Mobile App</div>
    <h2 class="section-title" style="margin-bottom:10px"><?= clean($dlHeadline) ?></h2>
    <p style="font-size:16px;color:var(--text2);max-width:460px;margin:0 auto 32px"><?= clean($dlDesc) ?></p>
    <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap">
      <?php if ($iosLink): ?>
      <a href="<?= clean($iosLink) ?>" target="_blank" style="display:inline-flex;align-items:center;gap:10px;padding:13px 22px;background:#1A1A22;border:1px solid rgba(255,255,255,0.15);border-radius:12px;color:#fff;text-decoration:none;transition:.2s;min-width:150px">
        <span style="font-size:22px">🍎</span>
        <div style="text-align:left"><div style="font-size:10px;color:var(--text2)">Download on the</div><div style="font-size:15px;font-weight:700">App Store</div></div>
      </a>
      <?php endif; ?>
      <?php if ($androidLink): ?>
      <a href="<?= clean($androidLink) ?>" target="_blank" style="display:inline-flex;align-items:center;gap:10px;padding:13px 22px;background:#1A1A22;border:1px solid rgba(255,255,255,0.15);border-radius:12px;color:#fff;text-decoration:none;transition:.2s;min-width:150px">
        <span style="font-size:22px">🤖</span>
        <div style="text-align:left"><div style="font-size:10px;color:var(--text2)">Get it on</div><div style="font-size:15px;font-weight:700">Google Play</div></div>
      </a>
      <?php endif; ?>
      <?php if ($huaweiLink): ?>
      <a href="<?= clean($huaweiLink) ?>" target="_blank" style="display:inline-flex;align-items:center;gap:10px;padding:13px 22px;background:#1A1A22;border:1px solid rgba(255,255,255,0.15);border-radius:12px;color:#fff;text-decoration:none;transition:.2s;min-width:150px">
        <span style="font-size:22px">📦</span>
        <div style="text-align:left"><div style="font-size:10px;color:var(--text2)">Explore on</div><div style="font-size:15px;font-weight:700">AppGallery</div></div>
      </a>
      <?php endif; ?>
      <a href="/dashboard/" style="display:inline-flex;align-items:center;gap:10px;padding:13px 22px;background:#1A1A22;border:1px solid rgba(255,255,255,0.15);border-radius:12px;color:#fff;text-decoration:none;transition:.2s;min-width:150px">
        <span style="font-size:22px">🌐</span>
        <div style="text-align:left"><div style="font-size:10px;color:var(--text2)">Open as</div><div style="font-size:15px;font-weight:700">Web App (PWA)</div></div>
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- CTA -->
<section class="cta-section">
  <div class="container">
    <h2>Start Earning With<br/><span>Your Voice</span> Today</h2>
    <p>Join thousands of creators, students, and businesses already earning on Voxu.</p>
    <div class="cta-btns">
      <a href="/auth/register.php" class="btn-cta primary">Create Free Account</a>
      <a href="/auth/login.php"    class="btn-cta ghost">I Already Have an Account</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="footer-inner">
      <div class="footer-brand">
        <div class="logo-text">Vo<span>xu</span></div>
        <p>Speak. Be Seen. Earn.<br/>The voice-first earning platform.</p>
      </div>
      <div class="footer-links">
        <div class="footer-col">
          <h4>Platform</h4>
          <a href="#features">Features</a>
          <a href="#earn">Earn</a>
          <a href="#faq">FAQ</a>
        </div>
        <div class="footer-col">
          <h4>Account</h4>
          <a href="/auth/register.php">Sign Up</a>
          <a href="/auth/login.php">Log In</a>
          <a href="/dashboard/">Dashboard</a>
        </div>
        <div class="footer-col">
          <h4>Legal</h4>
          <a href="/about.php">About Us</a>
          <a href="/privacy.php">Privacy Policy</a>
          <a href="/terms.php">Terms of Service</a>
          <a href="/contact.php">Contact Us</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= clean($appName) ?>. All rights reserved.</span>
      <div class="social-links">
        <a class="social-btn" href="#" title="Twitter">𝕏</a>
        <a class="social-btn" href="#" title="Instagram">📷</a>
        <a class="social-btn" href="#" title="TikTok">🎵</a>
      </div>
    </div>
  </div>
</footer>

<script>
function toggleFaq(i){
  const item = document.getElementById('faq-'+i);
  item.classList.toggle('open');
}
// Animate wave bars on phone mockup
setInterval(()=>{
  document.querySelectorAll('.phone-wave span').forEach(s=>{
    s.style.height = (Math.random()*70+15)+'%';
  });
},400);
</script>
</body>
</html>
