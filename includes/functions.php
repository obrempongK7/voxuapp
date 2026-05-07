<?php
/**
 * @author  Jcode | ObrempongK
 */
/* ============================================================
   VOXU — Core Helper Functions
   ============================================================ */
if (!defined('APP_NAME')) require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

/* ── AUTH ─────────────────────────────────────────────── */
function auth(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user !== null) return $user;
    try {
        $user = DB::first(
            'SELECT u.*, up.avatar, up.bio, up.phone, up.country,
                    w.balance, w.points_balance
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN wallets w ON w.user_id = u.id
             WHERE u.id = ? AND u.status = "active"',
            [$_SESSION['user_id']]
        );
    } catch (Throwable) {
        $user = null;
    }
    return $user;
}

function loginUser(int $id): void {
    $_SESSION['user_id']    = $id;
    $_SESSION['login_time'] = time();
    DB::exec('UPDATE users SET last_login=NOW(), last_ip=? WHERE id=?',
        [$_SERVER['REMOTE_ADDR'] ?? '', $id]);
}

function logoutUser(): void {
    $_SESSION = [];
    session_destroy();
}

function requireAuth(): void {
    if (!auth()) redirect('/auth/login.php');
}

function requireAdmin(): void {
    if (!isset($_SESSION['admin_id'])) redirect('/admin/login.php');
}

/* ── REDIRECT ─────────────────────────────────────────── */
function redirect(string $path): void {
    // Use current request host so redirects work even if APP_URL is misconfigured
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? parse_url(APP_URL, PHP_URL_HOST) ?? 'localhost';
    header('Location: ' . $scheme . '://' . $host . $path, true, 302);
    exit;
}

function redirectBack(string $fallback = '/'): void {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? parse_url(APP_URL, PHP_URL_HOST) ?? 'localhost';
    $ref    = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref && (str_starts_with($ref, APP_URL) || str_contains($ref, (string)$host))) {
        header('Location: ' . $ref, true, 302);
    } else {
        header('Location: ' . $scheme . '://' . $host . $fallback, true, 302);
    }
    exit;
}

function getTheme(): string {
    $theme = $_COOKIE['voxu_theme'] ?? 'dark';
    return $theme === 'light' ? 'light' : 'dark';
}

function themeClass(): string {
    return 'theme-' . getTheme();
}

/* ── SANITIZE ─────────────────────────────────────────── */
function clean(mixed $v): string {
    return htmlspecialchars(trim((string)$v), ENT_QUOTES, 'UTF-8');
}

function sanitize(mixed $v): string {
    return trim(strip_tags((string)$v));
}

/* ── CSRF ─────────────────────────────────────────────── */
function csrfToken(): string {
    // Unified key - same as Security::csrfToken()
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['_csrf']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!$expected || !hash_equals($expected, $token)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        } else {
            echo '<p style="font-family:sans-serif;padding:40px;color:#FF4444">Security check failed. <a href="javascript:history.back()">Go back</a>.</p>';
        }
        exit;
    }
}

function requireCsrfForStateChange(?string $method = null): void {
    $method = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        verifyCsrf();
    }
}

/* ── PASSWORD ─────────────────────────────────────────── */
function hashPassword(string $p): string {
    return password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}

