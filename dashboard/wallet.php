<?php
// @author  Jcode | ObrempongK
// dashboard/wallet.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/i18n.php';
requireAuth();

$user     = auth();
$userId   = (int)$user['id'];
$wallet   = getUserWallet($userId);
$settings = getPlatformSettings();
$rate     = (int)($settings['points_to_cash_rate'] ?? 100);
$minWith  = (int)($settings['min_withdrawal']      ?? 500);
$currency = $settings['currency'] ?? 'USD';
$symbol   = $settings['currency_symbol'] ?? '$';

// Transactions
$txPage  = max(1,(int)($_GET['txpage'] ?? 1));
$txPer   = 15;
$txOffset= ($txPage-1)*$txPer;
$transactions = DB::query(
    "SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT {$txPer} OFFSET {$txOffset}",
    [$userId]
);
$txTotal  = DB::count('transactions','user_id=?',[$userId]);

$pointsTx = DB::query(
    "SELECT * FROM points_transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 20",
    [$userId]
);

// Active gateways
try {
    $gateways = DB::query("SELECT * FROM payment_gateways WHERE is_active=1 ORDER BY sort_order ASC, name ASC");
} catch (Throwable) { $gateways = []; }

// Pending withdrawals
$pendingWithdrawals = DB::query(
    "SELECT * FROM withdrawals WHERE user_id=? AND status IN('pending','approved','processing') ORDER BY created_at DESC",
    [$userId]
);

$cashValue = round(($wallet['points_balance']??0) / max(1,$rate), 2);
$theme = getTheme();
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>" <?= isRtl() ? 'dir="rtl"' : '' ?>>
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta name="csrf-token" content="<?= csrfToken() ?>"/>
  <title>Wallet — Voxu</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
  <style>
    .theme-toggle { width: 38px; height: 20px; background: var(--bg3); border-radius: 10px; position: relative; cursor: pointer; border: 1px solid var(--border2); flex-shrink: 0; }
    .theme-toggle-knob { position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--purple); transition: left .2s; }
    body.theme-light .theme-toggle-knob { left: 20px; background: var(--warning); }
  </style>
