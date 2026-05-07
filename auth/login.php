<?php
/**
 * Voxu — Login · Social login · Email verification
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/i18n.php';
if (auth()) redirect('/dashboard/feed.php');
$error   = '';
$success = !empty($_GET['verified']) ? '✅ Email verified! You can now log in.' : '';
$settings = getPlatformSettings();
$appName  = clean($settings['app_name'] ?? 'Voxu');
$theme    = getTheme();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (!Security::rateLimit('login_'.($_SERVER['REMOTE_ADDR']??''), 5, 60)) {
        $error = 'Too many attempts. Wait 1 minute.';
    } else {
        $email = strtolower(sanitize($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';
        if (!$email || !$pass) { $error = 'Please fill in both fields.'; }
        else {
            try { $user = DB::first('SELECT * FROM users WHERE email=? LIMIT 1', [$email]); }
            catch (Throwable) { $user = null; }
            if (!$user || !verifyPassword($pass, $user['password'])) { $error = 'Invalid email or password.'; }
            elseif ($user['status'] === 'suspended') { $error = 'Account suspended. Contact support.'; }
            elseif ($user['status'] === 'banned')    { $error = 'Account permanently banned.'; }
            else {
                loginUser((int)$user['id']);
                try { DB::insert('users_audit_logs',['user_id'=>(int)$user['id'],'action'=>'login','description'=>'Logged in','ip_address'=>$_SERVER['REMOTE_ADDR']??'','created_at'=>date('Y-m-d H:i:s')]); } catch(Throwable){}
                redirect('/dashboard/feed.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>" <?= isRtl()?'dir="rtl"':'' ?>>
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<meta name="csrf-token" content="<?= csrfToken() ?>"/>
<title>Sign In — <?= $appName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="/assets/css/voxu.css"/>
</head>
<body class="theme-<?= clean($theme) ?>">
<div class="auth-page">
  <div class="auth-left">
    <div style="position:relative;z-index:1;text-align:center;max-width:360px">
      <div style="font-family:'Poppins',sans-serif;font-size:52px;font-weight:800;margin-bottom:16px"><?= $appName ?><span style="color:rgba(255,255,255,.5)">.</span></div>
      <div style="font-size:22px;font-weight:700;margin-bottom:14px;line-height:1.4">Speak. Be Seen.<br/>Earn.</div>
      <p style="font-size:15px;opacity:.85;line-height:1.7">The voice-first social platform where authentic voices become your greatest asset.</p>
      <div style="display:flex;gap:24px;margin-top:32px;justify-content:center">
        <?php foreach([['🎙','Record'],['⚡','Earn'],['💸','Cash Out']] as [$ic,$lb]): ?>
        <div style="text-align:center"><div style="font-size:28px;margin-bottom:6px"><?=$ic?></div><div style="font-size:12px;opacity:.8;font-weight:600"><?=$lb?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="auth-right">
    <div class="auth-form">
      <a href="/" style="display:block;margin-bottom:28px;font-family:'Poppins',sans-serif;font-size:22px;font-weight:800;color:var(--text)"><?= $appName ?><span style="color:var(--purple)">.</span></a>
      <div class="auth-title">Welcome back</div>
      <p class="auth-sub">Sign in to your <?= $appName ?> account</p>
      <?php if ($error): ?><div class="alert alert-danger"><?= clean($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= clean($success) ?></div><?php endif; ?>
      <div class="social-btns">
        <a href="/auth/social.php?provider=google" class="btn-social btn-social-google">
          <svg width="20" height="20" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
          Continue with Google
        </a>
        <a href="/auth/social.php?provider=facebook" class="btn-social btn-social-facebook">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          Continue with Facebook
        </a>
        <a href="/auth/social.php?provider=twitter" class="btn-social btn-social-twitter">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          Continue with X (Twitter)
        </a>
      </div>
      <div class="auth-divider">or sign in with email</div>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
        <div class="input-group">
          <label class="input-label">Email address</label>
          <div class="input-icon-wrap">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input class="input" type="email" name="email" placeholder="you@example.com" required autocomplete="email"/>
          </div>
        </div>
        <div class="input-group">
          <label class="input-label" style="display:flex;align-items:center;justify-content:space-between">Password <a href="/auth/forgot-password.php" style="font-size:12px;color:var(--purple);font-weight:600">Forgot?</a></label>
          <div class="input-icon-wrap" style="position:relative">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input class="input" type="password" name="password" id="pwdInput" placeholder="••••••••" required autocomplete="current-password" style="padding-right:44px"/>
            <button type="button" onclick="togglePwd()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text3);padding:2px">
              <svg id="pwdEye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-full" style="border-radius:999px;padding:13px;font-size:15px;margin-top:4px">Sign In</button>
      </form>
      <p style="text-align:center;margin-top:20px;font-size:14px;color:var(--text3)">No account? <a href="/auth/register.php" style="color:var(--purple);font-weight:700">Sign up free</a></p>
    </div>
  </div>
</div>
<script>
function togglePwd(){const i=document.getElementById('pwdInput');i.type=i.type==='password'?'text':'password';}
</script>
</body></html>
