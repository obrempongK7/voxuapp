<?php
/**
 * Voxu — Application Configuration
 * @author  Jcode | ObrempongK
 *
 * INSTRUCTIONS:
 *  1. Fill in your database credentials below
 *  2. Set APP_URL to your actual domain (no trailing slash)
 *  3. Generate a new APP_KEY: php -r "echo bin2hex(random_bytes(32));"
 *  4. Upload to your server root
 *  5. Run install.php to create all database tables
 */

/* ── APPLICATION ─────────────────────────────────────────── */
define('APP_NAME',    'Voxu');
define('APP_VERSION', '2.1.0');
define('APP_URL',     'http://yourdomain.com');   // ← CHANGE: no trailing slash
define('APP_DIR',     __DIR__);

/* ── DATABASE ────────────────────────────────────────────── */
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'your_database_name');        // ← CHANGE
define('DB_USER',    'your_database_user');        // ← CHANGE
define('DB_PASS',    'your_database_password');    // ← CHANGE
define('DB_CHARSET', 'utf8mb4');

/* ── SECURITY ────────────────────────────────────────────── */
// Generate a unique key: php -r "echo bin2hex(random_bytes(32));"
define('APP_KEY',    'CHANGE_ME_generate_a_random_64_char_hex_string');

/* ── UPLOAD LIMITS ───────────────────────────────────────── */
define('UPLOAD_DIR',    __DIR__ . '/assets/uploads');
define('MAX_VOICE_MB',  20);
define('MAX_STATUS_MB', 50);

/* ── SESSION ─────────────────────────────────────────────── */
define('SESSION_NAME', 'voxu_sess');
define('SESSION_LIFE', 86400 * 30);   // 30 days

/* ── EARNING DEFAULTS (overridden by platform_settings DB) ─ */
define('DEFAULT_POINTS_PER_POST',        5);
define('DEFAULT_POINTS_PER_REPLY',       2);
define('DEFAULT_POINTS_PER_STATUS_VIEW', 1);
define('DEFAULT_POINTS_PER_CLICK',       3);
define('DEFAULT_POINTS_FOR_SIGNUP',      20);
define('DEFAULT_POINTS_TO_CASH',         100);
define('DEFAULT_MIN_WITHDRAWAL',         500);
define('DEFAULT_MAX_DAILY_EARN',         1000);

/* ── PHP SETTINGS ────────────────────────────────────────── */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
date_default_timezone_set('UTC');

/* ── SESSION BOOTSTRAP ───────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFE,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