/* ── TOKEN ────────────────────────────────────────────── */
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/* ── FILE UPLOAD ──────────────────────────────────────── */
function uploadFile(array $file, string $type): array {
    // Extension → safe MIME map (used when finfo lies / returns octet-stream)
    static $EXT_MIME = [
        'webm' => 'audio/webm',  'mp3'  => 'audio/mpeg', 'ogg'  => 'audio/ogg',
        'wav'  => 'audio/wav',   'm4a'  => 'audio/mp4',  'mp4'  => 'video/mp4',
        'jpg'  => 'image/jpeg',  'jpeg' => 'image/jpeg', 'png'  => 'image/png',
        'gif'  => 'image/gif',   'webp' => 'image/webp', 'svg'  => 'image/svg+xml',
    ];
    // What extensions are allowed per upload type
    static $ALLOWED_EXT = [
        'voice'  => ['webm','mp3','ogg','wav','mp4','m4a'],
        'status' => ['jpg','jpeg','png','gif','webp','mp4','webm'],
        'avatar' => ['jpg','jpeg','png','webp'],
        'image'  => ['jpg','jpeg','png','gif','webp'],
        'logo'   => ['jpg','jpeg','png','gif','webp','svg'],
        'ad'     => ['jpg','jpeg','png','gif','webp'],
    ];
    // Max upload size per type
    static $MAX_MB = [
        'voice' => MAX_VOICE_MB, 'status' => MAX_STATUS_MB,
        'avatar' => 5, 'image' => 10, 'logo' => 5, 'ad' => 10,
    ];
    // Upload error → message
    static $UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE   => 'File too large (server upload_max_filesize limit).',
        UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit).',
        UPLOAD_ERR_PARTIAL    => 'Upload was interrupted — please try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was received.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by a server extension.',
    ];

    // --- 1. Check for upload errors first ---
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = $UPLOAD_ERRORS[$file['error']] ?? ('Upload error code: ' . $file['error']);
        return ['ok' => false, 'error' => $msg];
    }

    // --- 2. Get extension from original filename ---
    $clientName = $file['name'] ?? '';
    $ext = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

    // --- 3. Get MIME type via finfo ---
    try {
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $rawMime  = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
    } catch (Throwable) {
        $rawMime = 'application/octet-stream';
    }
    // Strip codec parameters: 'audio/webm;codecs=opus' → 'audio/webm'
    $mime = strtolower(trim(explode(';', $rawMime)[0]));

    // --- 4. Resolve extension: if missing or unrecognised, derive from detected MIME ---
    $mimeToExt = array_flip($EXT_MIME);
    if (!$ext || !isset($EXT_MIME[$ext])) {
        $ext = $mimeToExt[$mime] ?? null;
    }
    // Last resort for browser audio blobs reported as octet-stream:
    // trust the extension if it is in our safe voice list
    if (!$ext && $type === 'voice' && $mime === 'application/octet-stream') {
        $ext = 'webm'; // safest fallback for browser-recorded audio
    }

    // --- 5. Validate extension is in allowed list for this upload type ---
    $allowedExts = $ALLOWED_EXT[$type] ?? [];
    if (!$ext || !in_array($ext, $allowedExts, true)) {
        return ['ok' => false, 'error' => 'File type not allowed. Allowed: ' . implode(', ', $allowedExts)];
    }

    // --- 6. For audio/voice: also accept octet-stream (browser blobs) if ext is safe ---
    $safeMimeForType = [
        'voice'  => ['audio/', 'application/octet-stream', 'video/'],   // video/ covers .mp4 audio
        'status' => ['image/', 'video/', 'application/octet-stream'],
        'avatar' => ['image/'],
        'image'  => ['image/'],
        'logo'   => ['image/', 'application/octet-stream'],
        'ad'     => ['image/'],
    ];
    $mimeAllowed = false;
    foreach ($safeMimeForType[$type] ?? [] as $prefix) {
        if (str_starts_with($mime, $prefix)) { $mimeAllowed = true; break; }
    }
    if (!$mimeAllowed) {
        return ['ok' => false, 'error' => 'Detected file type (' . $mime . ') is not permitted.'];
    }

    // --- 7. Size check ---
    $maxBytes = ($MAX_MB[$type] ?? 10) * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'File is too large. Maximum: ' . ($MAX_MB[$type] ?? 10) . ' MB.'];
    }

    // --- 8. Save file ---
    $dir = UPLOAD_DIR . '/' . $type;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Upload directory could not be created.'];
    }
    $filename = generateToken(20) . '.' . $ext;
    $dest     = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save file to server. Check directory permissions.'];
    }
    return ['ok' => true, 'url' => '/assets/uploads/' . $type . '/' . $filename, 'ext' => $ext, 'mime' => $mime];
}

/* ── WALLET ───────────────────────────────────────────── */
function getUserWallet(int $userId): ?array {
    return DB::first('SELECT * FROM wallets WHERE user_id=?', [$userId]);
}

