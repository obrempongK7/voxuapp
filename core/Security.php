<?php
/**
 * Voxu — Centralized Security Layer
 * @author  Jcode | ObrempongK
 *
 * Include at the top of every page that needs protection:
 *   require_once __DIR__ . '/../core/Security.php';
 *   Security::requireAuth();          // user must be logged in
 *   Security::requireAdmin();         // admin must be logged in
 *   Security::requireRole('admin');   // admin with specific role
 *   Security::verifyCsrf();           // CSRF check on POST
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

class Security
{
    /* ── Role hierarchy ──────────────────────────────────────── */
    private const ROLE_LEVELS = [
        'moderator'   => 1,
        'admin'       => 2,
        'super_admin' => 3,
    ];

    /* ── USER AUTH ───────────────────────────────────────────── */

    /**
     * Require an authenticated user session.
     * Redirects to login if not authenticated.
     */
    public static function requireAuth(): void
    {
        if (!isset($_SESSION['user_id'])) {
            self::redirectRelative('/auth/login.php');
        }
    }

    /**
     * Require an authenticated admin session.
     * Redirects to admin login if not authenticated.
     */
    public static function requireAdmin(): void
    {
        if (!isset($_SESSION['admin_id'])) {
            self::redirectRelative('/admin/login.php');
        }
    }

    /**
     * Require a minimum admin role level.
     * Aborts with 403 if role is insufficient.
     *
     * @param string $minRole  'moderator' | 'admin' | 'super_admin'
     */
    public static function requireRole(string $minRole): void
    {
        self::requireAdmin();
        $current = strtolower($_SESSION['admin_role'] ?? 'moderator');
        $required = strtolower($minRole);

        $currentLevel  = self::ROLE_LEVELS[$current]  ?? 0;
        $requiredLevel = self::ROLE_LEVELS[$required] ?? 999;

        if ($currentLevel < $requiredLevel) {
            http_response_code(403);
            self::renderForbidden($current, $minRole);
            exit;
        }
    }

    /**
     * Check if the current admin has at least $minRole (returns bool, no exit).
     */
    public static function hasRole(string $minRole): bool
    {
        $current  = strtolower($_SESSION['admin_role'] ?? 'moderator');
        $required = strtolower($minRole);
        return (self::ROLE_LEVELS[$current] ?? 0) >= (self::ROLE_LEVELS[$required] ?? 999);
    }

    /**
     * Return current admin role or null.
     */
    public static function adminRole(): ?string
    {
        return $_SESSION['admin_role'] ?? null;
    }

    /* ── CSRF ────────────────────────────────────────────────── */

    /**
     * Generate (or return cached) CSRF token for the current session.
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Return an HTML hidden input with the CSRF token.
     */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8')
            . '"/>';
    }

    /**
     * Verify CSRF token. Accepts token from POST body or X-CSRF-Token header.
     * Sends JSON error (403) and exits if token is invalid.
     */
    public static function verifyCsrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') return;

        $submitted = $_POST['_csrf']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        $expected = $_SESSION['csrf_token'] ?? '';

        if (!$expected || !hash_equals($expected, $submitted)) {
            http_response_code(403);
            if (self::isApiRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
            } else {
                echo '<p style="font-family:sans-serif;color:#FF4444;padding:40px">
                        <strong>403 — Request validation failed.</strong><br/>
                        Please go back and try again.
                      </p>';
            }
            exit;
        }
    }

    /**
     * Rotate CSRF token (call after successful form processing).
     */
    public static function rotateCsrf(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /* ── INPUT SANITISATION ──────────────────────────────────── */

    /**
     * Sanitize a single value: strip tags, trim.
     */
    public static function sanitize(mixed $value): string
    {
        return trim(strip_tags((string)$value));
    }

    /**
     * HTML-escape a value for safe output.
     */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize a URL — only allow http/https schemes.
     */
    public static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'])) return '';
        if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) return '';
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }

    /* ── RATE LIMITING (session-based, lightweight) ─────────── */

    /**
     * Allow at most $max attempts per $windowSeconds.
     * Returns false if rate limit exceeded.
     */
    public static function rateLimit(string $key, int $max = 10, int $windowSeconds = 60): bool
    {
        $now = time();
        $k   = 'rl_' . md5($key);

        $data = $_SESSION[$k] ?? ['count' => 0, 'reset' => $now + $windowSeconds];

        if ($now > $data['reset']) {
            $data = ['count' => 0, 'reset' => $now + $windowSeconds];
        }

        $data['count']++;
        $_SESSION[$k] = $data;

        return $data['count'] <= $max;
    }

    /* ── REQUEST HELPERS ─────────────────────────────────────── */

    /** True if request expects a JSON response (API call). */
    public static function isApiRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xReq   = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json')
            || strtolower($xReq) === 'xmlhttprequest'
            || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }

    /** Redirect using a relative path (works on any domain). */
    public static function redirectRelative(string $path): void
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        // Detect sub-folder installs: if index.php is /subdir/index.php, base = /subdir
        // For root installs, base = ''
        // We use the APP_URL scheme+host + path for absolute-safe redirect
        $url = self::buildUrl($path);
        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * Build an absolute URL from a root-relative path.
     * Works correctly even if APP_URL is set to the wrong domain (uses
     * the current request host as fallback).
     */
    public static function buildUrl(string $path): string
    {
        // Prefer current request host over the hardcoded APP_URL
        // This way the site works even if APP_URL hasn't been updated yet
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? parse_url(APP_URL, PHP_URL_HOST) ?? 'localhost';
        return $scheme . '://' . $host . $path;
    }

    /* ── PERMISSION TABLE ────────────────────────────────────── */

    /**
     * What each role is allowed to do.
     * Returns true/false for a given action.
     */
    public static function can(string $action): bool
    {
        $role = strtolower($_SESSION['admin_role'] ?? 'moderator');

        $permissions = [
            // action                    => [min_role]
            'view_users'                 => 'moderator',
            'edit_users'                 => 'moderator',
            'ban_users'                  => 'admin',
            'delete_users'               => 'admin',
            'manage_content'             => 'moderator',
            'delete_content'             => 'admin',
            'manage_campaigns'           => 'admin',
            'manage_finance'             => 'admin',
            'approve_withdrawals'        => 'admin',
            'adjust_wallets'             => 'admin',
            'view_reports'               => 'moderator',
            'manage_settings'            => 'admin',
            'manage_branding'            => 'admin',
            'manage_payment_gateways'    => 'super_admin',
            'manage_admins'              => 'super_admin',
            'view_logs'                  => 'moderator',
            'send_notifications'         => 'admin',
            'send_emails'                => 'admin',
            'manage_subscriptions'       => 'admin',
            'manage_advertising'         => 'admin',
            'grant_custom_url'           => 'admin',
            'insert_custom_html_ads'     => 'super_admin',
        ];

        $required = $permissions[$action] ?? 'super_admin';
        return self::hasRole($required);
    }

    /* ── PRIVATE HELPERS ─────────────────────────────────────── */

    private static function renderForbidden(string $currentRole, string $requiredRole): void
    {
        $isAdmin = isset($_SESSION['admin_id']);
        ?>
        <!DOCTYPE html>
        <html lang="en"><head><meta charset="UTF-8"/>
        <title>403 — Access Denied</title>
        <style>
          body{font-family:-apple-system,'Inter',sans-serif;background:#0B0B0F;color:#fff;
               display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
          .box{background:#16161E;border:1px solid rgba(255,255,255,.07);border-radius:16px;
               padding:40px;text-align:center;max-width:440px}
          h1{font-size:48px;font-weight:800;color:#FF4444;margin-bottom:8px}
          h2{font-size:20px;margin-bottom:12px}
          p{color:#A0A0B0;font-size:14px;margin-bottom:20px}
          a{display:inline-block;padding:10px 24px;background:#6C3BFF;color:#fff;
            border-radius:8px;text-decoration:none;font-weight:600}
        </style>
        </head><body>
        <div class="box">
          <h1>403</h1>
          <h2>Access Denied</h2>
          <p>Your role (<strong><?= htmlspecialchars(ucfirst(str_replace('_',' ',$currentRole))) ?></strong>)
             does not have permission to access this page.<br/>
             Required role: <strong><?= htmlspecialchars(ucfirst(str_replace('_',' ',$requiredRole))) ?></strong></p>
          <a href="/admin/">← Back to Dashboard</a>
        </div>
        </body></html>
        <?php
    }
}
