<?php
// @author  Jcode | ObrempongK
// admin/settings.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('admin');

try { $admin = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]); } catch (Throwable) { $admin = ['id'=>0,'name'=>'Admin','role'=>'admin','email'=>'']; }
$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'general';
    $allowed = ['super_admin','admin'];
    if (!in_array($admin['role'], $allowed)) {
        $error = 'You do not have permission to change settings.';
    } else {
        // Handle branding file uploads
        if ($action === 'branding') {
            $brandingTextKeys = ['brand_color','accent_color'];
            foreach ($brandingTextKeys as $bk) {
                if (isset($_POST[$bk])) setSetting($bk, sanitize($_POST[$bk]));
            }
            $fileFields = [
                'logo_file'     => ['logo_url',      'logo'],
                'icon192_file'  => ['icon_192_url',  'logo'],
                'icon512_file'  => ['icon_512_url',  'logo'],
                'og_image_file' => ['og_image_url',  'logo'],
            ];
            foreach ($fileFields as $fileKey => [$settingKey, $uploadType]) {
                if (!empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $up = uploadFile($_FILES[$fileKey], $uploadType);
                    if ($up['ok']) {
                        setSetting($settingKey, $up['url']);
                        // Copy icon files to the assets/uploads root for PWA manifest
                        if ($settingKey === 'icon_192_url') {
                            @copy(APP_DIR . $up['url'], APP_DIR . '/assets/uploads/icon-192.png');
                        }
                        if ($settingKey === 'icon_512_url') {
                            @copy(APP_DIR . $up['url'], APP_DIR . '/assets/uploads/icon-512.png');
                        }
                    } else {
                        $error = "Upload failed for {$fileKey}: " . $up['error'];
                    }
                }
            }
            logAdminAction((int)$admin['id'], 'branding_update', 'Updated branding settings');
            $saved = true;
        }

        $settingGroups = [
            'general'   => ['app_name','app_tagline','support_email','currency','currency_symbol',
                            'exchange_rate_usd','exchange_rate_note'],
            'earnings'  => ['points_per_post','points_per_reply','points_for_signup','reward_per_view','reward_per_click',
                            'max_daily_earnings','min_withdrawal','points_to_cash_rate','status_expiry_hours',
                            'boost_fee_starter','boost_fee_standard','boost_fee_pro','boost_fee_cash',
                            'boost_reach_starter','boost_reach_standard','boost_reach_pro','boost_reach_cash'],
            'features'  => ['feature_voice','feature_status','feature_campaigns','feature_groups',
                            'registration_open','maintenance_mode','allow_short_video'],
            'ads'       => ['ads_enabled','adsense_code'],
        ];
        $keys = $settingGroups[$action] ?? [];
        foreach ($keys as $key) {
            $val = isset($_POST[$key]) ? (is_array($_POST[$key]) ? implode(',', $_POST[$key]) : sanitize($_POST[$key])) : '0';
            setSetting($key, $val);
        }
        logAdminAction((int)$admin['id'], 'settings_update', "Updated {$action} settings");
        $saved = true;
    }
}

$s = getPlatformSettings();
$gateways = DB::query('SELECT * FROM payment_gateways ORDER BY name');

