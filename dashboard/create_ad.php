<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAuth();

$user   = auth();
$userId = (int)$user['id'];
$wallet = getUserWallet($userId);
$settings = getPlatformSettings();
$appName  = clean($settings['app_name'] ?? 'Voxu');
$symbol   = clean($settings['currency_symbol'] ?? '$');
$theme    = getTheme();

// Ad Pricing Setup
$adCostPoints = 5000;
$adCostWallet = 10.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title     = sanitize($_POST['title'] ?? '');
    $linkUrl   = sanitizeUrl($_POST['link_url'] ?? '');
    $payMethod = $_POST['pay_method'] ?? 'points';

    if (!$title || !$linkUrl) {
        $error = 'Please provide a title and a valid link.';
    } elseif (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload an image for your ad.';
    } else {
        if ($payMethod === 'points') {
            if ($wallet['points_balance'] < $adCostPoints) {
                $error = 'Not enough points. You need ' . $adCostPoints . ' points.';
            } else {
                DB::exec('UPDATE wallets SET points_balance=points_balance-? WHERE user_id=?', [$adCostPoints, $userId]);
                $paid = true;
            }
        } else {
            if ($wallet['balance'] < $adCostWallet) {
                $error = 'Not enough wallet balance. You need ' . $symbol . $adCostWallet . '.';
            } else {
                DB::exec('UPDATE wallets SET balance=balance-? WHERE user_id=?', [$adCostWallet, $userId]);
                $paid = true;
            }
        }

        if (isset($paid)) {
            $up = uploadFile($_FILES['image'], 'ad');
            if (!$up['ok']) {
                $error = $up['error'];
                // Refund
                if ($payMethod === 'points') {
                    DB::exec('UPDATE wallets SET points_balance=points_balance+? WHERE user_id=?', [$adCostPoints, $userId]);
                } else {
                    DB::exec('UPDATE wallets SET balance=balance+? WHERE user_id=?', [$adCostWallet, $userId]);
                }
            } else {
                DB::insert('ad_slots', [
                    'title'      => $title,
                    'slot'       => 'sidebar',
                    'type'       => 'image',
                    'image_url'  => $up['url'],
                    'link_url'   => $linkUrl,
                    'is_active'  => 0, // Admin must approve
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $success = 'Your ad has been submitted and is pending admin approval!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Ad — <?= $appName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="/assets/css/voxu.css"/>
</head>
<body class="theme-<?= clean($theme) ?>">
<nav class="sk-topnav">
  <a href="/dashboard/feed.php" class="sk-logo"><?= $appName ?><span class="dot">.</span></a>
  <div style="position:absolute;left:50%;transform:translateX(-50%);font-size:16px;font-weight:700;color:var(--text)">Create Ad</div>
  <div class="sk-nav-actions">
    <a href="/dashboard/profile.php" style="flex-shrink:0"><div class="avatar avatar-sm"><?php if(!empty($user['avatar'])): ?><img src="<?= clean($user['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?></div></a>
  </div>
</nav>

<div class="sk-layout" style="padding-top:var(--nav-h)">
  <main style="flex:1;min-width:0;max-width:700px;margin:0 auto;padding:20px 16px">
    <h1 style="font-size:24px;font-weight:800;margin-bottom:16px">Create Sidebar Ad</h1>
    <p style="color:var(--text2);margin-bottom:24px">Promote your brand or content. Ads require admin approval after submission.</p>

    <?php if(!empty($error)): ?><div class="alert alert-danger" style="margin-bottom:20px"><?= $error ?></div><?php endif; ?>
    <?php if(!empty($success)): ?><div class="alert alert-success" style="margin-bottom:20px"><?= $success ?></div><?php endif; ?>

    <div class="sk-widget" style="padding:24px">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>

        <div class="input-group mb-3">
          <label class="input-label">Ad Title</label>
          <input type="text" name="title" class="input" placeholder="e.g. Check out my new podcast" required/>
        </div>

        <div class="input-group mb-3">
          <label class="input-label">Destination URL</label>
          <input type="url" name="link_url" class="input" placeholder="https://..." required/>
        </div>

        <div class="input-group mb-3">
          <label class="input-label">Ad Image Banner (Recommended: 600x300)</label>
          <input type="file" name="image" class="input" accept="image/*" required style="padding:8px"/>
        </div>

        <div class="input-group mb-4">
          <label class="input-label">Payment Method</label>
          <div style="display:flex;gap:16px;flex-wrap:wrap">
            <label style="display:flex;align-items:center;gap:8px;background:var(--bg3);padding:12px 16px;border-radius:var(--radius-sm);cursor:pointer;flex:1;border:1px solid var(--border)">
              <input type="radio" name="pay_method" value="points" checked/>
              <span style="font-weight:600">Points (<?= number_format($adCostPoints) ?>)</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;background:var(--bg3);padding:12px 16px;border-radius:var(--radius-sm);cursor:pointer;flex:1;border:1px solid var(--border)">
              <input type="radio" name="pay_method" value="wallet"/>
              <span style="font-weight:600">Wallet (<?= $symbol . number_format($adCostWallet, 2) ?>)</span>
            </label>
          </div>
          <div style="margin-top:8px;font-size:12px;color:var(--text3)">
            Your balance: <?= number_format((int)$wallet['points_balance']) ?> pts / <?= $symbol . number_format((float)$wallet['balance'], 2) ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:15px;border-radius:999px">Submit Ad</button>
      </form>
    </div>
  </main>
</div>
</body>
</html>
