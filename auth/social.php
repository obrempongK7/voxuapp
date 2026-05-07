<?php
/**
 * Voxu — Social OAuth Handler
 * Handles Google, Facebook, and X (Twitter) OAuth flows.
 * Configure credentials in config.php or platform settings.
 * @author  Jcode | ObrempongK
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../core/Security.php';

if (auth()) redirect('/dashboard/feed.php');

$provider = sanitize($_GET['provider'] ?? '');
$code     = $_GET['code']             ?? '';
$state    = $_GET['state']            ?? '';
$error    = '';

// OAuth credentials — set these in admin settings or define in config.php
$s = getPlatformSettings();
$OAUTH = [
    'google' => [
        'client_id'     => defined('GOOGLE_CLIENT_ID')     ? GOOGLE_CLIENT_ID     : ($s['oauth_google_id']     ?? ''),
        'client_secret' => defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : ($s['oauth_google_secret'] ?? ''),
        'redirect_uri'  => APP_URL . '/auth/social.php?provider=google',
        'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url'     => 'https://oauth2.googleapis.com/token',
        'userinfo_url'  => 'https://www.googleapis.com/oauth2/v3/userinfo',
        'scope'         => 'openid email profile',
    ],
    'facebook' => [
        'client_id'     => defined('FACEBOOK_APP_ID')      ? FACEBOOK_APP_ID      : ($s['oauth_facebook_id']     ?? ''),
        'client_secret' => defined('FACEBOOK_APP_SECRET')  ? FACEBOOK_APP_SECRET  : ($s['oauth_facebook_secret'] ?? ''),
        'redirect_uri'  => APP_URL . '/auth/social.php?provider=facebook',
        'auth_url'      => 'https://www.facebook.com/v19.0/dialog/oauth',
        'token_url'     => 'https://graph.facebook.com/v19.0/oauth/access_token',
        'userinfo_url'  => 'https://graph.facebook.com/me?fields=id,name,email,picture',
        'scope'         => 'email,public_profile',
    ],
    'twitter' => [
        'client_id'     => defined('TWITTER_CLIENT_ID')     ? TWITTER_CLIENT_ID     : ($s['oauth_twitter_id']     ?? ''),
        'client_secret' => defined('TWITTER_CLIENT_SECRET') ? TWITTER_CLIENT_SECRET : ($s['oauth_twitter_secret'] ?? ''),
        'redirect_uri'  => APP_URL . '/auth/social.php?provider=twitter',
        'auth_url'      => 'https://twitter.com/i/oauth2/authorize',
        'token_url'     => 'https://api.twitter.com/2/oauth2/token',
        'userinfo_url'  => 'https://api.twitter.com/2/users/me?user.fields=name,username,profile_image_url',
        'scope'         => 'tweet.read users.read',
    ],
];

if (!array_key_exists($provider, $OAUTH)) {
    redirect('/auth/login.php');
}

$cfg = $OAUTH[$provider];

// ── STEP 1: No code yet — redirect to provider ──────────────────────────────
if (!$code) {
    if (empty($cfg['client_id'])) {
        // OAuth not configured — show helpful error
        $appName = getPlatformSettings()['app_name'] ?? 'Voxu';
        ?><!DOCTYPE html>
        <html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
        <title>OAuth Not Configured</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
        <link rel="stylesheet" href="/assets/css/voxu.css"/>
        </head><body class="theme-dark" style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px">
        <div style="max-width:440px;width:100%;text-align:center">
          <div style="font-size:48px;margin-bottom:16px">🔧</div>
          <h2 style="color:var(--text);margin-bottom:8px"><?= ucfirst($provider) ?> Login Not Configured</h2>
          <p style="color:var(--text2);margin-bottom:20px;font-size:14px">
            The admin needs to add <?= ucfirst($provider) ?> OAuth credentials in<br/>
            <strong>Admin → Settings → Social Login</strong>
          </p>
          <a href="/auth/login.php" class="btn btn-secondary" style="border-radius:999px">← Back to Login</a>
        </div>
        </body></html>
        <?php
        exit;
    }
    $stateVal = generateToken(16);
    $_SESSION['oauth_state']    = $stateVal;
    $_SESSION['oauth_provider'] = $provider;

    $params = [
        'client_id'     => $cfg['client_id'],
        'redirect_uri'  => $cfg['redirect_uri'],
        'response_type' => 'code',
        'scope'         => $cfg['scope'],
        'state'         => $stateVal,
    ];
    if ($provider === 'google')  $params['access_type'] = 'online';
    if ($provider === 'twitter') $params['code_challenge_method'] = 'plain';

    header('Location: ' . $cfg['auth_url'] . '?' . http_build_query($params));
    exit;
}

// ── STEP 2: Code received — exchange for token ──────────────────────────────
if ($_SESSION['oauth_state'] !== $state || $_SESSION['oauth_provider'] !== $provider) {
    redirect('/auth/login.php?error=state_mismatch');
}

// Exchange code for access token
$tokenData = http_post($cfg['token_url'], [
    'code'          => $code,
    'client_id'     => $cfg['client_id'],
    'client_secret' => $cfg['client_secret'],
    'redirect_uri'  => $cfg['redirect_uri'],
    'grant_type'    => 'authorization_code',
]);

if (!isset($tokenData['access_token'])) {
    error_log('OAuth token exchange failed: ' . json_encode($tokenData));
    redirect('/auth/login.php?error=token_failed');
}

$accessToken = $tokenData['access_token'];

// Fetch user info from provider
$userInfo = http_get($cfg['userinfo_url'], $accessToken);
if (!$userInfo) redirect('/auth/login.php?error=userinfo_failed');

// Normalise provider-specific user data
$providerId = $email = $name = $avatarUrl = null;
if ($provider === 'google') {
    $providerId = $userInfo['sub']     ?? '';
    $email      = $userInfo['email']   ?? '';
    $name       = $userInfo['name']    ?? '';
    $avatarUrl  = $userInfo['picture'] ?? null;
} elseif ($provider === 'facebook') {
    $providerId = $userInfo['id']    ?? '';
    $email      = $userInfo['email'] ?? '';
    $name       = $userInfo['name']  ?? '';
    $avatarUrl  = $userInfo['picture']['data']['url'] ?? null;
} elseif ($provider === 'twitter') {
    $data       = $userInfo['data']         ?? $userInfo;
    $providerId = $data['id']               ?? '';
    $name       = $data['name']             ?? $data['username'] ?? '';
    $email      = $data['username'] . '@x.invalid';   // Twitter v2 doesn't expose email
    $avatarUrl  = $data['profile_image_url'] ?? null;
}

if (!$providerId) redirect('/auth/login.php?error=no_provider_id');

// Log in or register via socialLogin()
$userId = socialLogin($provider, $providerId, $email ?: ($providerId . '@' . $provider . '.social'), $name, $avatarUrl);
if (!$userId) redirect('/auth/login.php?error=social_failed');

loginUser($userId);
unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);
redirect('/dashboard/feed.php');

/* ── HELPERS ────────────────────────────────────────────────────────────── */
function http_post(string $url, array $params): array {
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($params),
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body ? (json_decode($body, true) ?? []) : [];
}

function http_get(string $url, string $token): array {
    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'header'  => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body ? (json_decode($body, true) ?? []) : [];
}