// Helper to get setting with default
function gs(array $s, string $k, string $d='') { return $s[$k] ?? $d; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Settings — Voxu Admin</title>
  <meta name="csrf" content="<?= csrfToken() ?>"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
</head>
<body>
<?php $activeMenu = 'settings'; require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <div class="admin-page-title">Platform Settings</div>
  </div>

  <div class="admin-content">
    <?php if ($saved): ?><div class="alert alert-success">✓ Settings saved successfully</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= clean($error) ?></div><?php endif; ?>

    <!-- SETTINGS TABS -->
    <div style="display:flex;gap:6px;margin-bottom:24px;flex-wrap:wrap">
      <?php foreach (['general'=>'⚙ General','branding'=>'🎨 Branding','earnings'=>'💰 Earnings','features'=>'🔧 Features','ads'=>'📢 Ads','gateways'=>'💳 Gateways'] as $tab=>$label): ?>
        <button class="btn btn-sm <?= $tab==='general'?'btn-primary':'btn-secondary' ?>" id="stab-<?=$tab?>" onclick="switchSettingTab('<?=$tab?>')"><?= $label ?></button>
      <?php endforeach; ?>
    </div>

    <!-- GENERAL -->
    <div id="spane-general">
      <form method="POST"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/><input type="hidden" name="action" value="general"/>
      <div class="form-section">
        <div class="form-section-title">App Identity</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">App Name</label><input class="form-input" name="app_name" value="<?= clean(gs($s,'app_name','Voxu')) ?>"/></div>
          <div class="form-group"><label class="form-label">Tagline</label><input class="form-input" name="app_tagline" value="<?= clean(gs($s,'app_tagline','Speak. Be Seen. Earn.')) ?>"/></div>
        </div>
        <div class="form-group"><label class="form-label">Support Email</label><input class="form-input" type="email" name="support_email" value="<?= clean(gs($s,'support_email')) ?>"/></div>
      </div>
      <div class="form-section">
        <div class="form-section-title">💱 Currency & Exchange Rate</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Currency Code</label>
            <select class="form-input" name="currency">
              <?php
              $currencies = ['USD'=>'$ US Dollar','EUR'=>'€ Euro','GBP'=>'£ British Pound',
                'GHS'=>'₵ Ghanaian Cedi','NGN'=>'₦ Nigerian Naira','KES'=>'KSh Kenyan Shilling',
                'ZAR'=>'R South African Rand','CAD'=>'CA$ Canadian Dollar',
                'AUD'=>'A$ Australian Dollar','INR'=>'₹ Indian Rupee','BRL'=>'R$ Brazilian Real',
                'JPY'=>'¥ Japanese Yen','CNY'=>'¥ Chinese Yuan','KRW'=>'₩ Korean Won',
                'RUB'=>'₽ Russian Ruble','IDR'=>'Rp Indonesian Rupiah','MXN'=>'MX$ Mexican Peso',
                'EGP'=>'£ Egyptian Pound','ZMW'=>'ZK Zambian Kwacha','UGX'=>'USh Ugandan Shilling'];
              $selCur = gs($s,'currency','USD');
              foreach ($currencies as $code => $label): ?>
                <option value="<?= $code ?>" <?= $selCur===$code?'selected':'' ?>><?= $code ?> — <?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Currency Symbol</label>
            <input class="form-input" name="currency_symbol" value="<?= clean(gs($s,'currency_symbol','$')) ?>" maxlength="5" placeholder="$, £, €, ₵ …"/>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Exchange Rate: 1 USD = ? <?= clean(gs($s,'currency','USD')) ?></label>
            <input class="form-input" type="number" name="exchange_rate_usd" value="<?= clean(gs($s,'exchange_rate_usd','1.00')) ?>" min="0.0001" step="0.0001" placeholder="e.g. 15.8 for GHS"/>
            <div class="form-hint">Used to convert earnings. Set to 1 if your currency IS USD.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Exchange Rate Note (shown to users)</label>
            <input class="form-input" name="exchange_rate_note" value="<?= clean(gs($s,'exchange_rate_note','')) ?>" placeholder="e.g. Rate updated daily · 1 USD = 15.8 GHS"/>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Save General Settings</button>
      </form>
    </div>

    <!-- BRANDING -->
    <div id="spane-branding" class="hidden">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action"  value="branding"/>
        <div class="form-section">
          <div class="form-section-title">🎨 Logo &amp; Icons</div>
          <p style="font-size:13px;color:var(--text2);margin-bottom:16px">Upload your app logo and PWA icons. Recommended sizes shown below each field.</p>

          <div class="form-row">
            <!-- SITE LOGO -->
            <div class="form-group">
              <label class="form-label">Site Logo (header)</label>
              <?php if (!empty($s['logo_url'])): ?>
                <div style="margin-bottom:8px;padding:10px;background:var(--bg3);border-radius:8px;display:inline-block">
                  <img src="<?= clean($s['logo_url']) ?>" alt="Logo" style="max-height:40px;max-width:180px;object-fit:contain"/>
                </div><br/>
              <?php endif; ?>
              <input class="form-input" type="file" name="logo_file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" id="logoInput"/>
              <div class="form-hint">PNG, SVG preferred · Transparent background recommended · Max 5MB</div>
            </div>

            <!-- FAVICON -->
            <div class="form-group">
              <label class="form-label">Favicon / PWA Icon 192×192</label>
              <?php if (!empty($s['icon_192_url'])): ?>
                <div style="margin-bottom:8px;padding:10px;background:var(--bg3);border-radius:8px;display:inline-block">
                  <img src="<?= clean($s['icon_192_url']) ?>" alt="Icon 192" style="width:48px;height:48px;object-fit:contain;border-radius:8px"/>
                </div><br/>
              <?php endif; ?>
              <input class="form-input" type="file" name="icon192_file" accept="image/jpeg,image/png,image/webp" id="icon192Input"/>
              <div class="form-hint">Exactly 192×192 px PNG · Used as PWA icon &amp; favicon</div>
            </div>
          </div>

          <div class="form-row">
            <!-- PWA ICON 512 -->
            <div class="form-group">
              <label class="form-label">PWA Icon 512×512</label>
              <?php if (!empty($s['icon_512_url'])): ?>
                <div style="margin-bottom:8px;padding:10px;background:var(--bg3);border-radius:8px;display:inline-block">
                  <img src="<?= clean($s['icon_512_url']) ?>" alt="Icon 512" style="width:64px;height:64px;object-fit:contain;border-radius:8px"/>
                </div><br/>
              <?php endif; ?>
              <input class="form-input" type="file" name="icon512_file" accept="image/jpeg,image/png,image/webp" id="icon512Input"/>
              <div class="form-hint">Exactly 512×512 px PNG · Used for app install splash screens</div>
            </div>

            <!-- OG IMAGE -->
            <div class="form-group">
              <label class="form-label">Social Share Image (OG Image)</label>
              <?php if (!empty($s['og_image_url'])): ?>
                <div style="margin-bottom:8px">
                  <img src="<?= clean($s['og_image_url']) ?>" alt="OG" style="max-height:60px;border-radius:6px;object-fit:cover"/>
                </div>
              <?php endif; ?>
              <input class="form-input" type="file" name="og_image_file" accept="image/jpeg,image/png,image/webp" id="ogInput"/>
              <div class="form-hint">1200×630 px · Shown when sharing links on social media</div>
            </div>
          </div>

          <!-- THEME COLORS -->
          <div class="form-section-title" style="margin-top:16px">Theme Colours</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Primary / Brand Colour</label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="color" name="brand_color" value="<?= clean($s['brand_color'] ?? '#6C3BFF') ?>" style="width:48px;height:38px;border:none;background:none;cursor:pointer;border-radius:6px"/>
                <input class="form-input" type="text" id="brandColorText" value="<?= clean($s['brand_color'] ?? '#6C3BFF') ?>" maxlength="7" style="flex:1"/>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Accent / Highlight Colour</label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="color" name="accent_color" value="<?= clean($s['accent_color'] ?? '#00D1FF') ?>" style="width:48px;height:38px;border:none;background:none;cursor:pointer;border-radius:6px"/>
                <input class="form-input" type="text" id="accentColorText" value="<?= clean($s['accent_color'] ?? '#00D1FF') ?>" maxlength="7" style="flex:1"/>
              </div>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Branding</button>
      </form>
    </div>

    <!-- EARNINGS -->
    <div id="spane-earnings" class="hidden">
      <form method="POST"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/><input type="hidden" name="action" value="earnings"/>
      <div class="form-section">
        <div class="form-section-title">Points Per Action</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Points per Voice Post</label><input class="form-input" type="number" name="points_per_post" value="<?= (int)gs($s,'points_per_post','5') ?>" min="0"/></div>
          <div class="form-group"><label class="form-label">Points per Reply</label><input class="form-input" type="number" name="points_per_reply" value="<?= (int)gs($s,'points_per_reply','2') ?>" min="0"/></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Points for Sign Up</label><input class="form-input" type="number" name="points_for_signup" value="<?= (int)gs($s,'points_for_signup','20') ?>" min="0"/></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Points per Status View</label><input class="form-input" type="number" name="reward_per_view" value="<?= (int)gs($s,'reward_per_view','1') ?>" min="0"/></div>
          <div class="form-group"><label class="form-label">Points per Contact Click</label><input class="form-input" type="number" name="reward_per_click" value="<?= (int)gs($s,'reward_per_click','3') ?>" min="0"/></div>
        </div>
      </div>
      <div class="form-section">
        <div class="form-section-title">Limits & Conversion</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Max Daily Earnings (pts)</label><input class="form-input" type="number" name="max_daily_earnings" value="<?= (int)gs($s,'max_daily_earnings','1000') ?>" min="1"/></div>
          <div class="form-group"><label class="form-label">Min Withdrawal (pts)</label><input class="form-input" type="number" name="min_withdrawal" value="<?= (int)gs($s,'min_withdrawal','500') ?>" min="1"/></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Points per 1 Currency Unit</label><input class="form-input" type="number" name="points_to_cash_rate" value="<?= (int)gs($s,'points_to_cash_rate','100') ?>" min="1"/><div class="form-hint">e.g. 100 pts = $1.00</div></div>
          <div class="form-group"><label class="form-label">Status Expiry (hours)</label><input class="form-input" type="number" name="status_expiry_hours" value="<?= (int)gs($s,'status_expiry_hours','24') ?>" min="1"/></div>
        </div>
      </div>
      <div class="form-section">
        <div class="form-section-title">🚀 Boost Post Fees</div>
        <p style="font-size:13px;color:var(--text2);margin-bottom:14px">Set the points/cash users pay to boost their posts. Reach is an approximate view estimate shown to users.</p>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
              <tr style="background:var(--bg3)">
                <th style="padding:10px;text-align:left;border:1px solid var(--border)">Tier</th>
                <th style="padding:10px;text-align:left;border:1px solid var(--border)">Cost (Points)</th>
                <th style="padding:10px;text-align:left;border:1px solid var(--border)">Cost (Cash <?= clean(gs($s,'currency_symbol','$')) ?>)</th>
                <th style="padding:10px;text-align:left;border:1px solid var(--border)">Est. Reach Label</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach([
                ['starter' ,'Starter','50' ,'0','~500 extra views'],
                ['standard','Standard','150','0','~2,000 extra views'],
                ['pro'     ,'Pro'    ,'400','0','~6,000 extra views'],
                ['cash'    ,'Cash'   ,'0'  ,'2','~5,000 extra views'],
              ] as [$key,$label,$defPts,$defCash,$defReach]): ?>
              <tr>
                <td style="padding:8px;border:1px solid var(--border);font-weight:600"><?= $label ?></td>
                <td style="padding:8px;border:1px solid var(--border)">
                  <input class="form-input" type="number" name="boost_fee_<?= $key ?>" min="0"
                    value="<?= clean(gs($s,"boost_fee_{$key}",$defPts)) ?>" style="width:100px"/>
                </td>
                <td style="padding:8px;border:1px solid var(--border)">
                  <input class="form-input" type="number" name="boost_fee_<?= $key ?>_cash" min="0" step="0.01"
                    value="<?= clean(gs($s,"boost_fee_{$key}_cash",$defCash)) ?>" style="width:100px"/>
                </td>
                <td style="padding:8px;border:1px solid var(--border)">
                  <input class="form-input" type="text" name="boost_reach_<?= $key ?>"
                    value="<?= clean(gs($s,"boost_reach_{$key}",$defReach)) ?>" style="width:160px"/>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
        // Also save cash boost fees when earnings saved
        $boostCashKeys = ['boost_fee_starter_cash','boost_fee_standard_cash','boost_fee_pro_cash','boost_fee_cash_cash'];
        $settingGroups['earnings'] = array_merge($settingGroups['earnings'] ?? [], $boostCashKeys);
        ?>
      </div>
      <button type="submit" class="btn btn-primary">Save Earnings Settings</button>
      </form>
    </div>

    <!-- FEATURES -->
    <div id="spane-features" class="hidden">
      <form method="POST"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/><input type="hidden" name="action" value="features"/>
      <div class="form-section">
        <div class="form-section-title">Platform Modules</div>
        <?php
        $featuresList = [
          'feature_voice'     => 'Voice Hub',
          'feature_status'    => 'Status Hub',
          'feature_campaigns' => 'Campaigns',
          'feature_groups'    => 'Groups',
        ];
        foreach ($featuresList as $key => $label):
        ?>
        <div class="form-group" style="flex-direction:row;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:12px;margin-bottom:12px">
          <div><label class="form-label" style="margin:0"><?= $label ?></label></div>
          <label class="toggle">
            <input type="checkbox" name="<?= $key ?>" value="1" <?= gs($s,$key,'1')==='1'?'checked':'' ?>/>
            <span class="toggle-track"></span>
          </label>
        </div>
        <?php endforeach; ?>
        <div class="form-group" style="flex-direction:row;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:12px;margin-bottom:12px">
          <div><label class="form-label" style="margin:0">Registration Open</label><div class="form-hint">Allow new users to register</div></div>
          <label class="toggle"><input type="checkbox" name="registration_open" value="1" <?= gs($s,'registration_open','1')==='1'?'checked':'' ?>/><span class="toggle-track"></span></label>
        </div>
        <div class="form-group" style="flex-direction:row;align-items:center;justify-content:space-between">
          <div><label class="form-label" style="margin:0;color:var(--warning)">⚠ Maintenance Mode</label><div class="form-hint">Shows maintenance page to users</div></div>
          <label class="toggle"><input type="checkbox" name="maintenance_mode" value="1" <?= gs($s,'maintenance_mode','0')==='1'?'checked':'' ?>/><span class="toggle-track"></span></label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Save Feature Settings</button>
      </form>
    </div>

    <!-- ADS -->
    <div id="spane-ads" class="hidden">
      <form method="POST"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/><input type="hidden" name="action" value="ads"/>
      <div class="form-section">
        <div class="form-section-title">Advertisement Settings</div>
        <div class="form-group" style="flex-direction:row;align-items:center;justify-content:space-between;margin-bottom:20px">
          <div><label class="form-label" style="margin:0">Enable Ads</label><div class="form-hint">Show Google AdSense ads to users</div></div>
          <label class="toggle"><input type="checkbox" name="ads_enabled" value="1" <?= gs($s,'ads_enabled','0')==='1'?'checked':'' ?>/><span class="toggle-track"></span></label>
        </div>
        <div class="form-group"><label class="form-label">Google AdSense Code</label><textarea class="form-input" name="adsense_code" rows="5" placeholder="Paste your AdSense script here..."><?= clean(gs($s,'adsense_code')) ?></textarea></div>
      </div>
      <button type="submit" class="btn btn-primary">Save Ad Settings</button>
      </form>
    </div>

    <!-- GATEWAYS -->
    <div id="spane-gateways" class="hidden">
      <div class="table-card">
        <div class="table-header">
          <span class="table-title">Payment Gateways</span>
        </div>
        <table>
          <thead><tr><th>Gateway</th><th>Deposit</th><th>Withdrawal</th><th>Fee %</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($gateways as $gw): ?>
            <tr>
              <td style="font-weight:600"><?= clean($gw['name']) ?></td>
              <td><?= $gw['supports_mobile']?'<span class="badge badge-green">Mobile</span>':'<span class="badge badge-muted">Card only</span>' ?></td>
              <td><?= $gw['supports_withdrawal']?'<span class="badge badge-green">Yes</span>':'<span class="badge badge-muted">No</span>' ?></td>
              <td><?= $gw['fee_percent'] ?>%</td>
              <td><?= $gw['is_active']?'<span class="badge badge-green">Active</span>':'<span class="badge badge-muted">Off</span>' ?></td>
              <td>
                <button class="btn btn-sm btn-secondary" onclick="editGateway(<?= $gw['id'] ?>,'<?= clean($gw['name']) ?>',<?= $gw['is_active'] ?>)">Configure</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- GATEWAY MODAL -->
<div class="modal-backdrop" id="gatewayModal">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <div class="admin-modal-title" id="gwModalTitle">Configure Gateway</div>
      <button onclick="document.getElementById('gatewayModal').classList.remove('open')" style="color:var(--text2)">✕</button>
    </div>
    <div class="admin-modal-body">
      <input type="hidden" id="gwId"/>
      <div class="form-group">
        <label class="form-label">API Key / Public Key</label>
        <input class="form-input" type="text" id="gwPublicKey" placeholder="pk_live_..."/>
      </div>
      <div class="form-group">
        <label class="form-label">Secret Key</label>
        <input class="form-input" type="password" id="gwSecretKey" placeholder="sk_live_..."/>
      </div>
      <div class="form-group" style="flex-direction:row;align-items:center;justify-content:space-between">
        <label class="form-label" style="margin:0">Enable Gateway</label>
        <label class="toggle"><input type="checkbox" id="gwActive"/><span class="toggle-track"></span></label>
      </div>
    </div>
    <div class="admin-modal-footer">
      <button class="btn btn-secondary" onclick="document.getElementById('gatewayModal').classList.remove('open')">Cancel</button>
      <button class="btn btn-primary" onclick="saveGateway()">Save</button>
    </div>
  </div>
</div>

<script>
function switchSettingTab(tab) {
  const tabs = ['general','branding','earnings','features','ads','gateways'];
  tabs.forEach(t => {
    document.getElementById('spane-'+t).classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('stab-'+t);
    if (btn) { btn.classList.toggle('btn-primary', t === tab); btn.classList.toggle('btn-secondary', t !== tab); }
  });
}

function editGateway(id, name, active) {
  document.getElementById('gwId').value = id;
  document.getElementById('gwModalTitle').textContent = 'Configure ' + name;
  document.getElementById('gwActive').checked = !!active;
  document.getElementById('gwPublicKey').value = '';
  document.getElementById('gwSecretKey').value = '';
  document.getElementById('gatewayModal').classList.add('open');
}

async function saveGateway() {
  const id     = document.getElementById('gwId').value;
  const pk     = document.getElementById('gwPublicKey').value;
  const sk     = document.getElementById('gwSecretKey').value;
  const active = document.getElementById('gwActive').checked ? 1 : 0;
  const csrfMeta = document.querySelector('meta[name="csrf"]');
  const csrfVal  = csrfMeta ? csrfMeta.getAttribute('content') : '';
  const res    = await fetch('/api/v1/admin/gateway/update', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfVal },
    body: JSON.stringify({ id, public_key: pk, secret_key: sk, is_active: active })
  });
  const d = await res.json();
  if (d.success) {
    alert('Gateway updated!');
    document.getElementById('gatewayModal').classList.remove('open');
    location.reload();
  } else {
    alert(d.message || 'Error saving gateway');
  }
}
</script>
</body>
</html>
