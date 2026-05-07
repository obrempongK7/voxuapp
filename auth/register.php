<?php
/**
 * Voxu — Register · Social signup · Email verification
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/i18n.php';
if (auth()) redirect('/dashboard/feed.php');
$error=$success='';
$settings=getPlatformSettings();
$appName=clean($settings['app_name']??'Voxu');
$theme=getTheme();
if ($settings['registration_open']??'1'!='1'){?>
<!DOCTYPE html><html><head><meta charset="UTF-8"/><title>Closed</title><link rel="stylesheet" href="/assets/css/voxu.css"/></head>
<body class="theme-dark" style="display:flex;align-items:center;justify-content:center;min-height:100vh"><div style="text-align:center"><h2 style="color:var(--text)">Registration is currently closed.</h2><a href="/auth/login.php" class="btn btn-primary" style="margin-top:16px;border-radius:999px">Sign In</a></div></body></html>
<?php exit; }
if ($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    if(!Security::rateLimit('register_'.($_SERVER['REMOTE_ADDR']??''),3,60)){$error='Too many attempts. Wait 1 minute.';}
    else{
        $username=strtolower(preg_replace('/[^a-zA-Z0-9_]/','',sanitize($_POST['username']??'')));
        $email=strtolower(trim($_POST['email']??''));
        $pass=$_POST['password']??'';
        $pass2=$_POST['password2']??'';
        if(!$username||!$email||!$pass){$error='All fields required.';}
        elseif(strlen($username)<3||strlen($username)>30){$error='Username: 3–30 characters.';}
        elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){$error='Invalid email address.';}
        elseif(strlen($pass)<8){$error='Password must be at least 8 characters.';}
        elseif($pass!==$pass2){$error='Passwords do not match.';}
        else{
            try{$existU=DB::count('users','username=?',[$username]);$existE=DB::count('users','email=?',[$email]);}
            catch(Throwable){$existU=$existE=0;}
            if($existU>0){$error='Username already taken.';}
            elseif($existE>0){$error='Email already registered.';}
            else{
                try{
                    $uid=DB::insert('users',['username'=>$username,'email'=>$email,'password'=>hashPassword($pass),'status'=>'active','is_verified'=>0,'created_at'=>date('Y-m-d H:i:s')]);
                    DB::exec("INSERT IGNORE INTO wallets (user_id,balance,points_balance) VALUES (?,0,0)",[$uid]);
                    addPoints($uid, (int)getSetting('points_for_signup', DEFAULT_POINTS_FOR_SIGNUP), 'signup', 'Welcome bonus points');
                    $plan=DB::first("SELECT id FROM subscription_plans WHERE slug='free' LIMIT 1");
                    DB::exec("INSERT IGNORE INTO user_subscriptions (user_id,plan_id,billing,status,starts_at) VALUES (?,?,'free','active',NOW())",[$uid,(int)($plan['id']??1)]);
                    DB::insert('user_profiles',['user_id'=>$uid]);
                    sendVerificationEmail($uid,$email,$username);
                    $success='✅ Account created! Check your email to verify your account.';
                }catch(Throwable $e){error_log('Register error: '.$e->getMessage());$error='Registration failed. Please try again.';}
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>" <?= isRtl()?'dir="rtl"':'' ?>>
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<meta name="csrf-token" content="<?= csrfToken() ?>"/>
<title>Sign Up — <?= $appName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="/assets/css/voxu.css"/>
</head>
<body class="theme-<?= clean($theme) ?>">
<div class="auth-page">
  <div class="auth-left">
    <div style="position:relative;z-index:1;text-align:center;max-width:360px">
      <div style="font-family:'Poppins',sans-serif;font-size:52px;font-weight:800;margin-bottom:16px"><?= $appName ?><span style="color:rgba(255,255,255,.5)">.</span></div>
      <div style="font-size:22px;font-weight:700;margin-bottom:14px">Join the Voice Revolution</div>
      <p style="font-size:15px;opacity:.85;line-height:1.7">Post your voice. Build your audience. Earn real money — starting today, for free.</p>
      <div style="background:rgba(255,255,255,.12);border-radius:16px;padding:20px;margin-top:28px;text-align:left">
        <?php foreach(['Free to join, forever','Earn from day one','15 languages supported','Android app coming soon'] as $f): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:14px;font-weight:500">
          <span style="background:rgba(255,255,255,.2);border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0">✓</span>
          <?= $f ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="auth-right" style="overflow-y:auto">
    <div class="auth-form">
      <a href="/" style="display:block;margin-bottom:24px;font-family:'Poppins',sans-serif;font-size:22px;font-weight:800;color:var(--text)"><?= $appName ?><span style="color:var(--purple)">.</span></a>
      <div class="auth-title">Create your account</div>
      <p class="auth-sub">Free forever. No credit card needed.</p>
      <?php if($error):?><div class="alert alert-danger"><?= clean($error) ?></div><?php endif;?>
      <?php if($success):?><div class="alert alert-success"><?= clean($success) ?></div><?php endif;?>
      <?php if(!$success):?>
      <div class="social-btns">
        <a href="/auth/social.php?provider=google" class="btn-social btn-social-google">
          <svg width="20" height="20" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
          Sign up with Google
        </a>
        <a href="/auth/social.php?provider=facebook" class="btn-social btn-social-facebook">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          Sign up with Facebook
        </a>
      </div>
      <div class="auth-divider">or create account with email</div>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
        <div class="form-row">
          <div class="input-group" style="margin-bottom:0">
            <label class="input-label">Username</label>
            <input class="input" type="text" name="username" placeholder="yourname" required minlength="3" maxlength="30" pattern="[a-zA-Z0-9_]+" title="Letters, numbers, underscore only"/>
          </div>
          <div class="input-group" style="margin-bottom:0">
            <label class="input-label">Email</label>
            <input class="input" type="email" name="email" placeholder="you@email.com" required/>
          </div>
        </div>
        <div style="height:14px"></div>
        <div class="form-row">
          <div class="input-group" style="margin-bottom:0">
            <label class="input-label">Password</label>
            <input class="input" type="password" name="password" placeholder="Min 8 chars" required minlength="8"/>
          </div>
          <div class="input-group" style="margin-bottom:0">
            <label class="input-label">Confirm Password</label>
            <input class="input" type="password" name="password2" placeholder="Repeat password" required/>
          </div>
        </div>
        <div style="height:14px"></div>
        <div style="font-size:12px;color:var(--text3);margin-bottom:14px">
          By registering you agree to our <a href="/terms.php" style="color:var(--purple)">Terms</a> and <a href="/privacy.php" style="color:var(--purple)">Privacy Policy</a>.
        </div>
        <button type="submit" class="btn btn-primary w-full" style="border-radius:999px;padding:13px;font-size:15px">Create Free Account 🎙</button>
      </form>
      <?php endif;?>
      <p style="text-align:center;margin-top:20px;font-size:14px;color:var(--text3)">Already have an account? <a href="/auth/login.php" style="color:var(--purple);font-weight:700">Sign in</a></p>
    </div>
  </div>
</div>
</body></html>
