<?php
/**
 * Voxu — Email Verification
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/i18n.php';

$token   = sanitize($_GET['token'] ?? '');
$appName = clean(getPlatformSettings()['app_name'] ?? 'Voxu');
$theme   = getTheme();

if (!$token) redirect('/auth/login.php');

$userId = verifyEmailToken($token);

if ($userId) {
    // Log the user in immediately
    loginUser($userId);
    redirect('/dashboard/feed.php?welcome=1');
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Verification Failed — <?= $appName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="/assets/css/voxu.css"/>
</head>
<body class="theme-<?= clean($theme) ?>" style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px">
<div style="max-width:400px;width:100%;text-align:center">
  <div style="font-size:52px;margin-bottom:16px">❌</div>
  <h2 style="color:var(--text);margin-bottom:10px">Link Expired or Invalid</h2>
  <p style="color:var(--text2);font-size:14px;margin-bottom:22px">This verification link has expired or already been used.<br/>Register again to get a new link.</p>
  <a href="/auth/register.php" class="btn btn-primary" style="border-radius:999px">Register Again</a>
</div>
</body></html>
