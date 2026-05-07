<?php
// @author  Jcode | ObrempongK
// privacy.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$settings = getPlatformSettings();
$appName  = $settings['app_name'] ?? 'Voxu';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Privacy Policy — <?= clean($appName) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
  <style>
    .static-page{max-width:740px;margin:80px auto;padding:24px}
    .static-page h1{font-size:32px;font-weight:800;margin-bottom:8px}
    .static-page .meta{color:var(--text2);font-size:13px;margin-bottom:32px}
    .static-page h2{font-size:18px;font-weight:700;color:#fff;margin:28px 0 10px}
    .static-page p{color:var(--text2);font-size:14px;line-height:1.8;margin-bottom:12px}
    .static-page ul{color:var(--text2);font-size:14px;padding-left:20px;margin-bottom:12px;line-height:1.8}
  </style>
</head>
<body>
<nav class="topnav">
  <a href="/" style="font-size:20px;font-weight:800;font-family:'Poppins',sans-serif">Vo<span style="color:var(--purple)">xu</span></a>
  <div class="topnav-right">
    <a href="/auth/login.php" class="btn btn-secondary btn-sm">Log In</a>
    <a href="/auth/register.php" class="btn btn-primary btn-sm">Sign Up</a>
  </div>
</nav>
<div class="static-page">
  <h1>Privacy Policy</h1>
  <div class="meta">Last updated: <?= date('F Y') ?></div>

  <h2>1. Information We Collect</h2>
  <p>We collect information you provide directly, including:</p>
  <ul>
    <li>Account information (username, email, password)</li>
    <li>Profile details (bio, avatar, country)</li>
    <li>Content you create (voice recordings, status posts)</li>
    <li>Payment and withdrawal information</li>
    <li>Device information and IP addresses for security purposes</li>
  </ul>

  <h2>2. How We Use Your Information</h2>
  <ul>
    <li>To provide and improve our services</li>
    <li>To process earnings and payments</li>
    <li>To detect and prevent fraud</li>
    <li>To communicate important updates</li>
    <li>To comply with legal obligations</li>
  </ul>

  <h2>3. Data Sharing</h2>
  <p>We do not sell your personal data. We may share data with payment processors to facilitate transactions, and with law enforcement when legally required.</p>

  <h2>4. Data Security</h2>
  <p>We implement industry-standard security measures including encrypted passwords, HTTPS, and session security. However, no system is 100% secure.</p>

  <h2>5. Voice Content</h2>
  <p>Voice recordings you upload are stored on our servers and made publicly available on the platform. Do not upload private or sensitive conversations.</p>

  <h2>6. Cookies</h2>
  <p>We use session cookies to keep you logged in. We do not use third-party tracking cookies.</p>

  <h2>7. Your Rights</h2>
  <ul>
    <li>Access your personal data via your profile settings</li>
    <li>Delete your account and associated data</li>
    <li>Request a copy of your data</li>
  </ul>

  <h2>8. Data Retention</h2>
  <p>We retain your data as long as your account is active. Deleted accounts are purged within 30 days.</p>

  <h2>9. Contact</h2>
  <p>For privacy inquiries, contact <?= clean($settings['support_email'] ?? 'privacy@voxu.app') ?>.</p>
</div>
</body>
</html>
