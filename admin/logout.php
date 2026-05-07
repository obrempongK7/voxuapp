<?php
/**
 * @author  Jcode | ObrempongK
 * Voxu — Admin Logout
 * FIX: Removed early session_start() that used wrong session name.
 *      config.php handles session bootstrap with correct 'voxu_sess' name.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['admin_id'])) {
    logAdminAction((int)$_SESSION['admin_id'], 'admin_logout', 'Admin logged out');
}

// Properly destroy session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: /admin/login.php');
exit;