</head>
<body class="<?= clean(themeClass()) ?>">
<nav class="sk-topnav">
  <a href="/dashboard/feed.php" class="sk-logo"><?= $appName ?><span class="dot">.</span></a>
  <div style="position:absolute;left:50%;transform:translateX(-50%);font-size:16px;font-weight:700;color:var(--text)">&#128179; Wallet</div>
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
  <div class="page">

    <!-- WALLET CARD -->
    <div class="wallet-card mb-4">
      <div class="wallet-label">Cash Balance</div>
      <div class="wallet-balance">
        <span class="currency"><?= $symbol ?></span><?= number_format((float)($wallet['balance']??0), 2) ?>
      </div>
      <div class="wallet-points">
        <span><?= number_format((int)($wallet['points_balance']??0)) ?> pts</span>
        = <?= $symbol ?><?= number_format($cashValue, 2) ?> cash value
      </div>
      <?php if (!empty($wallet['is_frozen'])): ?>
        <div style="color:var(--danger);font-size:12px;margin-top:8px;font-weight:600">⚠ Wallet frozen. Contact support.</div>
      <?php endif; ?>
      <div class="wallet-actions">
        <button class="wallet-action-btn" onclick="Modal.open('deposit-modal')">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
          Deposit
        </button>
        <button class="wallet-action-btn" onclick="Modal.open('withdraw-modal')">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
          Withdraw
        </button>
        <button class="wallet-action-btn" onclick="Modal.open('transfer-modal')">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
          Transfer
        </button>
        <button class="wallet-action-btn" onclick="Modal.open('convert-modal')">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
          Convert
        </button>
      </div>
    </div>

    <!-- PENDING WITHDRAWALS -->
    <?php if (!empty($pendingWithdrawals)): ?>
    <div class="card mb-4">
      <div class="font-semi mb-3" style="font-size:14px">⏳ Pending Withdrawals</div>
      <?php foreach ($pendingWithdrawals as $w): ?>
      <div class="tx-item">
        <div class="tx-icon debit">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
        </div>
        <div class="tx-info">
          <div class="tx-desc">Withdrawal via <?= clean($w['method']) ?></div>
          <div class="tx-date"><?= timeAgo($w['created_at']) ?></div>
        </div>
        <div>
          <div class="tx-amount debit"><?= $symbol ?><?= number_format((float)($w['net_amount'] ?? 0),2) ?></div>
          <div class="badge badge-warning" style="margin-top:4px"><?= ucfirst($w['status']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- TX TABS -->
    <div class="tabs">
      <div class="tab active" id="tab-cash" onclick="switchWalletTab('cash')">Cash Transactions</div>
      <div class="tab" id="tab-points" onclick="switchWalletTab('points')">Points History</div>
    </div>

    <!-- CASH TRANSACTIONS -->
    <div id="pane-cash">
      <?php if (empty($transactions)): ?>
        <div class="empty">
          <div class="empty-icon">💳</div>
          <div class="empty-title">No transactions yet</div>
          <p class="empty-text">Your cash transactions will appear here.</p>
        </div>
      <?php else: ?>
        <div class="card card-sm">
          <?php foreach ($transactions as $tx): $isCredit = in_array($tx['type'],['deposit','transfer_in','reward','refund']); ?>
          <div class="tx-item">
            <div class="tx-icon <?= $isCredit?'credit':'debit' ?>">
              <?php if ($isCredit): ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
              <?php else: ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
              <?php endif; ?>
            </div>
            <div class="tx-info">
              <div class="tx-desc"><?= clean($tx['description'] ?: ucwords(str_replace('_',' ',$tx['type']))) ?></div>
              <div class="tx-date"><?= timeAgo($tx['created_at']) ?> · <?= ucfirst($tx['status']) ?></div>
            </div>
            <div class="tx-amount <?= $isCredit?'credit':'debit' ?>"><?= $isCredit?'+':'-' ?><?= $symbol ?><?= number_format((float)$tx['amount'],2) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($txTotal > $txPer): ?>
        <div class="flex justify-center gap-2 mt-4">
          <?php if ($txPage > 1): ?><a href="?txpage=<?=$txPage-1?>" class="btn btn-secondary btn-sm">← Prev</a><?php endif; ?>
          <?php if ($txPage * $txPer < $txTotal): ?><a href="?txpage=<?=$txPage+1?>" class="btn btn-secondary btn-sm">Next →</a><?php endif; ?>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- POINTS HISTORY -->
    <div id="pane-points" class="hidden">
      <?php if (empty($pointsTx)): ?>
        <div class="empty">
          <div class="empty-icon">⚡</div>
          <div class="empty-title">No points activity yet</div>
          <p class="empty-text">Start posting and engaging to earn points.</p>
        </div>
      <?php else: ?>
        <div class="card card-sm">
          <?php foreach ($pointsTx as $pt): ?>
          <div class="tx-item">
            <div class="tx-icon <?= $pt['type']==='credit'?'credit':'debit' ?>">
              <span style="font-size:16px"><?= $pt['type']==='credit'?'⚡':'↑' ?></span>
            </div>
            <div class="tx-info">
              <div class="tx-desc"><?= clean($pt['description'] ?: ucwords(str_replace('_',' ',$pt['source']))) ?></div>
              <div class="tx-date"><?= timeAgo($pt['created_at']) ?></div>
            </div>
            <div class="tx-amount <?= $pt['type']==='credit'?'credit':'debit' ?>">
              <?= $pt['type']==='credit'?'+':'-' ?><?= number_format((int)$pt['points']) ?> pts
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="/dashboard/" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>Voice</a>
  <a href="/dashboard/status.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>Status</a>
  <a href="/dashboard/" class="bottom-nav-item"><div class="bottom-nav-create"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div></a>
  <a href="/dashboard/wallet.php" class="bottom-nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Wallet</a>
  <a href="/dashboard/profile.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profile</a>
</nav>

<!-- DEPOSIT MODAL -->
<div class="modal-overlay" id="deposit-modal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title">Deposit Funds</div>
    <div class="input-group mb-3">
      <label class="input-label">Amount (<?= $symbol ?>)</label>
      <input class="input" type="number" id="depositAmount" placeholder="50.00" min="1" step="0.01"/>
    </div>
    <div class="input-group mb-4">
      <label class="input-label">Payment Gateway</label>
      <select class="input" id="depositGateway">
        <?php foreach ($gateways as $gw): ?>
          <option value="<?= $gw['id'] ?>"><?= clean($gw['name']) ?></option>
        <?php endforeach; ?>
        <?php if (empty($gateways)): ?>
          <option value="">No gateways configured</option>
        <?php endif; ?>
      </select>
    </div>
    <button class="btn btn-primary w-full" onclick="initiateDeposit()">Proceed to Payment</button>
  </div>
</div>

<!-- WITHDRAW MODAL -->
<div class="modal-overlay" id="withdraw-modal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title">Withdraw</div>
    <div class="card card-sm mb-4" style="background:var(--green-l);border-color:var(--green)">
      <div style="font-size:13px;color:var(--green);font-weight:600">
        Available: <?= number_format((int)($wallet['points_balance']??0)) ?> pts (<?= $symbol ?><?= number_format($cashValue,2) ?>)
      </div>
      <div style="font-size:12px;color:var(--text2);margin-top:4px">
        Min withdrawal: <?= $minWith ?> pts · Rate: <?= $rate ?> pts = <?= $symbol ?>1
      </div>
    </div>
    <div class="input-group mb-3">
      <label class="input-label">Points to Withdraw</label>
      <input class="input" type="number" id="withdrawPts" placeholder="<?= $minWith ?>" min="<?= $minWith ?>" step="1"/>
      <div style="font-size:12px;color:var(--text2);margin-top:4px" id="withdrawCash">= <?= $symbol ?>0.00</div>
    </div>
    <div class="input-group mb-3">
      <label class="input-label">Withdrawal Method</label>
      <select class="input" id="withdrawMethod">
        <option value="mobile_money">Mobile Money</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="paypal">PayPal</option>
      </select>
    </div>
    <div class="input-group mb-4">
      <label class="input-label">Account Details</label>
      <input class="input" type="text" id="withdrawAccount" placeholder="Phone number / account number / email"/>
    </div>
    <button class="btn btn-primary w-full" onclick="submitWithdrawal()">Request Withdrawal</button>
  </div>
</div>

<!-- TRANSFER MODAL -->
<div class="modal-overlay" id="transfer-modal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title">Send to User</div>
    <div class="input-group mb-3">
      <label class="input-label">Recipient Username</label>
      <input class="input" type="text" id="transferUsername" placeholder="@username"/>
    </div>
    <div class="input-group mb-3">
      <label class="input-label">Amount (<?= $symbol ?>)</label>
      <input class="input" type="number" id="transferAmount" placeholder="10.00" min="0.01" step="0.01"/>
    </div>
    <div class="input-group mb-4">
      <label class="input-label">Note (optional)</label>
      <input class="input" type="text" id="transferNote" placeholder="What's this for?"/>
    </div>
    <button class="btn btn-primary w-full" onclick="submitTransfer()">Send <?= $symbol ?></button>
  </div>
</div>

<!-- CONVERT MODAL -->
<div class="modal-overlay" id="convert-modal">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-title">Convert Points to Cash</div>
    <div class="card card-sm mb-4" style="background:var(--purple-l);border-color:var(--purple)">
      <div style="font-size:13px;color:var(--purple);font-weight:600">Rate: <?= $rate ?> pts = <?= $symbol ?>1.00</div>
      <div style="font-size:12px;color:var(--text2);margin-top:4px">Your points: <?= number_format((int)($wallet['points_balance']??0)) ?> pts</div>
    </div>
    <div class="input-group mb-4">
      <label class="input-label">Points to Convert</label>
      <input class="input" type="number" id="convertPts" placeholder="100" min="<?= $minWith ?>" step="<?= $rate ?>"/>
      <div style="font-size:12px;color:var(--text2);margin-top:4px" id="convertCash">= <?= $symbol ?>0.00 cash</div>
    </div>
    <button class="btn btn-primary w-full" onclick="submitConvert()">Convert to Cash</button>
  </div>
</div>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
const RATE = <?= $rate ?>;
const SYMBOL = '<?= $symbol ?>';
const MIN_WITHDRAW = <?= $minWith ?>;

function switchWalletTab(tab) {
  document.getElementById('pane-cash').classList.toggle('hidden', tab !== 'cash');
  document.getElementById('pane-points').classList.toggle('hidden', tab !== 'points');
  document.getElementById('tab-cash').classList.toggle('active', tab === 'cash');
  document.getElementById('tab-points').classList.toggle('active', tab === 'points');
}

document.getElementById('withdrawPts')?.addEventListener('input', function() {
  const cash = (parseInt(this.value)||0) / RATE;
  document.getElementById('withdrawCash').textContent = '= ' + SYMBOL + cash.toFixed(2);
});

document.getElementById('convertPts')?.addEventListener('input', function() {
  const cash = (parseInt(this.value)||0) / RATE;
  document.getElementById('convertCash').textContent = '= ' + SYMBOL + cash.toFixed(2) + ' cash';
});

async function initiateDeposit() {
  const amount  = parseFloat(document.getElementById('depositAmount').value);
  const gateway = document.getElementById('depositGateway').value;
  if (!amount || amount < 1) { Toast.error('Enter a valid amount'); return; }
  const res = await API.post('/payments/deposit/initiate', { amount, gateway_id: gateway });
  if (res?.redirect_url) { window.location.href = res.redirect_url; }
  else if (res?.success) { Toast.success(res.message || 'Deposit initiated'); Modal.close('deposit-modal'); }
}

async function submitWithdrawal() {
  const pts     = parseInt(document.getElementById('withdrawPts').value);
  const method  = document.getElementById('withdrawMethod').value;
  const account = document.getElementById('withdrawAccount').value.trim();
  if (!pts || pts < MIN_WITHDRAW) { Toast.error('Minimum withdrawal is ' + MIN_WITHDRAW + ' pts'); return; }
  if (!account) { Toast.error('Enter your account details'); return; }
  const res = await API.post('/wallet/withdraw', { points: pts, method, account_details: account });
  if (res?.success) { Toast.success('Withdrawal requested!'); Modal.close('withdraw-modal'); setTimeout(()=>location.reload(),800); }
}

async function submitTransfer() {
  const username = document.getElementById('transferUsername').value.trim().replace('@','');
  const amount   = parseFloat(document.getElementById('transferAmount').value);
  const note     = document.getElementById('transferNote').value.trim();
  if (!username) { Toast.error('Enter recipient username'); return; }
  if (!amount)   { Toast.error('Enter transfer amount'); return; }
  const res = await API.post('/wallet/transfer', { username, amount, note });
  if (res?.success) { Toast.success('Transfer sent!'); Modal.close('transfer-modal'); setTimeout(()=>location.reload(),800); }
}

async function submitConvert() {
  const pts = parseInt(document.getElementById('convertPts').value);
  if (!pts || pts < MIN_WITHDRAW) { Toast.error('Minimum convert is ' + MIN_WITHDRAW + ' pts'); return; }
  const res = await API.post('/wallet/convert-points', { points: pts });
  if (res?.success) { Toast.success('Points converted!'); Modal.close('convert-modal'); setTimeout(()=>location.reload(),800); }
}
</script>
</body>
</html>
