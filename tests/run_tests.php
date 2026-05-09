<?php

// Mock necessary constants and globals for functions.php
define('APP_NAME', 'Voxu');
define('APP_URL', 'http://localhost');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads');
define('MAX_VOICE_MB', 20);
define('MAX_STATUS_MB', 50);
define('SESSION_NAME', 'voxu_sess');
define('SESSION_LIFE', 86400 * 30);

// Mock DB class if it's required by functions.php
// But we'll try to only test pure functions for now.
// If functions.php requires db.php, we might need to mock it.
// Looking at includes/functions.php, it requires config.php and db.php.

require_once __DIR__ . '/SimpleTestRunner.php';

$success = true;

$runnerFunctions = new SimpleTestRunner(__DIR__ . '/FunctionsTest.php');
$success = $runnerFunctions->run() && $success;

$runnerSecurity = new SimpleTestRunner(__DIR__ . '/SecurityTest.php');
$success = $runnerSecurity->run() && $success;

exit($success ? 0 : 1);
