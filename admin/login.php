<?php
/**
 * @author  Jcode | ObrempongK
 * Voxu — Admin Login
 * FIX: Removed manual session_start() that fired before config.php set
 *      session_name('voxu_sess'). This caused admin_id to be stored in
 *      PHPSESSID while all other admin pages read from voxu_sess → loop.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';

if (isset($_SESSION['admin_id'])) redirect('/admin/');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (!Security::rateLimit('admin_login_' . ($_SERVER['REMOTE_ADDR']??''), 5, 60)) {
        $error = 'Too many login attempts. Please wait 1 minute.';
    } else {
    $email = sanitize($_POST['email']    ?? '');
    $pass  = sanitize($_POST['password'] ?? '');

    if (!$email || !$pass) {
        $error = 'Email and password are required.';
    } else {
        $admin = DB::first(
            'SELECT * FROM admins WHERE email = ? AND status = "active" LIMIT 1',
            [$email]
        );
        if (!$admin || !verifyPassword($pass, $admin['password'])) {
            $error = 'Invalid email or password.';
            DB::insert('admin_activity_logs', [
                'admin_id'    => 0,
                'action'      => 'failed_login',
                'description' => "Failed admin login for: {$email}",
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } else {
            $_SESSION['admin_id']   = (int)$admin['id'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_name'] = $admin['name'];
            DB::update('admins', ['last_login' => date('Y-m-d H:i:s')], ['id' => (int)$admin['id']]);
            logAdminAction((int)$admin['id'], 'admin_login', 'Admin logged in');
            redirect('/admin/');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Admin Login — Voxu</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/admin.css"/>
  <style>
    body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg);padding:20px}
    .login-card{width:100%;max-width:400px;background:var(--card);border:1px solid var(--border);border-radius:20px;padding:40px 36px}
    .logo{font-family:'Poppins',sans-serif;font-size:30px;font-weight:800;text-align:center;color:#fff}
    .logo span{color:var(--purple)}
    .logo-sub{text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:var(--text3);margin-bottom:32px;margin-top:4px}
    .fg{margin-bottom:18px}
    .fl{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:7px}
    .fi{width:100%;background:var(--bg2);border:1px solid var(--border);color:#fff;padding:12px 14px;border-radius:8px;font-size:14px;outline:none;font-family:inherit;transition:.2s;box-sizing:border-box}
    .fi:focus{border-color:var(--purple);box-shadow:0 0 0 3px var(--purple-l)}
    .fb{width:100%;padding:14px;background:var(--purple);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;transition:.2s;margin-top:6px}
    .fb:hover{background:var(--purple-d)}
    .fb:disabled{opacity:.6;cursor:not-allowed}
    .al{padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:18px;background:var(--danger-l);border:1px solid var(--danger);color:var(--danger);display:flex;align-items:center;gap:8px}
    .div{height:1px;background:var(--border);margin:22px 0}
    .bk{text-align:center;font-size:13px;color:var(--text3)}
    .bk a{color:var(--text2);text-decoration:none}
    .bk a:hover{color:#fff}
  </style>
</head>
<body>
<div class="login-card">
  <div class="logo">Vo<span>xu</span></div>
  <div class="logo-sub">Admin Control Panel</div>

  <?php if ($error): ?>
    <div class="al">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= clean($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="/admin/login.php" id="af">
    <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>
    <div class="fg">
      <label class="fl" for="em">Admin Email</label>
      <input class="fi" type="email" id="em" name="email" value="<?= clean($_POST['email'] ?? '') ?>" placeholder="admin@yourdomain.com" required autocomplete="username"/>
    </div>
    <div class="fg">
      <label class="fl" for="pw">Password</label>
      <input class="fi" type="password" id="pw" name="password" placeholder="••••••••••" required autocomplete="current-password"/>
    </div>
    <button type="submit" class="fb" id="lb">Log In to Admin Panel</button>
  </form>

  <div class="div"></div>
  <div class="bk"><a href="/">← Back to Voxu</a></div>
</div>
<script>
document.getElementById('af').addEventListener('submit',function(){
  var b=document.getElementById('lb');
  b.textContent='Logging in…';
  b.disabled=true;
});
</script>
</body>
</html>
