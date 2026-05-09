<?php

// Mock necessary constants and globals for functions.php
define('APP_NAME', 'Voxu');
define('APP_URL', 'http://localhost');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads');
define('MAX_VOICE_MB', 20);
define('MAX_STATUS_MB', 50);
define('SESSION_NAME', 'voxu_sess');
define('SESSION_LIFE', 86400 * 30);

// We require them at the top level so global variables stay global
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/SimpleTestRunner.php';

$allSuccess = true;

$runner1 = new SimpleTestRunner(__DIR__ . '/FunctionsTest.php');
$success1 = $runner1->run();
$allSuccess = $allSuccess && $success1;

echo "\n";

$runner2 = new SimpleTestRunner(__DIR__ . '/I18nTest.php');
$success2 = $runner2->run();
$allSuccess = $allSuccess && $success2;

exit($allSuccess ? 0 : 1);