function addPoints(int $userId, int $points, string $source, string $description): bool {
    $settings = getPlatformSettings();
    $maxDaily = (int)($settings['max_daily_earnings'] ?? DEFAULT_MAX_DAILY_EARN);

    // Check daily earning cap
    $todayPoints = DB::first(
        'SELECT COALESCE(SUM(points),0) AS total FROM points_transactions
         WHERE user_id=? AND type="credit" AND DATE(created_at)=CURDATE()',
        [$userId]
    );
    if ((int)$todayPoints['total'] + $points > $maxDaily) {
        $points = max(0, $maxDaily - (int)$todayPoints['total']);
    }
    if ($points <= 0) return false;

    $startedTransaction = !DB::conn()->inTransaction();
    if ($startedTransaction) {
        DB::beginTransaction();
    }
    try {
        DB::exec('UPDATE wallets SET points_balance = points_balance + ? WHERE user_id=?', [$points, $userId]);
        DB::insert('points_transactions', [
            'user_id'     => $userId,
            'points'      => $points,
            'type'        => 'credit',
            'source'      => $source,
            'description' => $description,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        if ($startedTransaction) {
            DB::commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($startedTransaction && DB::conn()->inTransaction()) {
            DB::rollback();
        }
        return false;
    }
}

function deductPoints(int $userId, int $points, string $reason): bool {
    $wallet = getUserWallet($userId);
    if (!$wallet || $wallet['points_balance'] < $points) return false;
    $startedTransaction = !DB::conn()->inTransaction();
    if ($startedTransaction) {
        DB::beginTransaction();
    }
    try {
        DB::exec('UPDATE wallets SET points_balance = points_balance - ? WHERE user_id=?', [$points, $userId]);
        DB::insert('points_transactions', [
            'user_id'     => $userId,
            'points'      => $points,
            'type'        => 'debit',
            'source'      => 'system',
            'description' => $reason,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        if ($startedTransaction) {
            DB::commit();
        }
        return true;
    } catch (Throwable) {
        if ($startedTransaction && DB::conn()->inTransaction()) {
            DB::rollback();
        }
        return false;
    }
}

function addBalance(int $userId, float $amount, string $type, string $ref, string $desc): bool {
    $startedTransaction = !DB::conn()->inTransaction();
    if ($startedTransaction) {
        DB::beginTransaction();
    }
    try {
        DB::exec('UPDATE wallets SET balance = balance + ? WHERE user_id=?', [$amount, $userId]);
        DB::insert('transactions', [
            'user_id'     => $userId,
            'type'        => $type,
            'amount'      => $amount,
            'reference'   => $ref,
            'description' => $desc,
            'status'      => 'completed',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        if ($startedTransaction) {
            DB::commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($startedTransaction && DB::conn()->inTransaction()) {
            DB::rollback();
        }
        return false;
    }
}

function deductBalance(int $userId, float $amount, string $type, string $ref, string $desc): bool {
    $wallet = getUserWallet($userId);
    if (!$wallet || $wallet['balance'] < $amount) return false;
    $startedTransaction = !DB::conn()->inTransaction();
    if ($startedTransaction) {
        DB::beginTransaction();
    }
    try {
        DB::exec('UPDATE wallets SET balance = balance - ? WHERE user_id=?', [$amount, $userId]);
        DB::insert('transactions', [
            'user_id'     => $userId,
            'type'        => $type,
            'amount'      => $amount,
            'reference'   => $ref,
            'description' => $desc,
            'status'      => 'completed',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        if ($startedTransaction) {
            DB::commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($startedTransaction && DB::conn()->inTransaction()) {
            DB::rollback();
        }
        return false;
    }
}

/* ── NOTIFICATIONS ────────────────────────────────────── */
function createNotification(int $userId, string $type, string $message, ?string $data = null): void {
    DB::insert('notifications', [
        'user_id'    => $userId,
        'type'       => $type,
        'message'    => $message,
        'data'       => $data,
        'is_read'    => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

/* ── PLATFORM SETTINGS ────────────────────────────────── */
function getPlatformSettings(): array {
    static $settings = null;
    if ($settings !== null && empty($GLOBALS['__voxu_settings_cache_cleared'])) {
        return $settings;
    }
    unset($GLOBALS['__voxu_settings_cache_cleared']);
    try {
        $rows = DB::query('SELECT setting_key, setting_value FROM platform_settings');
        $settings = [];
        foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
    } catch (Throwable) {
        // Table may not exist yet (before install.php is run)
        $settings = [];
    }
    return $settings;
}

function getSetting(string $key, mixed $default = null): mixed {
    $s = getPlatformSettings();
    return $s[$key] ?? $default;
}

function setSetting(string $key, string $value): void {
    try {
        DB::exec(
            'INSERT INTO platform_settings (setting_key, setting_value, updated_at)
             VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?, updated_at=NOW()',
            [$key, $value, $value]
        );
    } catch (Throwable) {}
    clearSettingsCache();
}

function clearSettingsCache(): void {
    static $ref = null;
    // Force reset by calling with a special flag via a static variable trick
    $GLOBALS['__voxu_settings_cache_cleared'] = true;
}

/* ── TIME HELPERS ─────────────────────────────────────── */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff/60) . 'm ago';
    if ($diff < 86400)   return floor($diff/3600) . 'h ago';
    if ($diff < 604800)  return floor($diff/86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

function formatCurrency(float $amount, string $currency = ''): string {
    $currency = $currency ?: getSetting('currency', 'USD');
    return $currency . ' ' . number_format($amount, 2);
}

function formatPoints(int $points): string {
    return number_format($points) . ' pts';
}

function sanitizeUrl(mixed $url, array $allowedSchemes = ['http', 'https']): string {
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, $allowedSchemes, true)) {
        return '';
    }
    return $url;
}

/* ── FRAUD CHECK ──────────────────────────────────────── */
function checkFraudFlags(int $userId): bool {
    return DB::count('fraud_flags', 'user_id=? AND status="active"', [$userId]) > 0;
}

/* ── ADMIN AUDIT LOG ──────────────────────────────────── */
function logAdminAction(int $adminId, string $action, string $description, ?array $data = null): void {
    // NOTE: 'data' column not in base schema - appended to description if provided
    $desc = $description;
    if ($data) $desc .= ' | ' . json_encode($data);
    try {
        DB::insert('admin_activity_logs', [
            'admin_id'    => $adminId,
            'action'      => $action,
            'description' => mb_substr($desc, 0, 255),
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        // Silently fail if table not yet created - never crash the page
        error_log('logAdminAction failed: ' . $e->getMessage());
    }
}

/* ── JSON RESPONSE ────────────────────────────────────── */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonSuccess(string $message = 'OK', array $data = []): void {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['success' => false, 'message' => $message], $code);
}

/* ── PAGINATION ───────────────────────────────────────── */
function paginate(string $table, int $page, int $perPage, string $where = '1', array $params = [], string $order = 'id DESC'): array {
    $total  = DB::count($table, $where, $params);
    $offset = ($page - 1) * $perPage;
    $rows   = DB::query("SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}", $params);
    return [
        'data'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => ceil($total / $perPage),
    ];
}

/* ── AVATAR INITIALS ──────────────────────────────────── */
function avatarInitials(string $name): string {
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) return strtoupper($parts[0][0] . $parts[1][0]);
    return strtoupper(substr($name, 0, 2));
}

/* ── STATUS REWARD ────────────────────────────────────── */
function rewardStatusView(int $ownerId, int $statusId): void {
    $points = (int)getSetting('reward_per_view', DEFAULT_POINTS_PER_STATUS_VIEW);
    if ($points > 0) {
        addPoints($ownerId, $points, 'status_view', 'Earned from status view');
        DB::insert('status_earnings', [
            'user_id'      => $ownerId,
            'status_id'    => $statusId,
            'points_earned'=> $points,
            'source'       => 'view',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }
}

function rewardStatusClick(int $ownerId, int $statusId): void {
    $points = (int)getSetting('reward_per_click', DEFAULT_POINTS_PER_CLICK);
    if ($points > 0) {
        addPoints($ownerId, $points, 'status_click', 'Earned from contact click');
        DB::insert('status_earnings', [
            'user_id'      => $ownerId,
            'status_id'    => $statusId,
            'points_earned'=> $points,
            'source'       => 'click',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }
}

/* ── SUBSCRIPTION HELPERS ─────────────────────────────── */

/**
 * Get a user's active subscription plan.
 * Returns plan row or the free plan if none found.
 */
function getUserPlan(int $userId): array {
    try {
        $sub = DB::first(
            "SELECT sp.* FROM user_subscriptions us
             JOIN subscription_plans sp ON sp.id = us.plan_id
             WHERE us.user_id = ?
               AND us.status  = 'active'
               AND (us.expires_at IS NULL OR us.expires_at > NOW())",
            [$userId]
        );
        if ($sub) return $sub;
        $free = DB::first("SELECT * FROM subscription_plans WHERE slug='free' LIMIT 1");
    } catch (Throwable) { $free = null; $sub = null; }
    return $free ?: [
        'id'                 => 1,
        'name'               => 'Free',
        'slug'               => 'free',
        'max_recording_secs' => 180,
        'max_daily_earnings' => 1000,
        'min_withdrawal_pts' => 500,
        'cashout_multiplier' => 1.00,
        'max_status_per_day' => 10,
        'can_analytics'      => 0,
        'can_custom_link'    => 0,
        'verified_badge'     => 0,
        'priority_support'   => 0,
        'color'              => '#A0A0B0',
        'icon'               => '🎙',
    ];
}

/**
 * Check if user is premium (any paid plan).
 */
function isPremium(int $userId): bool {
    return DB::count(
        'user_subscriptions',
        'user_id=? AND status="active" AND plan_id > 1 AND (expires_at IS NULL OR expires_at > NOW())',
        [$userId]
    ) > 0;
}

/**
 * Assign a plan to a user (or update existing).
 */
function assignPlan(int $userId, int $planId, string $billing, ?string $expiresAt = null): void {
    $exists = DB::count('user_subscriptions','user_id=?',[$userId]);
    if ($exists) {
        DB::update('user_subscriptions', [
            'plan_id'    => $planId,
            'billing'    => $billing,
            'status'     => 'active',
            'starts_at'  => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['user_id' => $userId]);
    } else {
        DB::insert('user_subscriptions', [
            'user_id'    => $userId,
            'plan_id'    => $planId,
            'billing'    => $billing,
            'status'     => 'active',
            'starts_at'  => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

/**
 * Get the recording limit in seconds for a user.
 */
function getUserRecordingLimit(int $userId): int {
    $plan = getUserPlan($userId);
    $secs = (int)($plan['max_recording_secs'] ?? 180);
    // 0 means unlimited (Platinum)
    return $secs === 0 ? 0 : $secs;
}

/**
 * Get user's effective max daily earnings (applies plan multiplier).
 */
function getUserDailyEarningLimit(int $userId): int {
    $plan = getUserPlan($userId);
    return (int)($plan['max_daily_earnings'] ?? 1000);
}

/**
 * Get plan badge HTML.
 */
function planBadge(array $plan): string {
    if (($plan['slug'] ?? 'free') === 'free') return '';
    $color = htmlspecialchars($plan['color'] ?? '#6C3BFF', ENT_QUOTES);
    $icon  = $plan['icon']  ?? '⭐';
    $name  = htmlspecialchars($plan['name'] ?? 'Premium', ENT_QUOTES);
    return '<span style="display:inline-flex;align-items:center;gap:3px;background:' . $color . '22;color:' . $color . ';border:1px solid ' . $color . '55;border-radius:12px;padding:2px 8px;font-size:11px;font-weight:600">' . $icon . ' ' . $name . '</span>';
}

/* ── ADVERTISEMENT HELPERS ─────────────────────────────── */

/**
 * Get all active ads for a given slot.
 */
function getAds(string $slot): array {
    try {
        return DB::query(
            "SELECT * FROM ad_slots WHERE slot=? AND is_active=1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY sort_order ASC, created_at DESC",
            [$slot]
        );
    } catch (Throwable) {
        return [];
    }
}

/**
 * Render ad HTML for a slot. Returns empty string if no ads.
 */
function renderAds(string $slot, string $wrapClass = 'ad-slot'): string {
    $ads = getAds($slot);
    if (empty($ads)) return '';
    $class = htmlspecialchars($wrapClass, ENT_QUOTES, 'UTF-8');
    $slotAttr = htmlspecialchars($slot, ENT_QUOTES, 'UTF-8');
    $html = '<div class="' . $class . '" data-slot="' . $slotAttr . '">';
    foreach ($ads as $ad) {
        $target = !empty($ad['open_new_tab']) ? ' target="_blank" rel="noopener"' : '';
        $link   = htmlspecialchars($ad['link_url'] ?? '#', ENT_QUOTES);
        $alt    = htmlspecialchars($ad['title'] ?? 'Advertisement', ENT_QUOTES);
        if (!empty($ad['image_url'])) {
            $imageUrl = htmlspecialchars($ad['image_url'], ENT_QUOTES);
            $html .= '<a href="' . $link . '"' . $target . ' class="ad-item ad-image-item">';
            $html .= '<img src="' . $imageUrl . '" alt="' . $alt . '" loading="lazy"/>';
            $html .= "</a>";
        } elseif (!empty($ad['custom_html'])) {
            // Admin-supplied HTML (super_admin only can set this)
            $html .= '<div class="ad-item ad-html-item">' . $ad['custom_html'] . '</div>';
        }
    }
    $html .= "</div>";
    return $html;
}

/**
 * Track an ad click.
 */
function trackAdClick(int $adId): void {
    try {
        DB::exec('UPDATE ad_slots SET click_count=click_count+1 WHERE id=?', [$adId]);
    } catch (Throwable) {}
}

/**
 * Track an ad impression.
 */
function trackAdImpression(int $adId): void {
    try {
        DB::exec('UPDATE ad_slots SET impression_count=impression_count+1 WHERE id=?', [$adId]);
    } catch (Throwable) {}
}


/* ── HASHTAG / TOPIC HELPERS ───────────────────────── */

/**
 * Extract hashtags from text, return as lowercase array.
 */
function extractHashtags(string $text): array {
    preg_match_all('/#([a-zA-Z0-9_]{1,50})/', $text, $m);
    return array_values(array_unique(array_map('strtolower', $m[1] ?? [])));
}

/**
 * Convert #hashtag text to clickable spans.
 */
function linkifyHashtags(string $text): string {
    return preg_replace_callback('/#([a-zA-Z0-9_]{1,50})/', function($m) {
        $tag = htmlspecialchars(strtolower($m[1]), ENT_QUOTES);
        return "<span class=\"hashtag\" onclick=\"filterByHashtag('{$tag}')\" data-hashtag=\"{$tag}\">#" . htmlspecialchars($m[1], ENT_QUOTES) . "</span>";
    }, htmlspecialchars($text, ENT_QUOTES));
}

/* ── PODCAST LIMITS ────────────────────────────────── */

/**
 * Get max podcast duration (seconds) for a user based on plan.
 */
function getPodcastLimit(int $userId): int {
    $plan = getUserPlan($userId);
    // Free: 10 min, Silver: 30 min, Gold: 60 min, Platinum: unlimited
    $limits = ['free'=>600, 'silver'=>1800, 'gold'=>3600, 'platinum'=>0];
    return $limits[$plan['slug'] ?? 'free'] ?? 600;
}

function formatDuration(int $seconds): string {
    if ($seconds === 0) return 'Unlimited';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    if ($h > 0) return "{$h}h {$m}m";
    if ($m > 0) return "{$m}m " . ($s ? "{$s}s" : '');
    return "{$s}s";
}

/* ── MESSAGING RESTRICTIONS ────────────────────────── */

/**
 * Check if $senderId can send a message to $recipientId.
 * Rules:
 *  - Followers: unlimited
 *  - Non-followers: max 2 messages until recipient accepts
 * Returns ['allowed'=>bool, 'reason'=>string, 'is_follower'=>bool]
 */
function canSendMessage(int $senderId, int $recipientId): array {
    if ($senderId === $recipientId) {
        return ['allowed' => false, 'reason' => 'Cannot message yourself', 'is_follower' => false];
    }
    // Check mutual follow (sender follows recipient)
    $isFollower = DB::count('followers','follower_id=? AND following_id=?',[$senderId,$recipientId]) > 0;
    if ($isFollower) {
        return ['allowed' => true, 'reason' => '', 'is_follower' => true];
    }
    // Non-follower — check conversation acceptance
    $conv = DB::first(
        "SELECT * FROM message_conversations WHERE
         (user_a=? AND user_b=?) OR (user_a=? AND user_b=?)",
        [$senderId,$recipientId,$recipientId,$senderId]
    );
    if ($conv && $conv['status'] === 'accepted') {
        return ['allowed' => true, 'reason' => 'accepted', 'is_follower' => false];
    }
    if ($conv && $conv['status'] === 'blocked') {
        return ['allowed' => false, 'reason' => 'You have been blocked', 'is_follower' => false];
    }
    // Count messages already sent by this sender in this conversation
    $sentCount = $conv ? DB::count('messages', 'conversation_id=? AND sender_id=?',
        [$conv['id'], $senderId]) : 0;
    if ($sentCount >= 2) {
        return ['allowed' => false, 'reason' => 'Wait for the user to accept your message request', 'is_follower' => false];
    }
    return ['allowed' => true, 'reason' => 'request', 'is_follower' => false];
}

/**
 * Get or create a conversation between two users.
 */
function getOrCreateConversation(int $userA, int $userB): int {
    if ($userA > $userB) [$userA, $userB] = [$userB, $userA]; // canonical order
    $conv = DB::first(
        'SELECT id FROM message_conversations WHERE user_a=? AND user_b=?',
        [$userA, $userB]
    );
    if ($conv) return (int)$conv['id'];
    return (int)DB::insert('message_conversations', [
        'user_a'     => $userA,
        'user_b'     => $userB,
        'status'     => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
}

/* ── BOOST HELPERS ─────────────────────────────────── */

/**
 * Check if a post is currently boosted.
 */
function isBoosted(int $postId): bool {
    return DB::count('post_boosts', 'post_id=? AND status="active" AND expires_at > NOW()', [$postId]) > 0;
}

/**
 * Get boost info for a post.
 */
function getBoost(int $postId): ?array {
    return DB::first(
        "SELECT * FROM post_boosts WHERE post_id=? AND status='active' AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
        [$postId]
    );
}

/* ── EMAIL VERIFICATION ──────────────────────────────────────────────────── */
function sendVerificationEmail(int $userId, string $email, string $username): bool {
    try {
        $token      = generateToken(32);
        $expiresAt  = date('Y-m-d H:i:s', strtotime('+24 hours'));
        // Remove any existing token
        DB::exec('DELETE FROM email_verifications WHERE user_id=?', [$userId]);
        DB::insert('email_verifications', [
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $s       = getPlatformSettings();
        $appName = $s['app_name']  ?? 'Voxu';
        $appUrl  = APP_URL;
        $link    = $appUrl . '/auth/verify-email.php?token=' . urlencode($token);
        // Basic mail — replace with Mailgun/SES for production
        $subject = "Verify your {$appName} account";
        $body    = "Hi {$username},

Verify your account by clicking the link below:
{$link}

This link expires in 24 hours.

— The {$appName} Team";
        $headers = "From: noreply@" . parse_url($appUrl, PHP_URL_HOST) . "
";
        $headers .= "X-Mailer: PHP/" . phpversion();
        @mail($email, $subject, $body, $headers);
        return true;
    } catch (Throwable $e) {
        error_log('sendVerificationEmail failed: ' . $e->getMessage());
        return false;
    }
}

function verifyEmailToken(string $token): ?int {
    try {
        $row = DB::first(
            "SELECT * FROM email_verifications WHERE token=? AND used_at IS NULL AND expires_at > NOW()",
            [$token]
        );
        if (!$row) return null;
        DB::exec("UPDATE email_verifications SET used_at=NOW() WHERE id=?", [(int)$row['id']]);
        DB::exec("UPDATE users SET is_verified=1 WHERE id=?",              [(int)$row['user_id']]);
        return (int)$row['user_id'];
    } catch (Throwable) { return null; }
}

/* ── SOCIAL LOGIN ────────────────────────────────────────────────────────── */
function socialLogin(string $provider, string $providerId, string $email, string $name, ?string $avatar = null): ?int {
    try {
        // Find existing social login link
        $link = DB::first(
            "SELECT user_id FROM social_logins WHERE provider=? AND provider_id=?",
            [$provider, $providerId]
        );
        if ($link) return (int)$link['user_id'];

        // Check if email already registered
        $existing = DB::first("SELECT id FROM users WHERE email=?", [strtolower($email)]);
        if ($existing) {
            $uid = (int)$existing['id'];
        } else {
            // Create new user
            $username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $name)));
            $username = $username ?: 'user' . rand(1000, 9999);
            // Ensure unique username
            $base = $username; $n = 1;
            while (DB::count('users', 'username=?', [$username]) > 0) {
                $username = $base . $n++;
            }
            $uid = DB::insert('users', [
                'username'    => $username,
                'email'       => strtolower($email),
                'password'    => hashPassword(generateToken(24)),
                'status'      => 'active',
                'is_verified' => 1,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
            // Create wallet + profile + signup reward
            DB::exec("INSERT IGNORE INTO wallets (user_id, balance, points_balance) VALUES (?,0,0)", [$uid]);
            addPoints($uid, (int)getSetting('points_for_signup', DEFAULT_POINTS_FOR_SIGNUP), 'signup', 'Welcome bonus points');
            $plan = DB::first("SELECT id FROM subscription_plans WHERE slug='free' LIMIT 1");
            DB::exec("INSERT IGNORE INTO user_subscriptions (user_id,plan_id,billing,status,starts_at) VALUES (?,?,'free','active',NOW())",
                [$uid, (int)($plan['id'] ?? 1)]);
            // Download avatar if provided
            $avatarUrl = null;
            if ($avatar) {
                $imgData = @file_get_contents($avatar);
                if ($imgData) {
                    $dir = UPLOAD_DIR . '/avatars';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $fname = generateToken(16) . '.jpg';
                    @file_put_contents($dir . '/' . $fname, $imgData);
                    $avatarUrl = '/assets/uploads/avatars/' . $fname;
                }
            }
            DB::insert('user_profiles', ['user_id' => $uid, 'avatar' => $avatarUrl]);
        }
        // Link social account
        DB::exec(
            "INSERT IGNORE INTO social_logins (user_id, provider, provider_id) VALUES (?,?,?)",
            [$uid, $provider, $providerId]
        );
        DB::exec("UPDATE users SET is_verified=1 WHERE id=?", [$uid]);
        return $uid;
    } catch (Throwable $e) {
        error_log('socialLogin failed: ' . $e->getMessage());
        return null;
    }
}

/* ── REACTIONS ───────────────────────────────────────────────────────────── */
function getPostReactions(int $postId): array {
    try {
        return DB::query(
            "SELECT emoji, COUNT(*) as cnt FROM post_reactions WHERE post_id=? GROUP BY emoji ORDER BY cnt DESC",
            [$postId]
        );
    } catch (Throwable) { return []; }
}

function getUserReaction(int $userId, int $postId): ?string {
    try {
        $r = DB::first("SELECT emoji FROM post_reactions WHERE user_id=? AND post_id=?", [$userId, $postId]);
        return $r ? $r['emoji'] : null;
    } catch (Throwable) { return null; }
}

function toggleReaction(int $userId, int $postId, string $emoji): array {
    try {
        $existing = DB::first("SELECT id, emoji FROM post_reactions WHERE user_id=? AND post_id=?", [$userId, $postId]);
        if ($existing) {
            if ($existing['emoji'] === $emoji) {
                DB::exec("DELETE FROM post_reactions WHERE id=?", [(int)$existing['id']]);
                return ['action' => 'removed', 'emoji' => $emoji];
            } else {
                DB::exec("UPDATE post_reactions SET emoji=? WHERE id=?", [$emoji, (int)$existing['id']]);
                return ['action' => 'changed', 'emoji' => $emoji, 'old' => $existing['emoji']];
            }
        }
        DB::insert('post_reactions', ['user_id' => $userId, 'post_id' => $postId, 'emoji' => $emoji, 'created_at' => date('Y-m-d H:i:s')]);
        return ['action' => 'added', 'emoji' => $emoji];
    } catch (Throwable $e) {
        error_log('toggleReaction: ' . $e->getMessage());
        return ['action' => 'error'];
    }
}
