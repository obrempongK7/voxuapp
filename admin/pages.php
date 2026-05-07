<?php
/**
 * Voxu Admin — Page & Content Manager
 * Edit About Us, App Links, Landing Page Sections, Static Pages
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
requireAdmin();
Security::requireRole('admin');

$admin      = DB::first('SELECT * FROM admins WHERE id=?', [$_SESSION['admin_id']]);
$activeMenu = 'pages';
$success    = '';
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $section = $_POST['section'] ?? '';

    $allowed = ['super_admin','admin'];
    if (!in_array($admin['role'], $allowed)) {
        $error = 'You do not have permission to edit page content.';
    } else {
        $saved = 0;
        switch ($section) {
            case 'app_links':
                $keys = ['app_link_ios','app_link_android','app_link_huawei','app_link_pwa','app_download_headline','app_download_desc'];
                foreach ($keys as $k) {
                    setSetting($k, sanitize($_POST[$k] ?? ''));
                    $saved++;
                }
                break;

            case 'about':
                $keys = ['page_about_hero','page_about_mission','page_about_vision','page_about_team','page_about_founded'];
                foreach ($keys as $k) {
                    setSetting($k, sanitize($_POST[$k] ?? ''));
                    $saved++;
                }
                break;

            case 'hero':
                $keys = ['hero_headline','hero_subtext','hero_trust_line','hero_btn_primary','hero_btn_secondary'];
                foreach ($keys as $k) {
                    setSetting($k, sanitize($_POST[$k] ?? ''));
                    $saved++;
                }
                break;

            case 'faq':
                // Save FAQ as JSON
                $questions = $_POST['faq_q'] ?? [];
                $answers   = $_POST['faq_a'] ?? [];
                $faqs      = [];
                for ($i = 0; $i < count($questions); $i++) {
                    $q = sanitize($questions[$i] ?? '');
                    $a = sanitize($answers[$i]   ?? '');
                    if ($q && $a) $faqs[] = [$q, $a];
                }
                setSetting('page_faqs', json_encode($faqs));
                $saved++;
                break;

            case 'social':
                $keys = ['social_twitter','social_instagram','social_tiktok','social_facebook','social_linkedin','social_youtube'];
                foreach ($keys as $k) {
                    setSetting($k, sanitize($_POST[$k] ?? ''));
                    $saved++;
                }
                break;

            case 'seo':
                $keys = ['meta_title','meta_description','meta_keywords','google_analytics','custom_head_code','custom_footer_code'];
                foreach ($keys as $k) {
                    setSetting($k, sanitize($_POST[$k] ?? ''));
                    $saved++;
                }
                break;
        }
        logAdminAction((int)$admin['id'], 'pages_update', "Updated page section: {$section} ({$saved} settings)");
        $success = ucfirst($section) . ' section saved successfully.';
    }
}

$s = getPlatformSettings();
function sv(array $s, string $k, string $d = ''): string { return htmlspecialchars($s[$k] ?? $d, ENT_QUOTES); }

// Load FAQ
$faqs = json_decode($s['page_faqs'] ?? '[]', true) ?: [
    ['Is Voxu free to use?',       'Yes! Voxu is completely free to join and use.'],
    ['How do I earn?',             'Earn points by posting, getting replies, status views, contact clicks, and campaigns.'],
    ['How do I withdraw?',         'Go to Wallet → Withdraw, choose your method, and follow the process.'],
    ['Is there a minimum withdrawal?', 'Yes, 500 points minimum for free users.'],
    ['Is Voxu available as an app?',   'Yes, as a PWA you can add it to your home screen from your browser.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Page Manager — Voxu Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
  <style>
    .faq-entry{background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:10px}
    .faq-entry label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin-bottom:4px;display:block}
    .page-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:24px}
    .page-tab{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:500;color:var(--text2);background:var(--bg2);border:1px solid var(--border);cursor:pointer;transition:.2s}
    .page-tab.active{background:var(--purple-l);color:var(--purple);border-color:var(--purple)}
    .page-section{display:none}.page-section.active{display:block}
    .preview-btn{font-size:12px;color:var(--blue);text-decoration:none;padding:4px 8px;border-radius:6px;background:var(--blue-l);transition:.2s}
    .preview-btn:hover{background:var(--blue);color:var(--bg)}
  </style>
</head>
<body>
<?php $activeMenu = 'pages'; require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="admin-page-title">Page &amp; Content Manager</div>
    <div class="topbar-actions">
      <a href="/" target="_blank" class="preview-btn">↗ View Site</a>
      <a href="/about.php" target="_blank" class="preview-btn">↗ About Page</a>
    </div>
  </div>
  <div class="admin-content">
    <?php if ($success): ?><div class="alert alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">⚠ <?= clean($error)   ?></div><?php endif; ?>

    <!-- TAB BUTTONS -->
    <div class="page-tabs">
      <div class="page-tab active" onclick="switchTab('app_links')">📱 App Links</div>
      <div class="page-tab"       onclick="switchTab('about')">📖 About Us</div>
      <div class="page-tab"       onclick="switchTab('hero')">🏠 Hero Section</div>
      <div class="page-tab"       onclick="switchTab('faq')">❓ FAQ</div>
      <div class="page-tab"       onclick="switchTab('social')">🌐 Social Links</div>
      <div class="page-tab"       onclick="switchTab('seo')">🔍 SEO &amp; Analytics</div>
    </div>

    <!-- ── APP LINKS TAB ──────────────────────────────── -->
    <div class="page-section active" id="tab-app_links">
      <form method="POST">
        <input type="hidden" name="_csrf"    value="<?= csrfToken() ?>"/>
        <input type="hidden" name="section"  value="app_links"/>
        <div class="form-section">
          <div class="form-section-title">📱 Mobile App Download Links</div>
          <p style="font-size:13px;color:var(--text2);margin-bottom:16px">These links appear in the landing page, About page, and user profile footer. Leave blank to hide that platform's button.</p>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">🍎 Apple App Store URL</label>
              <input class="form-input" type="url" name="app_link_ios" value="<?= sv($s,'app_link_ios') ?>" placeholder="https://apps.apple.com/app/…"/>
            </div>
            <div class="form-group">
              <label class="form-label">🤖 Google Play Store URL</label>
              <input class="form-input" type="url" name="app_link_android" value="<?= sv($s,'app_link_android') ?>" placeholder="https://play.google.com/store/apps/…"/>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">📦 Huawei AppGallery URL</label>
              <input class="form-input" type="url" name="app_link_huawei" value="<?= sv($s,'app_link_huawei') ?>" placeholder="https://appgallery.huawei.com/…"/>
            </div>
            <div class="form-group">
              <label class="form-label">🌐 PWA / Web App URL</label>
              <input class="form-input" type="url" name="app_link_pwa" value="<?= sv($s,'app_link_pwa',APP_URL.'/dashboard/') ?>" placeholder="https://yourdomain.com/dashboard/"/>
            </div>
          </div>
        </div>
        <div class="form-section">
          <div class="form-section-title">Download Section Text</div>
          <div class="form-group">
            <label class="form-label">Headline</label>
            <input class="form-input" name="app_download_headline" value="<?= sv($s,'app_download_headline','Get the Voxu App') ?>" maxlength="80"/>
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <input class="form-input" name="app_download_desc" value="<?= sv($s,'app_download_desc','Available on iOS, Android, and as a Progressive Web App.') ?>" maxlength="160"/>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save App Links</button>
      </form>
    </div>

    <!-- ── ABOUT TAB ──────────────────────────────────── -->
    <div class="page-section" id="tab-about">
      <form method="POST">
        <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
        <input type="hidden" name="section" value="about"/>
        <div class="form-section">
          <div class="form-section-title">About Us Page Content <a href="/about.php" target="_blank" class="preview-btn" style="margin-left:10px">Preview ↗</a></div>
          <div class="form-group">
            <label class="form-label">Hero Statement (main headline paragraph)</label>
            <textarea class="form-input" name="page_about_hero" rows="3" maxlength="300"><?= sv($s,'page_about_hero') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Mission Statement</label>
            <textarea class="form-input" name="page_about_mission" rows="3" maxlength="500"><?= sv($s,'page_about_mission') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Vision Statement</label>
            <textarea class="form-input" name="page_about_vision" rows="3" maxlength="500"><?= sv($s,'page_about_vision') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Team Description</label>
            <textarea class="form-input" name="page_about_team" rows="3" maxlength="500"><?= sv($s,'page_about_team') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Year Founded</label>
            <input class="form-input" name="page_about_founded" value="<?= sv($s,'page_about_founded','2024') ?>" maxlength="4" style="max-width:120px"/>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save About Page</button>
      </form>
    </div>

    <!-- ── HERO TAB ───────────────────────────────────── -->
    <div class="page-section" id="tab-hero">
      <form method="POST">
        <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
        <input type="hidden" name="section" value="hero"/>
        <div class="form-section">
          <div class="form-section-title">Landing Page Hero Section <a href="/" target="_blank" class="preview-btn" style="margin-left:10px">Preview ↗</a></div>
          <div class="form-group">
            <label class="form-label">Hero Headline (3 words, e.g. "Speak. Be Seen. Earn.")</label>
            <input class="form-input" name="hero_headline" value="<?= sv($s,'hero_headline','Speak. Be Seen. Earn.') ?>" maxlength="80"/>
          </div>
          <div class="form-group">
            <label class="form-label">Hero Subtext</label>
            <textarea class="form-input" name="hero_subtext" rows="2" maxlength="200"><?= sv($s,'hero_subtext','Share your voice. Post your status. Get real attention and earn from it.') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Trust Line (below buttons, e.g. "Join thousands…")</label>
            <input class="form-input" name="hero_trust_line" value="<?= sv($s,'hero_trust_line','Join thousands of users earning daily') ?>" maxlength="80"/>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Primary Button Text</label>
              <input class="form-input" name="hero_btn_primary" value="<?= sv($s,'hero_btn_primary','Get Started Free →') ?>" maxlength="40"/>
            </div>
            <div class="form-group">
              <label class="form-label">Secondary Button Text</label>
              <input class="form-input" name="hero_btn_secondary" value="<?= sv($s,'hero_btn_secondary','See How It Works') ?>" maxlength="40"/>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Hero Section</button>
      </form>
    </div>

    <!-- ── FAQ TAB ────────────────────────────────────── -->
    <div class="page-section" id="tab-faq">
      <form method="POST" id="faqForm">
        <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
        <input type="hidden" name="section" value="faq"/>
        <div class="form-section">
          <div class="form-section-title">FAQ Items <span style="font-size:12px;color:var(--text3)">(shown on landing page and FAQ section)</span></div>
          <div id="faqList">
            <?php foreach ($faqs as $i => $faq): ?>
            <div class="faq-entry" id="faq-<?= $i ?>">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <span style="font-size:12px;font-weight:600;color:var(--text2)">FAQ #<?= $i+1 ?></span>
                <button type="button" onclick="removeFaq(<?= $i ?>)" style="color:var(--danger);font-size:12px;background:none;border:none;cursor:pointer">✕ Remove</button>
              </div>
              <label>Question</label>
              <input class="form-input" style="margin-bottom:8px" type="text" name="faq_q[]" value="<?= htmlspecialchars($faq[0],ENT_QUOTES) ?>" required/>
              <label>Answer</label>
              <textarea class="form-input" name="faq_a[]" rows="2"><?= htmlspecialchars($faq[1],ENT_QUOTES) ?></textarea>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" style="margin-top:10px" onclick="addFaq()">+ Add FAQ Item</button>
        </div>
        <button type="submit" class="btn btn-primary">Save FAQ</button>
      </form>
    </div>

    <!-- ── SOCIAL TAB ─────────────────────────────────── -->
    <div class="page-section" id="tab-social">
      <form method="POST">
        <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
        <input type="hidden" name="section" value="social"/>
        <div class="form-section">
          <div class="form-section-title">Social Media Links</div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">𝕏 Twitter / X URL</label><input class="form-input" type="url" name="social_twitter"   value="<?= sv($s,'social_twitter')   ?>" placeholder="https://x.com/…"/></div>
            <div class="form-group"><label class="form-label">📷 Instagram URL</label>   <input class="form-input" type="url" name="social_instagram" value="<?= sv($s,'social_instagram') ?>" placeholder="https://instagram.com/…"/></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">🎵 TikTok URL</label>     <input class="form-input" type="url" name="social_tiktok"    value="<?= sv($s,'social_tiktok')    ?>" placeholder="https://tiktok.com/…"/></div>
            <div class="form-group"><label class="form-label">📘 Facebook URL</label>   <input class="form-input" type="url" name="social_facebook"  value="<?= sv($s,'social_facebook')  ?>" placeholder="https://facebook.com/…"/></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">💼 LinkedIn URL</label>   <input class="form-input" type="url" name="social_linkedin"  value="<?= sv($s,'social_linkedin')  ?>" placeholder="https://linkedin.com/…"/></div>
            <div class="form-group"><label class="form-label">▶ YouTube URL</label>     <input class="form-input" type="url" name="social_youtube"   value="<?= sv($s,'social_youtube')   ?>" placeholder="https://youtube.com/…"/></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Social Links</button>
      </form>
    </div>

    <!-- ── SEO TAB ────────────────────────────────────── -->
    <div class="page-section" id="tab-seo">
      <form method="POST">
        <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
        <input type="hidden" name="section" value="seo"/>
        <div class="form-section">
          <div class="form-section-title">SEO Meta Tags</div>
          <div class="form-group"><label class="form-label">Meta Title (overrides app name)</label><input class="form-input" name="meta_title" value="<?= sv($s,'meta_title') ?>" maxlength="70" placeholder="Leave blank to use App Name"/></div>
          <div class="form-group"><label class="form-label">Meta Description</label><textarea class="form-input" name="meta_description" rows="2" maxlength="165"><?= sv($s,'meta_description') ?></textarea></div>
          <div class="form-group"><label class="form-label">Keywords (comma separated)</label><input class="form-input" name="meta_keywords" value="<?= sv($s,'meta_keywords') ?>" placeholder="voice platform, earn online, …"/></div>
        </div>
        <div class="form-section">
          <div class="form-section-title">Analytics &amp; Custom Code</div>
          <div class="form-group"><label class="form-label">Google Analytics / Tag Manager Script</label><textarea class="form-input" name="google_analytics" rows="4" placeholder="Paste your GA4 or GTM script tag here…"><?= sv($s,'google_analytics') ?></textarea></div>
          <div class="form-group"><label class="form-label">Custom &lt;head&gt; Code</label><textarea class="form-input" name="custom_head_code" rows="3" placeholder="Any additional scripts or meta tags to insert in &lt;head&gt;"><?= sv($s,'custom_head_code') ?></textarea></div>
          <div class="form-group"><label class="form-label">Custom Footer Code (before &lt;/body&gt;)</label><textarea class="form-input" name="custom_footer_code" rows="3" placeholder="Chat widgets, tracking pixels, etc."><?= sv($s,'custom_footer_code') ?></textarea></div>
        </div>
        <button type="submit" class="btn btn-primary">Save SEO Settings</button>
      </form>
    </div>

  </div>
</div>

<script>
let faqCount = <?= count($faqs) ?>;
function switchTab(tab) {
  document.querySelectorAll('.page-tab').forEach((t,i) => {
    const tabs = ['app_links','about','hero','faq','social','seo'];
    t.classList.toggle('active', tabs[i] === tab);
  });
  document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
}
function addFaq() {
  const n   = faqCount++;
  const div = document.createElement('div');
  div.className = 'faq-entry';
  div.id        = 'faq-' + n;
  div.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><span style="font-size:12px;font-weight:600;color:var(--text2)">FAQ #${n+1}</span><button type="button" onclick="removeFaq(${n})" style="color:var(--danger);font-size:12px;background:none;border:none;cursor:pointer">✕ Remove</button></div><label style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);display:block;margin-bottom:4px">Question</label><input class="form-input" style="margin-bottom:8px" type="text" name="faq_q[]" required/><label style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);display:block;margin-bottom:4px">Answer</label><textarea class="form-input" name="faq_a[]" rows="2"></textarea>`;
  document.getElementById('faqList').appendChild(div);
}
function removeFaq(n) {
  const el = document.getElementById('faq-' + n);
  if (el) el.remove();
}
</script>
</body>
</html>
