<?php
// @author  Jcode | ObrempongK
// auth/logout.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (auth()) {
    DB::exec('UPDATE users SET last_login=NOW() WHERE id=?', [(int)$_SESSION['user_id']]);
}
logoutUser();
redirect('/');
