<?php
/**
 * Voxu — Premium Plans Page
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
$settings = getPlatformSettings();
$symbol   = $settings['currency_symbol'] ?? '$';
$myPlan   = getUserPlan($userId);
$plans    = DB::query("SELECT * FROM subscription_plans WHERE is_active=1 ORDER BY price_monthly ASC");
$theme = getTheme();

function secLabel(int $s): string {
    if ($s === 0) return 'Unlimited';
    return floor($s/60) . ' min';
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>" <?= isRtl() ? 'dir="rtl"' : '' ?>>
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta name="csrf-token" content="<?= csrfToken() ?>"/>
  <title>Go Premium — Voxu</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
  <style>
    .premium-page{max-width:900px;margin:0 auto;padding:80px 16px 100px}
    .premium-hero{text-align:center;margin-bottom:48px}
    .premium-title{font-family:'Poppins',sans-serif;font-size:clamp(28px,6vw,48px);font-weight:800;color:#fff;margin-bottom:10px;line-height:1.15}
    .premium-title span{color:var(--purple)}
    .premium-sub{font-size:16px;color:var(--text2);max-width:480px;margin:0 auto 20px}
    .current-badge{display:inline-flex;align-items:center;gap:8px;background:var(--purple-l);border:1px solid var(--purple);border-radius:20px;padding:8px 16px;font-size:13px;font-weight:600;color:var(--purple)}
    .billing-toggle{display:flex;align-items:center;justify-content:center;gap:10px;margin:0 auto 32px}
    .billing-lbl{font-size:14px;color:var(--text2)}
    .billing-save{background:var(--green-l);color:var(--green);border-radius:10px;padding:2px 8px;font-size:11px;font-weight:600}
    .plans-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:40px}
    .plan-card{background:var(--card);border:2px solid var(--border);border-radius:20px;padding:24px;position:relative;overflow:hidden;transition:.3s;display:flex;flex-direction:column}
    .plan-card.popular{border-color:var(--purple);}
    .plan-card.current-plan{border-color:var(--green)}
    .plan-card:hover:not(.current-plan){transform:translateY(-4px);box-shadow:0 16px 40px rgba(0,0,0,0.4)}
    .popular-badge{position:absolute;top:14px;right:14px;background:var(--purple);color:#fff;border-radius:10px;padding:2px 8px;font-size:10px;font-weight:700;text-transform:uppercase}
    .current-label{position:absolute;top:14px;right:14px;background:var(--green-l);color:var(--green);border-radius:10px;padding:2px 8px;font-size:10px;font-weight:700}
    .plan-icon{font-size:28px;margin-bottom:10px}
    .plan-name{font-size:18px;font-weight:800;color:#fff;margin-bottom:6px}
    .plan-price{font-size:30px;font-weight:800;color:#fff;line-height:1;margin-bottom:4px}
    .plan-price .cur{font-size:14px;font-weight:400;color:var(--text2);vertical-align:top;margin-top:4px;display:inline-block}
    .plan-price .per{font-size:13px;font-weight:400;color:var(--text2)}
    .plan-yearly{font-size:12px;color:var(--text3);margin-bottom:16px}
    .plan-features{flex:1;list-style:none;margin-bottom:18px;display:flex;flex-direction:column;gap:8px}
    .plan-features li{font-size:13px;color:var(--text2);display:flex;align-items:flex-start;gap:7px;line-height:1.4}
    .plan-features li .tick{font-size:14px;flex-shrink:0;margin-top:1px}
    .plan-features li .tick.yes{color:var(--green)}
    .plan-features li .tick.no{color:var(--text3)}
    .plan-btn{width:100%;padding:12px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:.2s;text-align:center;text-decoration:none;display:block}
    .plan-btn.active{background:var(--purple);color:#fff}
    .plan-btn.active:hover{background:var(--purple-d)}
    .plan-btn.current{background:var(--green-l);color:var(--green);cursor:default}
    .plan-btn.free{background:var(--bg2);color:var(--text2);border:1px solid var(--border)}
    .compare-table{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-top:12px}
    .compare-table th{background:var(--bg3);padding:12px 16px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text2);text-align:center}
    .compare-table th:first-child{text-align:left}
    .compare-table td{padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text2);text-align:center}
    .compare-table td:first-child{text-align:left;color:var(--text)}
    .compare-table tr:last-child td{border-bottom:none}
    .theme-toggle { width: 38px; height: 20px; background: var(--bg3); border-radius: 10px; position: relative; cursor: pointer; border: 1px solid var(--border2); flex-shrink: 0; }
    .theme-toggle-knob { position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--purple); transition: left .2s; }
    body.theme-light .theme-toggle-knob { left: 20px; background: var(--warning); }
  </style>
</head>
<body class="<?= clean(themeClass()) ?>">
<nav class="sk-topnav">
  <a href="/dashboard/feed.php" class="sk-logo"><?= $appName ?><span class="dot">.</span></a>
  <div style="position:absolute;left:50%;transform:translateX(-50%);font-size:17px;font-weight:700;color:var(--text)">&#11088; Premium</div>
  <div class="sk-nav-actions">
    <a href="/dashboard/notifications.php" class="sk-nav-btn" title="Notifications">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </a>
    <a href="/dashboard/profile.php" style="flex-shrink:0;text-decoration:none">
      <div class="avatar avatar-sm"><?php if(!empty($user['avatar'])): ?><img src="<?= clean($user['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?></div>
    </a>
  </div>
</nav>

<div class="app-layout">
  <div class="premium-page">

    <!-- HERO -->
    <div class="premium-hero">
      <h1 class="premium-title">Unlock Your Full<br/><span>Earning Potential</span></h1>
      <p class="premium-sub">Upgrade to record longer, earn more every day, and access powerful creator tools.</p>
      <div class="current-badge">
        <?= $myPlan['icon'] ?? '🎙' ?> Current plan: <?= clean($myPlan['name'] ?? 'Free') ?>
        <?php if (($myPlan['slug']??'free') !== 'free'): ?>
          <span style="color:var(--green)">✓ Active</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- BILLING TOGGLE -->
    <div class="billing-toggle">
      <span class="billing-lbl" id="lbl-monthly" style="color:#fff;font-weight:600">Monthly</span>
      <label class="toggle" style="margin:0 4px">
        <input type="checkbox" id="billingToggle" onchange="switchBilling(this.checked)"/>
        <span class="toggle-track"></span>
      </label>
      <span class="billing-lbl" id="lbl-yearly">Yearly <span class="billing-save">Save up to 20%</span></span>
    </div>

    <!-- PLAN CARDS -->
    <div class="plans-grid">
      <?php foreach ($plans as $p):
        $isCurrent = ($p['id'] == ($myPlan['id'] ?? 1));
        $isPopular  = ($p['slug'] === 'gold');
        $isFree     = ($p['slug'] === 'free');
      ?>
      <div class="plan-card <?= $isPopular?'popular':'' ?> <?= $isCurrent?'current-plan':'' ?>">
        <?php if ($isPopular && !$isCurrent): ?><div class="popular-badge">Most Popular</div><?php endif; ?>
        <?php if ($isCurrent): ?><div class="current-label">✓ Your Plan</div><?php endif; ?>

        <div class="plan-icon"><?= $p['icon'] ?></div>
        <div class="plan-name"><?= clean($p['name']) ?></div>

        <?php if ($isFree): ?>
          <div class="plan-price"><span class="cur"><?= $symbol ?></span>0<span class="per">/mo</span></div>
          <div class="plan-yearly">Free forever</div>
        <?php else: ?>
          <div class="plan-price" id="price-<?= $p['id'] ?>">
            <span class="cur"><?= $symbol ?></span><?= number_format((float)$p['price_monthly'],2) ?><span class="per">/mo</span>
          </div>
          <div class="plan-yearly" id="yearly-<?= $p['id'] ?>"><?= $symbol ?><?= number_format((float)$p['price_yearly'],2) ?>/year (save <?= $symbol ?><?= number_format((float)$p['price_monthly']*12 - (float)$p['price_yearly'],2) ?>)</div>
        <?php endif; ?>

        <ul class="plan-features">
          <li>
            <span class="tick yes">🎙</span>
            <span><strong style="color:#fff"><?= secLabel((int)$p['max_recording_secs']) ?></strong> voice recording</span>
          </li>
          <li>
            <span class="tick yes">⚡</span>
            <span>Up to <strong style="color:#fff"><?= number_format((int)$p['max_daily_earnings']) ?> pts</strong>/day</span>
          </li>
          <li>
            <span class="tick yes">💰</span>
            <span><strong style="color:#fff"><?= $p['cashout_multiplier'] ?>×</strong> earnings multiplier</span>
          </li>
          <li>
            <span class="tick yes">📸</span>
            <span><strong style="color:#fff"><?= (int)$p['max_status_per_day']===100?'Unlimited':$p['max_status_per_day'] ?></strong> statuses/day</span>
          </li>
          <li>
            <span class="tick <?= $p['can_analytics']?'yes':'no' ?>"><?= $p['can_analytics']?'✓':'—' ?></span>
            <span>Detailed Analytics</span>
          </li>
          <li>
            <span class="tick <?= $p['can_custom_link']?'yes':'no' ?>"><?= $p['can_custom_link']?'✓':'—' ?></span>
            <span>Custom Profile URL</span>
          </li>
          <li>
            <span class="tick <?= $p['verified_badge']?'yes':'no' ?>"><?= $p['verified_badge']?'✓':'—' ?></span>
            <span>Verified Badge</span>
          </li>
          <li>
            <span class="tick <?= $p['priority_support']?'yes':'no' ?>"><?= $p['priority_support']?'✓':'—' ?></span>
            <span>Priority Support</span>
          </li>
        </ul>

        <?php if ($isCurrent): ?>
          <div class="plan-btn current">✓ Current Plan</div>
        <?php elseif ($isFree): ?>
          <div class="plan-btn free">Free Forever</div>
        <?php else: ?>
          <a href="#" class="plan-btn active" onclick="selectPlan(<?= $p['id'] ?>,'<?= clean($p['name']) ?>', <?= $p['price_monthly'] ?>, <?= $p['price_yearly'] ?>)">
            Upgrade to <?= clean($p['name']) ?>
          </a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- COMPARISON TABLE -->
    <div style="text-align:center;margin-bottom:20px">
      <h2 style="font-size:22px;font-weight:700;color:#fff">Full Comparison</h2>
    </div>
    <div class="compare-table">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr>
            <th style="text-align:left">Feature</th>
            <?php foreach ($plans as $p): ?>
              <th style="color:<?= clean($p['color']) ?>"><?= $p['icon'] ?> <?= clean($p['name']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $rows = [
            'Max Recording'     => fn($p) => secLabel((int)$p['max_recording_secs']),
            'Daily Earn Limit'  => fn($p) => number_format((int)$p['max_daily_earnings']) . ' pts',
            'Min Withdrawal'    => fn($p) => number_format((int)$p['min_withdrawal_pts']) . ' pts',
            'Earnings Boost'    => fn($p) => $p['cashout_multiplier'] . '×',
            'Statuses / Day'    => fn($p) => (int)$p['max_status_per_day']===100?'Unlimited':$p['max_status_per_day'],
            'Analytics'         => fn($p) => $p['can_analytics']    ? '<span style="color:var(--green)">✓</span>' : '<span style="color:var(--text3)">—</span>',
            'Custom URL'        => fn($p) => $p['can_custom_link']   ? '<span style="color:var(--green)">✓</span>' : '<span style="color:var(--text3)">—</span>',
            'Voice Background'  => fn($p) => $p['can_voice_bg']     ? '<span style="color:var(--green)">✓</span>' : '<span style="color:var(--text3)">—</span>',
            'Verified Badge'    => fn($p) => $p['verified_badge']    ? '<span style="color:var(--green)">✓</span>' : '<span style="color:var(--text3)">—</span>',
            'Priority Support'  => fn($p) => $p['priority_support']  ? '<span style="color:var(--green)">✓</span>' : '<span style="color:var(--text3)">—</span>',
          ];
          foreach ($rows as $label => $fn):
          ?>
          <tr>
            <td><?= $label ?></td>
            <?php foreach ($plans as $p): ?>
              <td><?= $fn($p) ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p style="text-align:center;font-size:12px;color:var(--text3);margin-top:20px">
      Cancel anytime. Payments processed securely. Contact <a href="/contact.php" style="color:var(--purple)">support</a> for any billing questions.
    </p>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="/dashboard/"          class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>Voice</a>
  <a href="/dashboard/status.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/></svg>Status</a>
  <a href="/dashboard/"           class="bottom-nav-item"><div class="bottom-nav-create"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div></a>
  <a href="/dashboard/wallet.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Wallet</a>
  <a href="/dashboard/profile.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profile</a>
</nav>

<!-- CHECKOUT MODAL -->
<div class="modal-overlay" id="checkoutModal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title" id="checkoutTitle">Upgrade to Gold</div>
    <input type="hidden" id="selectedPlanId"/>
    <div id="checkoutSummary" style="background:var(--bg3);border-radius:10px;padding:14px;margin-bottom:16px;font-size:14px;color:var(--text2)"></div>
    <div class="input-group mb-3">
      <label class="input-label">Billing</label>
      <div class="flex gap-2">
        <button class="btn btn-primary flex-1" id="billingMonthly" onclick="setBilling('monthly')">Monthly</button>
        <button class="btn btn-secondary flex-1" id="billingYearly"  onclick="setBilling('yearly')">Yearly (save 20%)</button>
      </div>
    </div>
    <button class="btn btn-primary w-full btn-lg" onclick="proceedCheckout()">Proceed to Payment →</button>
    <p style="font-size:11px;color:var(--text3);text-align:center;margin-top:10px">You will be redirected to complete payment securely.</p>
  </div>
</div>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
let selectedPlan = null;
let billing = 'monthly';

function switchBilling(yearly) {
  document.getElementById('lbl-monthly').style.color = yearly ? 'var(--text2)' : '#fff';
  document.getElementById('lbl-monthly').style.fontWeight = yearly ? '400' : '600';
  document.getElementById('lbl-yearly').style.color = yearly ? '#fff' : 'var(--text2)';
  document.getElementById('lbl-yearly').style.fontWeight = yearly ? '600' : '400';
  // Update prices shown on cards
  document.querySelectorAll('[id^="price-"]').forEach(el => {
    const planId = el.id.replace('price-','');
    const yrEl   = document.getElementById('yearly-' + planId);
    // We'd need plan data to update dynamically; for now show toggle hint
  });
}

function selectPlan(id, name, priceM, priceY) {
  selectedPlan = { id, name, priceM, priceY };
  billing = 'monthly';
  document.getElementById('checkoutTitle').textContent = 'Upgrade to ' + name;
  updateCheckoutSummary();
  Modal.open('checkoutModal');
}

function setBilling(b) {
  billing = b;
  document.getElementById('billingMonthly').className = 'btn flex-1 ' + (b==='monthly'?'btn-primary':'btn-secondary');
  document.getElementById('billingYearly').className  = 'btn flex-1 ' + (b==='yearly' ?'btn-primary':'btn-secondary');
  updateCheckoutSummary();
}

function updateCheckoutSummary() {
  if (!selectedPlan) return;
  const price = billing === 'yearly' ? selectedPlan.priceY : selectedPlan.priceM;
  const period= billing === 'yearly' ? 'per year' : 'per month';
  document.getElementById('checkoutSummary').innerHTML =
    '<strong style="color:#fff">' + selectedPlan.name + '</strong> — <?= $symbol ?>' + parseFloat(price).toFixed(2) + ' ' + period;
}

async function proceedCheckout() {
  if (!selectedPlan) return;
  const res = await API.post('/payments/deposit/initiate', {
    amount: billing === 'yearly' ? selectedPlan.priceY : selectedPlan.priceM,
    gateway_id: 1,
    meta: JSON.stringify({ type: 'subscription', plan_id: selectedPlan.id, billing })
  });
  if (res?.redirect_url) {
    window.location.href = res.redirect_url;
  } else {
    Toast.info('Redirecting to payment…');
    window.location.href = '/dashboard/wallet.php';
  }
}
</script>
</body>
</html>
