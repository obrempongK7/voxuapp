<?php
// @author  Jcode | ObrempongK
// terms.php
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
  <title>Terms of Service — <?= clean($appName) ?></title>
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
  <h1>Terms of Service</h1>
  <div class="meta">Last updated: <?= date('F Y') ?></div>

  <h2>1. Acceptance of Terms</h2>
  <p>By accessing and using <?= clean($appName) ?>, you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our platform.</p>

  <h2>2. Eligibility</h2>
  <p>You must be at least 15 years old to use <?= clean($appName) ?>. By using the platform, you confirm that you meet this age requirement.</p>

  <h2>3. Account Responsibilities</h2>
  <ul>
    <li>You are responsible for maintaining the security of your account credentials.</li>
    <li>You must not share your account with others or create multiple accounts.</li>
    <li>You are responsible for all activity that occurs under your account.</li>
  </ul>

  <h2>4. Content Policy</h2>
  <p>You agree not to post content that is illegal, harmful, abusive, defamatory, or violates the rights of others. <?= clean($appName) ?> reserves the right to remove any content that violates these guidelines.</p>

  <h2>5. Earnings & Rewards</h2>
  <p>Points earned through platform engagement can be converted to cash according to the current conversion rate. Earnings are subject to anti-fraud verification. <?= clean($appName) ?> reserves the right to adjust earning rates and policies.</p>

  <h2>6. Prohibited Activities</h2>
  <ul>
    <li>Artificially inflating views, clicks, or engagement</li>
    <li>Using bots or automated tools</li>
    <li>Creating fake accounts or impersonating others</li>
    <li>Posting spam or misleading content</li>
    <li>Attempting to manipulate the earning system</li>
  </ul>

  <h2>7. Termination</h2>
  <p>We reserve the right to suspend or terminate accounts that violate these terms. In cases of fraud or abuse, balances may be forfeited.</p>

  <h2>8. Changes to Terms</h2>
  <p>We may update these terms from time to time. Continued use of the platform constitutes acceptance of the updated terms.</p>

  <h2>9. Contact</h2>
  <p>For questions about these terms, contact us at <?= clean($settings['support_email'] ?? 'support@voxu.app') ?>.</p>
</div>
</body>
</html>
