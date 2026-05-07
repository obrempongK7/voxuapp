<?php
/**
 * Voxu — User Settings · Socimo-inspired layout
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
$appName  = clean($settings['app_name'] ?? 'Voxu');
$theme    = getTheme();
$section  = sanitize($_GET['s'] ?? 'profile');
$success  = '';
$error    = '';

// Load profile
try {
    $profile = DB::first(
        "SELECT * FROM user_profiles WHERE user_id=?", [$userId]
    ) ?? [];
} catch (Throwable) { $profile = []; }

// ── POST HANDLER ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'profile') {
        $bio       = sanitize($_POST['bio']          ?? '');
        $website   = sanitizeUrl($_POST['website']   ?? '');
        $occ       = sanitize($_POST['occupation']   ?? '');
        $company   = sanitize($_POST['company']      ?? '');
        $city      = sanitize($_POST['city']         ?? '');
        $country   = sanitize($_POST['country']      ?? '');
        $education = sanitize($_POST['education']    ?? '');
        $skills    = sanitize($_POST['skills']       ?? '');
        $interests = sanitize($_POST['interests']    ?? '');
        $twitter   = sanitize($_POST['twitter']      ?? '');
        $instagram = sanitize($_POST['instagram']    ?? '');
        $facebook  = sanitize($_POST['facebook']     ?? '');
        $linkedin  = sanitize($_POST['linkedin']     ?? '');
        $gender    = in_array($_POST['gender']??'', ['male','female','non_binary','prefer_not']) ? $_POST['gender'] : 'prefer_not';
        $rel       = in_array($_POST['relationship_status']??'', ['single','in_relationship','married','prefer_not']) ? $_POST['relationship_status'] : 'prefer_not';
        $dob       = sanitize($_POST['date_of_birth'] ?? '');
        $dob       = $dob && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) ? $dob : null;

        try {
            $exists = DB::count('user_profiles', 'user_id=?', [$userId]);
            $fields = [
                'bio'=>$bio,'website'=>$website,'occupation'=>$occ,'company'=>$company,
                'city'=>$city,'country'=>$country,'education'=>$education,
                'skills'=>$skills,'interests'=>$interests,
                'twitter'=>$twitter,'instagram'=>$instagram,'facebook'=>$facebook,'linkedin'=>$linkedin,
                'gender'=>$gender,'relationship_status'=>$rel,'date_of_birth'=>$dob,
            ];
            if ($exists) {
                DB::update('user_profiles', $fields, ['user_id' => $userId]);
            } else {
                $fields['user_id'] = $userId;
                DB::insert('user_profiles', $fields);
            }
            $success = 'Profile updated successfully!';
            $profile = array_merge($profile, $fields);
        } catch (Throwable $e) {
            $error = 'Failed to save: ' . $e->getMessage();
        }

    } elseif ($action === 'avatar') {
        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $up = uploadFile($_FILES['avatar'], 'avatar');
            if ($up['ok']) {
                try {
                    $exists = DB::count('user_profiles', 'user_id=?', [$userId]);
                    if ($exists) DB::update('user_profiles', ['avatar' => $up['url']], ['user_id' => $userId]);
                    else DB::insert('user_profiles', ['user_id' => $userId, 'avatar' => $up['url']]);
                    $success = 'Profile photo updated!';
                } catch (Throwable) { $error = 'DB error saving avatar'; }
            } else {
                $error = $up['error'];
            }
        } else {
            $error = 'Please select a photo to upload.';
        }

    } elseif ($action === 'cover') {
        if (!empty($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $up = uploadFile($_FILES['cover'], 'image');
            if ($up['ok']) {
                try {
                    $exists = DB::count('user_profiles', 'user_id=?', [$userId]);
                    if ($exists) DB::update('user_profiles', ['cover_photo' => $up['url']], ['user_id' => $userId]);
                    else DB::insert('user_profiles', ['user_id' => $userId, 'cover_photo' => $up['url']]);
                    $success = 'Cover photo updated!';
                } catch (Throwable) { $error = 'DB error saving cover'; }
            } else { $error = $up['error']; }
        }

    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!$current || !$new || !$confirm) {
            $error = 'All password fields are required.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } else {
            try {
                $u = DB::first('SELECT password FROM users WHERE id=?', [$userId]);
                if (!$u || !verifyPassword($current, $u['password'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    DB::exec('UPDATE users SET password=? WHERE id=?', [hashPassword($new), $userId]);
                    $success = 'Password changed successfully!';
                }
            } catch (Throwable) { $error = 'Failed to update password.'; }
        }

    } elseif ($action === 'account') {
        $newUsername = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', sanitize($_POST['username'] ?? '')));
        if ($newUsername && $newUsername !== $user['username']) {
            if (strlen($newUsername) < 3) { $error = 'Username must be at least 3 characters.'; }
            elseif (DB::count('users', 'username=? AND id!=?', [$newUsername, $userId]) > 0) { $error = 'Username already taken.'; }
            else {
                DB::exec('UPDATE users SET username=? WHERE id=?', [$newUsername, $userId]);
                $success = 'Username updated!';
            }
        }

    } elseif ($action === 'theme') {
        $t = in_array($_POST['theme'] ?? '', ['dark','light']) ? $_POST['theme'] : 'dark';
        setcookie('voxu_theme', $t, time() + 60*60*24*365, '/');
        $success = 'Theme preference saved!';
        $theme   = $t;
    }

    // Redirect after POST to prevent resubmit
    if (!$error) {
        redirect('/dashboard/settings.php?s=' . $section . '&saved=1');
    }
}

if (!empty($_GET['saved'])) $success = 'Changes saved!';
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>" <?= isRtl() ? 'dir="rtl"' : '' ?>>
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta name="csrf-token" content="<?= csrfToken() ?>"/>
  <title>Settings — <?= $appName ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
</head>
<body class="theme-<?= clean($theme) ?>">

<!-- TOP NAV -->
<nav class="sk-topnav">
  <a href="/dashboard/feed.php" class="sk-logo"><?= $appName ?><span class="dot">.</span></a>
  <div style="position:absolute;left:50%;transform:translateX(-50%);font-size:16px;font-weight:700;color:var(--text)">⚙️ Settings</div>
  <div class="sk-nav-actions">
    <a href="/dashboard/notifications.php" class="sk-nav-btn">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </a>
    <a href="/dashboard/profile.php" style="flex-shrink:0">
      <div class="avatar avatar-sm">
        <?php if(!empty($user['avatar'])): ?><img src="<?= clean($user['avatar']) ?>" alt="me"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?>
      </div>
    </a>
  </div>
</nav>

<div style="padding-top:var(--nav-h);min-height:100vh">
<div class="settings-layout">

  <!-- SETTINGS SIDEBAR -->
  <div class="settings-tabs-col">
    <?php
    $tabs = [
      ['profile',  '👤', 'Profile Info'],
      ['photos',   '📷', 'Photos'],
      ['account',  '🔑', 'Account'],
      ['password', '🔒', 'Password'],
      ['language', '🌍', 'Language'],
      ['theme',    '🎨', 'Appearance'],
      ['privacy',  '🛡️', 'Privacy'],
    ];
    foreach ($tabs as [$key,$icon,$label]):
    ?>
    <a href="?s=<?= $key ?>" class="settings-tab <?= $section===$key?'active':'' ?>">
      <span><?= $icon ?></span>
      <span><?= $label ?></span>
    </a>
    <?php endforeach; ?>
    <div style="margin-top:auto;padding-top:20px">
      <a href="/auth/logout.php" class="settings-tab" style="color:var(--danger)">
        <span>🚪</span><span>Sign Out</span>
      </a>
    </div>
  </div>

  <!-- SETTINGS BODY -->
  <div class="settings-body">
    <?php if ($error): ?>
      <div class="alert alert-danger mb-4"><?= clean($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success mb-4">✅ <?= clean($success) ?></div>
    <?php endif; ?>

    <?php if ($section === 'profile'): ?>
    <!-- ── PROFILE INFO ───────────────────────────────── -->
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
      <input type="hidden" name="action" value="profile"/>

      <div class="settings-section">
        <div class="settings-section-title">Basic Information</div>
        <div class="input-group">
          <label class="input-label">Bio</label>
          <textarea class="input" name="bio" rows="3" maxlength="300" placeholder="Tell people about yourself…"><?= clean($profile['bio'] ?? '') ?></textarea>
          <div class="input-hint">Max 300 characters</div>
        </div>
        <div class="form-row">
          <div class="input-group">
            <label class="input-label">Occupation</label>
            <input class="input" type="text" name="occupation" value="<?= clean($profile['occupation'] ?? '') ?>" placeholder="e.g. Software Engineer"/>
          </div>
          <div class="input-group">
            <label class="input-label">Company / Organisation</label>
            <input class="input" type="text" name="company" value="<?= clean($profile['company'] ?? '') ?>" placeholder="e.g. Google"/>
          </div>
        </div>
        <div class="form-row">
          <div class="input-group">
            <label class="input-label">City</label>
            <input class="input" type="text" name="city" value="<?= clean($profile['city'] ?? '') ?>" placeholder="Accra"/>
          </div>
          <div class="input-group">
            <label class="input-label">Country</label>
            <input class="input" type="text" name="country" value="<?= clean($profile['country'] ?? '') ?>" placeholder="Ghana"/>
          </div>
        </div>
        <div class="input-group">
          <label class="input-label">Education</label>
          <input class="input" type="text" name="education" value="<?= clean($profile['education'] ?? '') ?>" placeholder="e.g. BSc Computer Science, University of Ghana"/>
        </div>
        <div class="input-group">
          <label class="input-label">Website</label>
          <input class="input" type="url" name="website" value="<?= clean($profile['website'] ?? '') ?>" placeholder="https://yoursite.com"/>
        </div>
      </div>

      <div class="settings-section">
        <div class="settings-section-title">Personal Details</div>
        <div class="form-row">
          <div class="input-group">
            <label class="input-label">Gender</label>
            <select class="input" name="gender">
              <option value="prefer_not" <?= ($profile['gender']??'')==='prefer_not'?'selected':'' ?>>Prefer not to say</option>
              <option value="male" <?= ($profile['gender']??'')==='male'?'selected':'' ?>>Male</option>
              <option value="female" <?= ($profile['gender']??'')==='female'?'selected':'' ?>>Female</option>
              <option value="non_binary" <?= ($profile['gender']??'')==='non_binary'?'selected':'' ?>>Non-binary</option>
            </select>
          </div>
          <div class="input-group">
            <label class="input-label">Date of Birth</label>
            <input class="input" type="date" name="date_of_birth" value="<?= clean($profile['date_of_birth'] ?? '') ?>"/>
          </div>
        </div>
        <div class="input-group">
          <label class="input-label">Relationship Status</label>
          <select class="input" name="relationship_status">
            <option value="prefer_not" <?= ($profile['relationship_status']??'')==='prefer_not'?'selected':'' ?>>Prefer not to say</option>
            <option value="single" <?= ($profile['relationship_status']??'')==='single'?'selected':'' ?>>Single</option>
            <option value="in_relationship" <?= ($profile['relationship_status']??'')==='in_relationship'?'selected':'' ?>>In a Relationship</option>
            <option value="married" <?= ($profile['relationship_status']??'')==='married'?'selected':'' ?>>Married</option>
          </select>
        </div>
      </div>

      <div class="settings-section">
        <div class="settings-section-title">Skills & Interests</div>
        <div class="input-group">
          <label class="input-label">Skills <span class="input-hint" style="display:inline">(comma-separated)</span></label>
          <input class="input" type="text" name="skills" value="<?= clean($profile['skills'] ?? '') ?>" placeholder="PHP, JavaScript, Public Speaking…"/>
        </div>
        <div class="input-group">
          <label class="input-label">Interests</label>
          <input class="input" type="text" name="interests" value="<?= clean($profile['interests'] ?? '') ?>" placeholder="Music, Tech, Sports, Travel…"/>
        </div>
      </div>

      <div class="settings-section">
        <div class="settings-section-title">Social Links</div>
        <div class="form-row">
          <div class="input-group">
            <label class="input-label">Twitter / X</label>
            <input class="input" type="text" name="twitter" value="<?= clean($profile['twitter'] ?? '') ?>" placeholder="@username"/>
          </div>
          <div class="input-group">
            <label class="input-label">Instagram</label>
            <input class="input" type="text" name="instagram" value="<?= clean($profile['instagram'] ?? '') ?>" placeholder="@username"/>
          </div>
        </div>
        <div class="form-row">
          <div class="input-group">
            <label class="input-label">Facebook</label>
            <input class="input" type="text" name="facebook" value="<?= clean($profile['facebook'] ?? '') ?>" placeholder="facebook.com/username"/>
          </div>
          <div class="input-group">
            <label class="input-label">LinkedIn</label>
            <input class="input" type="text" name="linkedin" value="<?= clean($profile['linkedin'] ?? '') ?>" placeholder="linkedin.com/in/username"/>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="border-radius:999px;padding:13px 32px">💾 Save Profile</button>
    </form>

    <?php elseif ($section === 'photos'): ?>
    <!-- ── PHOTOS ────────────────────────────────────── -->
    <div class="settings-section">
      <div class="settings-section-title">Profile Photo</div>
      <div style="display:flex;align-items:center;gap:20px;margin-bottom:20px">
        <div class="avatar avatar-lg">
          <?php if(!empty($profile['avatar'])): ?><img src="<?= clean($profile['avatar']) ?>" alt="avatar"/><?php else: ?><?= avatarInitials($user['username']) ?><?php endif; ?>
        </div>
        <div>
          <p style="font-size:14px;color:var(--text2);margin-bottom:10px">JPG, PNG or WebP. Max 5MB.</p>
          <form method="POST" enctype="multipart/form-data" id="avatarForm">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
            <input type="hidden" name="action" value="avatar"/>
            <label class="btn btn-secondary" style="border-radius:999px;cursor:pointer">
              📷 Change Photo
              <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="document.getElementById('avatarForm').submit()"/>
            </label>
          </form>
        </div>
      </div>
    </div>
    <div class="settings-section">
      <div class="settings-section-title">Cover Photo</div>
      <div style="height:140px;border-radius:var(--radius);background:var(--grad-cover);overflow:hidden;margin-bottom:16px;position:relative">
        <?php if(!empty($profile['cover_photo'])): ?><img src="<?= clean($profile['cover_photo']) ?>" style="width:100%;height:100%;object-fit:cover" alt="cover"/><?php endif; ?>
      </div>
      <form method="POST" enctype="multipart/form-data" id="coverForm">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action" value="cover"/>
        <label class="btn btn-secondary" style="border-radius:999px;cursor:pointer">
          🖼 Change Cover
          <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="document.getElementById('coverForm').submit()"/>
        </label>
      </form>
    </div>

    <?php elseif ($section === 'account'): ?>
    <!-- ── ACCOUNT ───────────────────────────────────── -->
    <div class="settings-section">
      <div class="settings-section-title">Account Details</div>
      <div style="margin-bottom:16px">
        <div class="input-label">Email</div>
        <div style="font-size:15px;color:var(--text);font-weight:600"><?= clean($user['email']) ?></div>
        <div class="input-hint">Email cannot be changed. Contact support if needed.</div>
      </div>
      <form method="POST">
        <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action" value="account"/>
        <div class="input-group">
          <label class="input-label">Username</label>
          <input class="input" type="text" name="username" value="<?= clean($user['username']) ?>" pattern="[a-zA-Z0-9_]{3,30}" minlength="3" maxlength="30"/>
          <div class="input-hint">3–30 characters. Letters, numbers and underscore only.</div>
        </div>
        <button type="submit" class="btn btn-primary" style="border-radius:999px">Save Username</button>
      </form>
    </div>
    <div class="settings-section">
      <div class="settings-section-title" style="color:var(--danger)">Danger Zone</div>
      <p style="font-size:14px;color:var(--text2);margin-bottom:14px">Deactivating your account will hide your profile and posts. You can reactivate by logging back in.</p>
      <button class="btn btn-danger" style="border-radius:999px" onclick="if(confirm('Deactivate your account?'))window.location='/auth/deactivate.php'">Deactivate Account</button>
    </div>

    <?php elseif ($section === 'password'): ?>
    <!-- ── PASSWORD ──────────────────────────────────── -->
    <div class="settings-section">
      <div class="settings-section-title">Change Password</div>
      <form method="POST">
        <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action" value="password"/>
        <div class="input-group">
          <label class="input-label">Current Password</label>
          <input class="input" type="password" name="current_password" required placeholder="Enter current password"/>
        </div>
        <div class="input-group">
          <label class="input-label">New Password</label>
          <input class="input" type="password" name="new_password" required minlength="8" placeholder="At least 8 characters"/>
        </div>
        <div class="input-group">
          <label class="input-label">Confirm New Password</label>
          <input class="input" type="password" name="confirm_password" required placeholder="Repeat new password"/>
        </div>
        <button type="submit" class="btn btn-primary" style="border-radius:999px">🔒 Update Password</button>
      </form>
    </div>

    <?php elseif ($section === 'language'): ?>
    <!-- ── LANGUAGE ──────────────────────────────────── -->
    <div class="settings-section">
      <div class="settings-section-title">Choose Language</div>
      <p style="font-size:14px;color:var(--text2);margin-bottom:16px">Select your preferred language. Changes apply immediately without a page reload.</p>
      <div style="display:flex;flex-direction:column;gap:6px">
        <?php foreach (VOXU_LANGUAGES as $code => $meta): ?>
        <div class="sk-lang-opt <?= getCurrentLang()===$code?'active':'' ?>"
          onclick="setLangSetting('<?= $code ?>')"
          style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:var(--radius);border:1px solid var(--border);cursor:pointer;transition:var(--transition)">
          <span style="font-size:22px"><?= $meta['flag'] ?></span>
          <div style="flex:1">
            <div style="font-size:15px;font-weight:700;color:var(--text)"><?= htmlspecialchars($meta['native']) ?></div>
            <div style="font-size:12px;color:var(--text3)"><?= htmlspecialchars($meta['name']) ?></div>
          </div>
          <?php if(getCurrentLang()===$code): ?>
          <span class="badge badge-purple">✓ Active</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($section === 'theme'): ?>
    <!-- ── APPEARANCE ────────────────────────────────── -->
    <div class="settings-section">
      <div class="settings-section-title">Appearance</div>
      <p style="font-size:14px;color:var(--text2);margin-bottom:20px">Choose how <?= $appName ?> looks to you.</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px">
        <?php foreach ([
          ['dark' , '🌙', 'Dark',  'Easy on the eyes at night'],
          ['light', '☀️', 'Light', 'Clean and bright'],
        ] as [$val,$ic,$lbl,$desc]):
          $isActive = $theme === $val;
        ?>
        <div onclick="applyTheme('<?= $val ?>')"
          style="background:<?= $val==='dark'?'#0D0D14':'#F0F2F8' ?>;border:2px solid <?= $isActive?'var(--purple)':'var(--border)' ?>;border-radius:var(--radius-lg);padding:20px;cursor:pointer;transition:var(--transition);text-align:center">
          <div style="font-size:32px;margin-bottom:8px"><?= $ic ?></div>
          <div style="font-size:15px;font-weight:700;color:<?= $val==='dark'?'#fff':'#1A1A2E' ?>"><?= $lbl ?></div>
          <div style="font-size:12px;color:<?= $val==='dark'?'rgba(255,255,255,.5)':'rgba(0,0,0,.4)' ?>;margin-top:4px"><?= $desc ?></div>
          <?php if($isActive): ?><div style="color:var(--purple);font-size:12px;font-weight:700;margin-top:8px">✓ Active</div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <form method="POST" id="themeForm">
        <input type="hidden" name="_csrf"   value="<?= csrfToken() ?>"/>
        <input type="hidden" name="action"  value="theme"/>
        <input type="hidden" name="theme"   id="themeInput" value="<?= clean($theme) ?>"/>
      </form>
    </div>

    <?php elseif ($section === 'privacy'): ?>
    <!-- ── PRIVACY ───────────────────────────────────── -->
    <div class="settings-section">
      <div class="settings-section-title">Privacy Controls</div>
      <div style="display:flex;flex-direction:column;gap:16px">
        <?php foreach ([
          ['Who can message you',    'Anyone can send a message request'],
          ['Show activity status',   'Let others see when you were last active'],
          ['Visible in search',      'Allow your profile to appear in search results'],
          ['Show earning stats',     'Display your points balance on your profile'],
        ] as [$lbl,$desc]): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border)">
          <div><div style="font-size:14px;font-weight:600;color:var(--text)"><?= $lbl ?></div>
          <div style="font-size:12px;color:var(--text3);margin-top:3px"><?= $desc ?></div></div>
          <label class="toggle"><input type="checkbox" checked/><span class="toggle-track"></span></label>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-primary" style="border-radius:999px;margin-top:16px">Save Privacy Settings</button>
    </div>
    <?php endif; ?>

  </div><!-- /settings-body -->
</div><!-- /settings-layout -->
</div>

<!-- MOBILE BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="/dashboard/feed.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>Home</a>
  <a href="/dashboard/notifications.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>Alerts</a>
  <a href="/dashboard/wallet.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Wallet</a>
  <a href="/dashboard/profile.php" class="bottom-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profile</a>
  <a href="/dashboard/settings.php" class="bottom-nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Settings</a>
</nav>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
function applyTheme(t) {
  document.body.className = 'theme-' + t;
  document.cookie = 'voxu_theme=' + t + ';path=/;max-age=31536000';
  document.getElementById('themeInput').value = t;
  document.getElementById('themeForm').submit();
}
function setLangSetting(code) {
  document.cookie = 'voxu_lang=' + encodeURIComponent(code) + ';path=/;max-age=31536000;SameSite=Lax';
  fetch('/api/v1/user/set-lang', {
    method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/json','X-CSRF-Token':getCsrfToken()||'','X-Requested-With':'XMLHttpRequest'},
    body:JSON.stringify({lang:code})
  }).finally(() => { Toast.success('Language changed!'); setTimeout(() => location.reload(), 800); });
}
VoxuI18n.init();
</script>
</body>
</html>
