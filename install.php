<?php
/**
 * Voxu — Installation & Upgrade Wizard
 * =====================================================================
 * Run once to create all database tables and seed required data.
 * Supports fresh install AND upgrade (ALTER TABLE migrations).
 * DELETE this file after successful installation!
 * =====================================================================
 * @author  Jcode | ObrempongK
 * @version 2.1
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

/* ── STEP TRACKING ──────────────────────────────────────── */
session_start();
$step    = (int)($_GET['step']  ?? 1);
$errors  = [];
$success = [];
$info    = [];

/* ── HELPER ─────────────────────────────────────────────── */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function ok(string $m): void  { global $success; $success[] = $m; }
function err(string $m): void { global $errors;  $errors[]  = $m; }
function inf(string $m): void { global $info;    $info[]    = $m; }

/* ── REQUIREMENTS CHECK ─────────────────────────────────── */
function checkRequirements(): array {
    $checks = [];
    $checks[] = ['PHP Version ≥ 8.0',  PHP_VERSION_ID >= 80000, PHP_VERSION];
    $checks[] = ['PDO Extension',       extension_loaded('pdo'),       extension_loaded('pdo')       ? 'Loaded' : 'MISSING'];
    $checks[] = ['PDO MySQL Driver',    extension_loaded('pdo_mysql'),  extension_loaded('pdo_mysql') ? 'Loaded' : 'MISSING'];
    $checks[] = ['JSON Extension',      extension_loaded('json'),       extension_loaded('json')      ? 'Loaded' : 'MISSING'];
    $checks[] = ['Mbstring Extension',  extension_loaded('mbstring'),   extension_loaded('mbstring')  ? 'Loaded' : 'MISSING'];
    $checks[] = ['FileInfo Extension',  extension_loaded('fileinfo'),   extension_loaded('fileinfo')  ? 'Loaded' : 'MISSING'];
    $checks[] = ['OpenSSL Extension',   extension_loaded('openssl'),    extension_loaded('openssl')   ? 'Loaded' : 'MISSING'];
    $checks[] = ['Upload dir writable', is_writable(__DIR__ . '/assets/uploads'), is_writable(__DIR__ . '/assets/uploads') ? 'Writable' : 'NOT WRITABLE'];
    $checks[] = ['config.php readable', file_exists(__DIR__ . '/config.php'), file_exists(__DIR__ . '/config.php') ? 'Found' : 'MISSING'];
    return $checks;
}

/* ── ALL MIGRATIONS (tables + columns) ──────────────────── */
function getMigrations(): array {
    return [

/* ━━━━━━━━ USERS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`     VARCHAR(30)  NOT NULL UNIQUE,
  `email`        VARCHAR(120) NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL,
  `status`       ENUM('active','suspended','banned') DEFAULT 'active',
  `is_verified`  TINYINT(1)  DEFAULT 0,
  `last_login`   DATETIME    DEFAULT NULL,
  `last_ip`      VARCHAR(45) DEFAULT NULL,
  `created_at`   DATETIME    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email    (email),
  INDEX idx_username (username),
  INDEX idx_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `user_profiles` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`          INT UNSIGNED NOT NULL UNIQUE,
  `avatar`           VARCHAR(255) DEFAULT NULL,
  `bio`              VARCHAR(300) DEFAULT NULL,
  `website`          VARCHAR(255) DEFAULT NULL,
  `country`          VARCHAR(60)  DEFAULT NULL,
  `phone`            VARCHAR(20)  DEFAULT NULL,
  `date_of_birth`    DATE         DEFAULT NULL,
  `custom_url_slug`  VARCHAR(30)  DEFAULT NULL UNIQUE,
  `twitter`          VARCHAR(100) DEFAULT NULL,
  `instagram`        VARCHAR(100) DEFAULT NULL,
  `updated_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user     (user_id),
  INDEX idx_slug     (custom_url_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `users_audit_logs` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `action`      VARCHAR(60)  NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user   (user_id),
  INDEX idx_action (action),
  INDEX idx_date   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ ADMINS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(80)  NOT NULL,
  `email`      VARCHAR(120) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('super_admin','admin','moderator') DEFAULT 'moderator',
  `api_token`  VARCHAR(64)  DEFAULT NULL,
  `status`     ENUM('active','inactive') DEFAULT 'active',
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `action`      VARCHAR(60)  NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin  (admin_id),
  INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ CHANNELS & POSTS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `channels` (
  `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`      VARCHAR(60)  NOT NULL,
  `slug`      VARCHAR(60)  NOT NULL UNIQUE,
  `icon`      VARCHAR(10)  DEFAULT NULL,
  `is_active` TINYINT(1)   DEFAULT 1,
  `sort_order`INT UNSIGNED DEFAULT 0,
  `created_at`DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `posts` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `channel_id`  INT UNSIGNED DEFAULT NULL,
  `title`       VARCHAR(280) NOT NULL,
  `audio_url`   VARCHAR(255) DEFAULT NULL,
  `image_url`   VARCHAR(255) DEFAULT NULL,
  `duration`    INT UNSIGNED DEFAULT 0,
  `play_count`  INT UNSIGNED DEFAULT 0,
  `reply_count` INT UNSIGNED DEFAULT 0,
  `status`      ENUM('active','removed','draft') DEFAULT 'active',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user    (user_id),
  INDEX idx_channel (channel_id),
  INDEX idx_status  (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `post_hashtags` (
  `id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT UNSIGNED NOT NULL,
  `hashtag` VARCHAR(50)  NOT NULL,
  UNIQUE KEY uniq_post_tag (post_id, hashtag),
  INDEX idx_post (post_id),
  INDEX idx_tag  (hashtag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `post_boosts` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       ENUM('points','cash') DEFAULT 'points',
  `amount`     DECIMAL(10,2) DEFAULT 0,
  `status`     ENUM('active','expired','cancelled') DEFAULT 'active',
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post    (post_id),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `replies` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `text`       TEXT         DEFAULT NULL,
  `audio_url`  VARCHAR(255) DEFAULT NULL,
  `duration`   INT UNSIGNED DEFAULT 0,
  `status`     ENUM('active','removed') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post (post_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `energy_transactions` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `giver_id`    INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `post_id`     INT UNSIGNED NOT NULL,
  `amount`      INT UNSIGNED DEFAULT 1,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post     (post_id),
  INDEX idx_giver    (giver_id),
  INDEX idx_receiver (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `followers` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `follower_id`  INT UNSIGNED NOT NULL,
  `following_id` INT UNSIGNED NOT NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_follow (follower_id, following_id),
  INDEX idx_follower  (follower_id),
  INDEX idx_following (following_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ STATUS SYSTEM ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `status_posts` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT UNSIGNED NOT NULL,
  `type`            ENUM('image','video','text','voice') DEFAULT 'text',
  `media_url`       VARCHAR(255) DEFAULT NULL,
  `text`            TEXT         DEFAULT NULL,
  `bg_color`        VARCHAR(100) DEFAULT NULL,
  `caption`         VARCHAR(200) DEFAULT NULL,
  `source_label`    VARCHAR(100) DEFAULT NULL,
  `contact_link`    VARCHAR(500) DEFAULT NULL,
  `views_count`     INT UNSIGNED DEFAULT 0,
  `clicks_count`    INT UNSIGNED DEFAULT 0,
  `earnings_points` INT UNSIGNED DEFAULT 0,
  `status`          ENUM('active','expired','removed') DEFAULT 'active',
  `expires_at`      DATETIME NOT NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user    (user_id),
  INDEX idx_expires (expires_at),
  INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `status_views` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status_id`  INT UNSIGNED NOT NULL,
  `viewer_id`  INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status_id),
  INDEX idx_viewer (viewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `status_clicks` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `click_type` VARCHAR(30)  DEFAULT 'link',
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ WALLET & FINANCE ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `wallets` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT UNSIGNED NOT NULL UNIQUE,
  `balance`        DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `points_balance` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `is_frozen`      TINYINT(1) DEFAULT 0,
  `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `transactions` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `type`        VARCHAR(50)  NOT NULL,
  `amount`      DECIMAL(18,4) NOT NULL,
  `reference`   VARCHAR(100) DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `status`      ENUM('pending','completed','failed','reversed') DEFAULT 'completed',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_type (type),
  INDEX idx_ref  (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `points_transactions` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `type`        ENUM('credit','debit') NOT NULL,
  `points`      BIGINT NOT NULL,
  `source`      VARCHAR(60)  DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user   (user_id),
  INDEX idx_source (source),
  INDEX idx_date   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT UNSIGNED NOT NULL,
  `amount`          BIGINT UNSIGNED NOT NULL COMMENT 'points',
  `net_amount`      DECIMAL(18,4) DEFAULT 0,
  `method`          VARCHAR(60)  NOT NULL,
  `account_details` VARCHAR(255) DEFAULT NULL,
  `status`          ENUM('pending','approved','processing','completed','rejected') DEFAULT 'pending',
  `admin_note`      VARCHAR(255) DEFAULT NULL,
  `processed_at`    DATETIME DEFAULT NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user   (user_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `deposits` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `amount`      DECIMAL(18,4) NOT NULL,
  `gateway`     VARCHAR(50)  NOT NULL,
  `gateway_ref` VARCHAR(100) DEFAULT NULL,
  `status`      ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `tips` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sender_id`   INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `post_id`     INT UNSIGNED DEFAULT NULL,
  `amount`      INT UNSIGNED NOT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sender   (sender_id),
  INDEX idx_receiver (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `user_transfers` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sender_id`   INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `amount`      DECIMAL(18,4) NOT NULL,
  `note`        VARCHAR(255) DEFAULT NULL,
  `reference`   VARCHAR(100) DEFAULT NULL,
  `status`      ENUM('completed','reversed') DEFAULT 'completed',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sender   (sender_id),
  INDEX idx_receiver (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `admin_wallet_adjustments` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       ENUM('credit','debit') NOT NULL,
  `amount`     DECIMAL(18,4) NOT NULL,
  `reason`     VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ PAYMENT GATEWAYS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `payment_gateways` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`            VARCHAR(80)  NOT NULL,
  `code`            VARCHAR(40)  NOT NULL UNIQUE,
  `supports_mobile` TINYINT(1)  DEFAULT 0,
  `supports_intl`   TINYINT(1)  DEFAULT 0,
  `min_deposit`     DECIMAL(10,2) DEFAULT 1.00,
  `fee_rate`        DECIMAL(6,4)  DEFAULT 0.0000,
  `is_active`       TINYINT(1)  DEFAULT 0,
  `sort_order`      INT UNSIGNED DEFAULT 0,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `payment_gateway_settings` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `gateway_id`   INT UNSIGNED NOT NULL,
  `setting_key`  VARCHAR(60)  NOT NULL,
  `setting_value`TEXT         DEFAULT NULL,
  `is_secret`    TINYINT(1)   DEFAULT 0,
  UNIQUE KEY uniq_gw_key (gateway_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ SUBSCRIPTIONS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`               VARCHAR(60)    NOT NULL,
  `slug`               VARCHAR(30)    NOT NULL UNIQUE,
  `price_monthly`      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `price_yearly`       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `max_recording_secs` INT UNSIGNED   NOT NULL DEFAULT 180,
  `max_daily_earnings` INT UNSIGNED   NOT NULL DEFAULT 1000,
  `min_withdrawal_pts` INT UNSIGNED   NOT NULL DEFAULT 500,
  `cashout_multiplier` DECIMAL(4,2)   NOT NULL DEFAULT 1.00,
  `max_status_per_day` INT UNSIGNED   NOT NULL DEFAULT 10,
  `can_voice_bg`       TINYINT(1)     NOT NULL DEFAULT 0,
  `can_analytics`      TINYINT(1)     NOT NULL DEFAULT 0,
  `can_custom_link`    TINYINT(1)     NOT NULL DEFAULT 0,
  `verified_badge`     TINYINT(1)     NOT NULL DEFAULT 0,
  `priority_support`   TINYINT(1)     NOT NULL DEFAULT 0,
  `color`              VARCHAR(30)    NOT NULL DEFAULT '',
  `icon`               VARCHAR(20)    NOT NULL DEFAULT '',
  `is_active`          TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`         DATETIME       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL UNIQUE,
  `plan_id`    INT UNSIGNED NOT NULL DEFAULT 1,
  `billing`    ENUM('free','monthly','yearly') DEFAULT 'free',
  `status`     ENUM('active','expired','cancelled','pending') DEFAULT 'active',
  `starts_at`  DATETIME NOT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `auto_renew` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user    (user_id),
  INDEX idx_plan    (plan_id),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `subscription_transactions` (
  `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`   INT UNSIGNED NOT NULL,
  `plan_id`   INT UNSIGNED NOT NULL,
  `amount`    DECIMAL(10,2) NOT NULL,
  `billing`   ENUM('monthly','yearly') NOT NULL,
  `reference` VARCHAR(100) NOT NULL UNIQUE,
  `gateway`   VARCHAR(50)  DEFAULT NULL,
  `status`    ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  `created_at`DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ NOTIFICATIONS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       VARCHAR(40)  NOT NULL DEFAULT 'info',
  `message`    TEXT         NOT NULL,
  `data`       JSON         DEFAULT NULL,
  `is_read`    TINYINT(1)   DEFAULT 0,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user    (user_id),
  INDEX idx_unread  (user_id, is_read),
  INDEX idx_date    (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ REPORTS & FRAUD ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `reports` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reporter_id` INT UNSIGNED NOT NULL,
  `target_type` ENUM('post','user','status','comment') DEFAULT 'post',
  `target_id`   INT UNSIGNED NOT NULL,
  `reason`      VARCHAR(60)  NOT NULL,
  `details`     TEXT         DEFAULT NULL,
  `status`      ENUM('open','reviewing','resolved','dismissed') DEFAULT 'open',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reporter (reporter_id),
  INDEX idx_target   (target_type, target_id),
  INDEX idx_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `fraud_flags` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `reason`     VARCHAR(255) NOT NULL,
  `flagged_by` VARCHAR(20)  DEFAULT 'system',
  `status`     ENUM('active','resolved') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user   (user_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ CAMPAIGNS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `campaigns` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `creator_id`  INT UNSIGNED NOT NULL,
  `title`       VARCHAR(120) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `type`        VARCHAR(40)  DEFAULT 'general',
  `status`      ENUM('active','paused','completed','cancelled','draft') DEFAULT 'active',
  `starts_at`   DATETIME     NOT NULL,
  `ends_at`     DATETIME     DEFAULT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `campaign_reward_pools` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `campaign_id`      INT UNSIGNED NOT NULL UNIQUE,
  `total_points`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `remaining`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `reward_per_action`INT UNSIGNED    NOT NULL DEFAULT 10,
  `max_per_user`     INT UNSIGNED    NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `campaign_responses` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `campaign_id` INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_campaign (campaign_id),
  INDEX idx_user     (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `campaign_payouts` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `campaign_id` INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `points`      INT UNSIGNED NOT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ MESSAGING ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `message_conversations` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_a`          INT UNSIGNED NOT NULL,
  `user_b`          INT UNSIGNED NOT NULL,
  `status`          ENUM('pending','accepted','blocked') DEFAULT 'pending',
  `last_message_id` INT UNSIGNED DEFAULT NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_conv (user_a, user_b),
  INDEX idx_a       (user_a),
  INDEX idx_b       (user_b),
  INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `messages` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT UNSIGNED NOT NULL,
  `sender_id`       INT UNSIGNED NOT NULL,
  `body`            TEXT NOT NULL,
  `is_read`         TINYINT(1)   DEFAULT 0,
  `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conv    (conversation_id),
  INDEX idx_sender  (sender_id),
  INDEX idx_read    (conversation_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ PODCASTS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `podcasts` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `audio_url`   VARCHAR(255) NOT NULL,
  `cover_url`   VARCHAR(255) DEFAULT NULL,
  `duration`    INT UNSIGNED DEFAULT 0,
  `category`    VARCHAR(60)  DEFAULT 'general',
  `play_count`  INT UNSIGNED DEFAULT 0,
  `like_count`  INT UNSIGNED DEFAULT 0,
  `status`      ENUM('active','removed','pending') DEFAULT 'active',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user   (user_id),
  INDEX idx_status (status),
  INDEX idx_plays  (play_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ ADVERTISING ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `ad_slots` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`            VARCHAR(120) NOT NULL,
  `slot`             VARCHAR(60)  NOT NULL,
  `type`             ENUM('image','html','adsense') NOT NULL DEFAULT 'image',
  `image_url`        VARCHAR(255) DEFAULT NULL,
  `link_url`         VARCHAR(500) DEFAULT NULL,
  `open_new_tab`     TINYINT(1)   DEFAULT 1,
  `custom_html`      TEXT         DEFAULT NULL,
  `is_active`        TINYINT(1)   DEFAULT 1,
  `sort_order`       INT UNSIGNED DEFAULT 0,
  `impression_count` INT UNSIGNED DEFAULT 0,
  `click_count`      INT UNSIGNED DEFAULT 0,
  `expires_at`       DATETIME     DEFAULT NULL,
  `created_by`       INT UNSIGNED DEFAULT NULL,
  `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_slot   (slot),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ PLATFORM SETTINGS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `platform_settings` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key`   VARCHAR(80)  NOT NULL UNIQUE,
  `setting_value` TEXT         DEFAULT NULL,
  `updated_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",


/* ━━━━━━━━ EMOJI REACTIONS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `post_reactions` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `post_id`    INT UNSIGNED NOT NULL,
  `emoji`      VARCHAR(10)  NOT NULL DEFAULT '👍',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_reaction (user_id, post_id),
  INDEX idx_post (post_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ EMAIL VERIFICATION ━━━━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `email_verifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `token`      VARCHAR(64)  NOT NULL UNIQUE,
  `expires_at` DATETIME     NOT NULL,
  `used_at`    DATETIME     DEFAULT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token),
  INDEX idx_user  (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ━━━━━━━━ SOCIAL LOGIN ACCOUNTS ━━━━━━━━━━━━━━━━━━━━━━━ */
"CREATE TABLE IF NOT EXISTS `social_logins` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `provider`    ENUM('google','facebook','twitter') NOT NULL,
  `provider_id` VARCHAR(255) NOT NULL,
  `access_token`TEXT         DEFAULT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_provider (provider, provider_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    ]; // end getMigrations()
}

/* ── COLUMN-LEVEL UPGRADES (for existing installs) ────── */
function getColumnUpgrades(): array {
    return [
        // table          => [column, definition]
        'posts'           => [
            ['image_url',  "VARCHAR(255) DEFAULT NULL AFTER audio_url"],
        ],
        'user_profiles'   => [
            ['custom_url_slug', "VARCHAR(30) DEFAULT NULL UNIQUE AFTER website"],
            ['twitter',         "VARCHAR(100) DEFAULT NULL AFTER custom_url_slug"],
            ['instagram',       "VARCHAR(100) DEFAULT NULL AFTER twitter"],
        ],
        'user_profiles'   => [
            ['custom_url_slug', "VARCHAR(30) DEFAULT NULL UNIQUE AFTER website"],
            ['twitter',         "VARCHAR(100) DEFAULT NULL"],
            ['instagram',       "VARCHAR(100) DEFAULT NULL"],
            ['facebook',        "VARCHAR(100) DEFAULT NULL"],
            ['linkedin',        "VARCHAR(100) DEFAULT NULL"],
            ['occupation',      "VARCHAR(100) DEFAULT NULL"],
            ['company',         "VARCHAR(100) DEFAULT NULL"],
            ['city',            "VARCHAR(80)  DEFAULT NULL"],
            ['country',         "VARCHAR(60)  DEFAULT NULL"],
            ['education',       "VARCHAR(200) DEFAULT NULL"],
            ['skills',          "TEXT         DEFAULT NULL"],
            ['interests',       "TEXT         DEFAULT NULL"],
            ['relationship_status', "ENUM('single','in_relationship','married','prefer_not') DEFAULT NULL"],
            ['cover_photo',     "VARCHAR(255) DEFAULT NULL"],
            ['gender',          "ENUM('male','female','non_binary','prefer_not') DEFAULT NULL"],
            ['date_of_birth',   "DATE         DEFAULT NULL"],
        ],
        'admins'          => [
            ['last_login', "DATETIME DEFAULT NULL"],
        ],
        'status_posts'    => [
            ['earnings_points', "INT UNSIGNED DEFAULT 0"],
        ],
        'podcasts'        => [
            ['like_count', "INT UNSIGNED DEFAULT 0 AFTER play_count"],
        ],
    ];
}

/* ── SEED DATA ──────────────────────────────────────────── */
function seedSettings(PDO $pdo): void {
    $defaults = [
        'app_name'             => 'Voxu',
        'app_tagline'          => 'Speak. Be Seen. Earn.',
        'support_email'        => '',
        'currency'             => 'USD',
        'currency_symbol'      => '$',
        'points_per_post'      => '5',
        'points_per_reply'     => '2',
        'points_for_signup'    => '20',
        'reward_per_view'      => '1',
        'reward_per_click'     => '3',
        'max_daily_earnings'   => '1000',
        'min_withdrawal'       => '500',
        'points_to_cash_rate'  => '100',
        'status_expiry_hours'  => '24',
        'registration_open'    => '1',
        'maintenance_mode'     => '0',
        'feature_voice'        => '1',
        'feature_status'       => '1',
        'feature_campaigns'    => '1',
        'feature_groups'       => '0',
        'ads_enabled'          => '0',
        'adsense_code'         => '',
        'app_link_ios'         => '',
        'app_link_android'     => '',
        'app_link_huawei'      => '',
        'brand_color'          => '#6C3BFF',
        'accent_color'         => '#00D1FF',
    ];
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES (?,?)"
    );
    foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);
}

function seedPlans(PDO $pdo): void {
    $plans = [
        [1,'Free',     'free',    0.00,  0.00,  180, 1000, 500, 1.00, 10, 0,0,0,0,0,'#A0A0B0',''],
        [2,'Silver',   'silver',  4.99, 49.99,  300, 2000, 200, 1.50, 20, 0,1,0,0,0,'#00D1FF',''],
        [3,'Gold',     'gold',    9.99, 99.99,  600, 5000, 100, 2.00, 50, 1,1,1,1,0,'#FFB830',''],
        [4,'Platinum', 'platinum',19.99,199.99,  0, 10000,  50, 3.00,100, 1,1,1,1,1,'#6C3BFF',''],
    ];
    $icons = ['',''];
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO subscription_plans
         (id,name,slug,price_monthly,price_yearly,max_recording_secs,max_daily_earnings,
          min_withdrawal_pts,cashout_multiplier,max_status_per_day,can_voice_bg,
          can_analytics,can_custom_link,verified_badge,priority_support,color,icon)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $iconMap = ['free'=>'', 'silver'=>'', 'gold'=>'', 'platinum'=>''];
    foreach ($plans as $p) $stmt->execute($p);
}

function seedGateways(PDO $pdo): void {
    $gw = [
        ['Paystack',    'paystack',    1,0,100,0.0150],
        ['Flutterwave', 'flutterwave', 1,1,100,0.0140],
        ['PayPal',      'paypal',      1,1,  1,0.0290],
        ['Stripe',      'stripe',      1,1,  1,0.0290],
        ['Razorpay',    'razorpay',    0,0,  1,0.0200],
        ['Paystack GH', 'paystack_gh', 1,0,100,0.0150],
        ['MTN MoMo',    'mtn_momo',    1,0, 10,0.0150],
        ['Airtel Money','airtel_money',1,0, 10,0.0200],
    ];
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO payment_gateways
         (name,code,supports_mobile,supports_intl,min_deposit,fee_rate,is_active)
         VALUES (?,?,?,?,?,?,0)"
    );
    foreach ($gw as $g) $stmt->execute($g);
}

function seedChannels(PDO $pdo): void {
    $ch = [
        ['General',     'general',     1,0],
        ['Technology',  'technology',  1,1],
        ['Business',    'business',    1,2],
        ['Music',       'music',       1,3],
        ['Comedy',      'comedy',      1,4],
        ['Sports',      'sports',      1,5],
        ['Education',   'education',   1,6],
        ['News',        'news',        1,7],
        ['Health',      'health',      1,8],
        ['Gaming',      'gaming',      1,9],
    ];
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO channels (name,slug,is_active,sort_order) VALUES (?,?,?,?)"
    );
    foreach ($ch as $c) $stmt->execute($c);
}

/* ── STEP 1: REQUIREMENTS ───────────────────────────────── */
if ($step === 1):
    $checks = checkRequirements();
    $allOk  = !in_array(false, array_column($checks, 1), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Voxu Installer</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif;background:#0B0B0F;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#16161E;border:1px solid rgba(255,255,255,.07);border-radius:20px;padding:40px;width:100%;max-width:600px}
    .logo{font-size:32px;font-weight:800;margin-bottom:4px}
    .logo span{color:#6C3BFF}
    .sub{font-size:13px;color:#A0A0B0;text-transform:uppercase;letter-spacing:.1em;margin-bottom:28px}
    .steps{display:flex;gap:8px;margin-bottom:28px}
    .step{flex:1;height:4px;background:#1A1A22;border-radius:2px}
    .step.done{background:#6C3BFF}
    .step.active{background:linear-gradient(90deg,#6C3BFF,#00D1FF)}
    h2{font-size:20px;font-weight:700;margin-bottom:16px}
    .check-row{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:8px;background:#1A1A22;margin-bottom:8px}
    .check-label{font-size:14px;color:#A0A0B0}
    .check-val{font-size:13px;font-weight:600}
    .ok{color:#00FF9C}.fail{color:#FF4444}
    .btn{display:block;width:100%;padding:14px;background:#6C3BFF;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;text-align:center;margin-top:20px;transition:.2s}
    .btn:hover{background:#5A30D0}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .warn{background:rgba(255,184,48,.1);border:1px solid rgba(255,184,48,.3);color:#FFB830;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
    .info{background:rgba(0,209,255,.1);border:1px solid rgba(0,209,255,.2);color:#00D1FF;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
    label{font-size:13px;color:#A0A0B0;display:block;margin-bottom:5px}
    input,select{width:100%;background:#1A1A22;border:1px solid rgba(255,255,255,.08);color:#fff;padding:11px 14px;border-radius:8px;font-size:14px;outline:none;margin-bottom:14px}
    input:focus,select:focus{border-color:#6C3BFF;box-shadow:0 0 0 3px rgba(108,59,255,.15)}
    .err-box{background:rgba(255,68,68,.1);border:1px solid rgba(255,68,68,.4);color:#FF4444;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:14px}
    .suc-box{background:rgba(0,255,156,.1);border:1px solid rgba(0,255,156,.3);color:#00FF9C;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:14px}
    .cred-box{background:#1A1A22;border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:16px;margin-bottom:14px}
    .cred-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:13px}
    .cred-key{color:#A0A0B0}
    .cred-val{color:#00FF9C;font-family:monospace;font-weight:600}
    .divider{height:1px;background:rgba(255,255,255,.06);margin:20px 0}
    .note{font-size:12px;color:#5A5A72;line-height:1.6}
  </style>
</head>
<body>
<div class="card">
  <div class="logo">Vo<span>xu</span></div>
  <div class="sub">Installation Wizard</div>

  <div class="steps">
    <div class="step active"></div>
    <div class="step"></div>
    <div class="step"></div>
    <div class="step"></div>
  </div>

  <h2>Step 1 — System Requirements</h2>
  <?php foreach ($checks as [$label,$ok,$val]): ?>
  <div class="check-row">
    <span class="check-label"><?= h($label) ?></span>
    <span class="check-val <?= $ok?'ok':'fail' ?>"><?= $ok?'✓':'' ?> <?= h($val) ?></span>
  </div>
  <?php endforeach; ?>

  <?php if (!$allOk): ?>
  <div class="warn" style="margin-top:16px">⚠ Some requirements are not met. Please fix them before continuing.</div>
  <?php else: ?>
  <a href="?step=2" class="btn">Continue →</a>
  <?php endif; ?>
</div>
</body></html>
<?php
/* ── STEP 2: DATABASE + ADMIN SETUP ─────────────────────── */
elseif ($step === 2):
    $prefill_email = $_SESSION['install_email'] ?? '';
?>
<body style="font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif;background:#0B0B0F;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  .card{background:#16161E;border:1px solid rgba(255,255,255,.07);border-radius:20px;padding:40px;width:100%;max-width:600px}
  .logo{font-size:32px;font-weight:800;margin-bottom:4px}.logo span{color:#6C3BFF}
  .sub{font-size:13px;color:#A0A0B0;text-transform:uppercase;letter-spacing:.1em;margin-bottom:28px}
  .steps{display:flex;gap:8px;margin-bottom:28px}
  .step{flex:1;height:4px;background:#1A1A22;border-radius:2px}
  .step.done{background:#6C3BFF}.step.active{background:linear-gradient(90deg,#6C3BFF,#00D1FF)}
  h2{font-size:20px;font-weight:700;margin-bottom:6px}
  .hint{font-size:13px;color:#5A5A72;margin-bottom:20px}
  label{font-size:13px;color:#A0A0B0;display:block;margin-bottom:5px;margin-top:12px}
  input,select{width:100%;background:#1A1A22;border:1px solid rgba(255,255,255,.08);color:#fff;padding:11px 14px;border-radius:8px;font-size:14px;outline:none}
  input:focus,select:focus{border-color:#6C3BFF;box-shadow:0 0 0 3px rgba(108,59,255,.15)}
  .btn{display:block;width:100%;padding:14px;background:#6C3BFF;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;margin-top:20px;transition:.2s}
  .btn:hover{background:#5A30D0}
  .err-box{background:rgba(255,68,68,.1);border:1px solid rgba(255,68,68,.4);color:#FF4444;padding:12px;border-radius:8px;font-size:13px;margin-bottom:14px}
  .section-title{font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#A0A0B0;margin-top:24px;margin-bottom:4px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.06)}
  .note{font-size:12px;color:#5A5A72;margin-top:4px}
</style>
<div class="card">
  <div class="logo">Vo<span>xu</span></div>
  <div class="sub">Installation Wizard</div>
  <div class="steps">
    <div class="step done"></div><div class="step active"></div><div class="step"></div><div class="step"></div>
  </div>
  <h2>Step 2 — Configuration</h2>
  <p class="hint">Edit <code>config.php</code> directly, then click Continue. Your current config values are shown below.</p>

  <?php
  // Read current config.php to show values
  $cfgFile = __DIR__ . '/config.php';
  $cfgExists = file_exists($cfgFile);
  if ($cfgExists) {
      require_once $cfgFile;
  }
  $appUrl     = defined('APP_URL')  ? APP_URL  : 'http://yourdomain.com';
  $dbHost     = defined('DB_HOST')  ? DB_HOST  : 'localhost';
  $dbPort     = defined('DB_PORT')  ? DB_PORT  : '3306';
  $dbName     = defined('DB_NAME')  ? DB_NAME  : '';
  $dbUser     = defined('DB_USER')  ? DB_USER  : '';
  $dbPass     = defined('DB_PASS')  ? DB_PASS  : '';
  ?>

  <?php if (!$cfgExists): ?>
  <div class="err-box">⚠ config.php not found! Create it from config.example.php before proceeding.</div>
  <?php endif; ?>

  <div class="section-title">Current config.php Values</div>
  <div style="background:#1A1A22;border-radius:10px;padding:16px;font-family:monospace;font-size:13px;line-height:1.8">
    <div><span style="color:#5A5A72">APP_URL</span>   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;= <span style="color:#00D1FF"><?= h($appUrl) ?></span></div>
    <div><span style="color:#5A5A72">DB_HOST</span>   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;= <span style="color:#FFB830"><?= h($dbHost) ?>:<?= h($dbPort) ?></span></div>
    <div><span style="color:#5A5A72">DB_NAME</span>   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;= <span style="color:#FFB830"><?= h($dbName) ?></span></div>
    <div><span style="color:#5A5A72">DB_USER</span>   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;= <span style="color:#FFB830"><?= h($dbUser) ?></span></div>
    <div><span style="color:#5A5A72">DB_PASS</span>   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;= <span style="color:#FFB830"><?= str_repeat('•', min(strlen($dbPass),12)) ?></span></div>
  </div>

  <form method="POST" action="?step=3">
    <div class="section-title" style="margin-top:24px">Super Admin Account</div>
    <label>Admin Name</label>
    <input type="text" name="admin_name" placeholder="Site Administrator" value="<?= h($_SESSION['install_name'] ?? 'Admin') ?>" required/>
    <label>Admin Email</label>
    <input type="email" name="admin_email" placeholder="admin@yourdomain.com" value="<?= h($_SESSION['install_email'] ?? '') ?>" required/>
    <label>Admin Password <span style="color:#5A5A72">(min 8 chars)</span></label>
    <input type="password" name="admin_password" placeholder="Choose a strong password" minlength="8" required/>
    <label>Confirm Password</label>
    <input type="password" name="admin_password2" placeholder="Repeat password" required/>
    <button type="submit" class="btn">Install Database →</button>
  </form>
</div>
</body>
<?php

/* ── STEP 3: RUN INSTALLATION ───────────────────────────── */
elseif ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST'):
    // Store form data
    $adminName  = trim($_POST['admin_name']  ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_password']   ?? '';
    $adminPass2 = $_POST['admin_password2']  ?? '';

    $_SESSION['install_name']  = $adminName;
    $_SESSION['install_email'] = $adminEmail;

    // Validate
    if (!$adminName || !$adminEmail || !$adminPass) {
        header('Location: ?step=2');
        exit;
    }
    if ($adminPass !== $adminPass2) {
        err('Passwords do not match.');
    }
    if (strlen($adminPass) < 8) {
        err('Password must be at least 8 characters.');
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        err('Invalid email address.');
    }

    if (empty($errors)) {
        require_once __DIR__ . '/config.php';

        // Test DB connection
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';port='.DB_PORT.';charset='.DB_CHARSET,
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"]
            );
            ok("Connected to MySQL server");

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
            ok("Database `" . DB_NAME . "` ready");

        } catch (PDOException $e) {
            err("Database connection failed: " . $e->getMessage());
        }

        if (empty($errors)) {
            // ── RUN TABLE MIGRATIONS ─────────────────────────
            $migrations = getMigrations();
            $tableCount = 0;
            foreach ($migrations as $sql) {
                try {
                    $pdo->exec($sql);
                    $tableCount++;
                } catch (PDOException $e) {
                    err("Migration error: " . $e->getMessage() . " — SQL: " . substr($sql, 0, 100));
                }
            }
            ok("Created/verified {$tableCount} tables");

            // ── COLUMN UPGRADES ───────────────────────────────
            $upgrades = getColumnUpgrades();
            $upgraded = 0;
            foreach ($upgrades as $table => $columns) {
                foreach ($columns as [$col, $def]) {
                    // Check if column exists
                    $exists = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'")->rowCount() > 0;
                    if (!$exists) {
                        try {
                            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
                            $upgraded++;
                            inf("Added column {$table}.{$col}");
                        } catch (PDOException $e) {
                            // Ignore if column already exists (race condition)
                            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                                err("Column upgrade {$table}.{$col}: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
            if ($upgraded > 0) ok("Applied {$upgraded} column upgrade(s)");

            // ── SEED DATA ─────────────────────────────────────
            try {
                seedSettings($pdo);
                ok("Platform settings seeded");
                seedPlans($pdo);
                ok("Subscription plans seeded (Free/Silver/Gold/Platinum)");
                seedGateways($pdo);
                ok("Payment gateways seeded");
                seedChannels($pdo);
                ok("Channels/topics seeded");
            } catch (PDOException $e) {
                err("Seed error: " . $e->getMessage());
            }

            // ── CREATE SUPER ADMIN ────────────────────────────
            if (empty($errors)) {
                try {
                    // Check if admin already exists
                    $existing = $pdo->prepare("SELECT id FROM admins WHERE email=?");
                    $existing->execute([$adminEmail]);
                    $existingAdmin = $existing->fetch();

                    $hashedPass = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);

                    if ($existingAdmin) {
                        // Update existing admin
                        $upd = $pdo->prepare("UPDATE admins SET password=?, role='super_admin', status='active' WHERE email=?");
                        $upd->execute([$hashedPass, $adminEmail]);
                        ok("Super admin account updated for: {$adminEmail}");
                    } else {
                        // Create new admin
                        $ins = $pdo->prepare(
                            "INSERT INTO admins (name, email, password, role, status, api_token, created_at)
                             VALUES (?,?,?,'super_admin','active',?,NOW())"
                        );
                        $apiToken = bin2hex(random_bytes(16));
                        $ins->execute([$adminName, $adminEmail, $hashedPass, $apiToken]);
                        ok("Super admin account created: {$adminEmail}");
                    }
                } catch (PDOException $e) {
                    err("Admin creation failed: " . $e->getMessage());
                }
            }

            // ── ENSURE UPLOAD DIRECTORIES ─────────────────────
            $dirs = [
                '/assets/uploads',
                '/assets/uploads/voice',
                '/assets/uploads/status',
                '/assets/uploads/avatars',
                '/assets/uploads/image',
                '/assets/uploads/logo',
                '/assets/uploads/ad',
            ];
            foreach ($dirs as $d) {
                $path = __DIR__ . $d;
                if (!is_dir($path)) {
                    mkdir($path, 0755, true);
                    // Drop index.php to block directory listing
                    file_put_contents($path . '/index.php', '<?php http_response_code(403); die();');
                }
            }
            ok("Upload directories verified");

            // ── SAVE ADMIN CREDENTIALS FOR DISPLAY ────────────
            $_SESSION['install_done']         = true;
            $_SESSION['install_admin_email']  = $adminEmail;
            $_SESSION['install_admin_pass']   = $adminPass;
            $_SESSION['install_app_url']      = defined('APP_URL') ? APP_URL : '';
            $_SESSION['install_errors']       = $errors;
            $_SESSION['install_success']      = $success;
            $_SESSION['install_info']         = $info;

            if (empty($errors)) {
                header('Location: ?step=4');
                exit;
            }
        }
    }
    // Fall through to show errors
    ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Voxu Installer — Error</title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif;background:#0B0B0F;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.card{background:#16161E;border:1px solid rgba(255,255,255,.07);border-radius:20px;padding:40px;width:100%;max-width:600px}.logo{font-size:32px;font-weight:800;margin-bottom:4px}.logo span{color:#6C3BFF}.sub{font-size:13px;color:#A0A0B0;text-transform:uppercase;letter-spacing:.1em;margin-bottom:28px}h2{font-size:20px;font-weight:700;margin-bottom:16px}.err-box{background:rgba(255,68,68,.1);border:1px solid rgba(255,68,68,.4);color:#FF4444;padding:12px;border-radius:8px;font-size:13px;margin-bottom:10px}.btn{display:block;width:100%;padding:14px;background:#6C3BFF;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;text-align:center;margin-top:20px;transition:.2s}</style>
</head><body><div class="card">
<div class="logo">Vo<span>xu</span></div><div class="sub">Installation Wizard</div>
<h2>⚠ Installation Errors</h2>
<?php foreach ($errors as $e): ?><div class="err-box">✗ <?= h($e) ?></div><?php endforeach; ?>
<?php foreach ($success as $s): ?><div style="background:rgba(0,255,156,.1);border:1px solid rgba(0,255,156,.3);color:#00FF9C;padding:12px;border-radius:8px;font-size:13px;margin-bottom:10px">✓ <?= h($s) ?></div><?php endforeach; ?>
<a href="?step=2" class="btn">← Back and Try Again</a>
</div></body></html>
<?php

/* ── STEP 4: SUCCESS ────────────────────────────────────── */
elseif ($step === 4):
    if (empty($_SESSION['install_done'])) {
        header('Location: ?step=1'); exit;
    }
    $savedErrors  = $_SESSION['install_errors']       ?? [];
    $savedSuccess = $_SESSION['install_success']      ?? [];
    $savedInfo    = $_SESSION['install_info']         ?? [];
    $adminEmail   = $_SESSION['install_admin_email']  ?? '';
    $adminPass    = $_SESSION['install_admin_pass']   ?? '';
    $appUrl       = $_SESSION['install_app_url']      ?? '';
    unset($_SESSION['install_done'],$_SESSION['install_admin_pass']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Voxu — Installed!</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif;background:#0B0B0F;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{background:#16161E;border:1px solid rgba(255,255,255,.07);border-radius:20px;padding:40px;width:100%;max-width:640px}
  .logo{font-size:32px;font-weight:800;margin-bottom:4px}.logo span{color:#6C3BFF}
  .sub{font-size:13px;color:#A0A0B0;text-transform:uppercase;letter-spacing:.1em;margin-bottom:28px}
  .steps{display:flex;gap:8px;margin-bottom:28px}
  .step{flex:1;height:4px;background:#6C3BFF;border-radius:2px}
  .icon{width:72px;height:72px;background:rgba(0,255,156,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 20px}
  h2{font-size:24px;font-weight:800;text-align:center;margin-bottom:6px}
  .tagline{text-align:center;font-size:14px;color:#A0A0B0;margin-bottom:24px}
  .log-item{font-size:13px;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04);display:flex;align-items:flex-start;gap:8px}
  .cred-box{background:#1A1A22;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:18px;margin:20px 0}
  .cred-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:14px;border-bottom:1px solid rgba(255,255,255,.04)}
  .cred-row:last-child{border:none}
  .cred-key{color:#A0A0B0}.cred-val{color:#00FF9C;font-weight:700;font-family:monospace}
  .warn-box{background:rgba(255,68,68,.1);border:1px solid rgba(255,68,68,.3);color:#FF4444;padding:14px;border-radius:10px;font-size:13px;margin:16px 0;line-height:1.6}
  .btn{display:block;width:100%;padding:14px;background:#6C3BFF;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;text-align:center;margin-top:10px;transition:.2s}
  .btn:hover{background:#5A30D0}
  .btn-sec{background:transparent;border:1px solid rgba(255,255,255,.12);color:#A0A0B0}
  .btn-sec:hover{background:rgba(255,255,255,.05);color:#fff}
  .divider{height:1px;background:rgba(255,255,255,.06);margin:20px 0}
</style>
</head><body>
<div class="card">
  <div class="logo">Vo<span>xu</span></div>
  <div class="sub">Installation Wizard</div>
  <div class="steps"><div class="step"></div><div class="step"></div><div class="step"></div><div class="step"></div></div>

  <div class="icon">✓</div>
  <h2>Installation Complete!</h2>
  <p class="tagline">Voxu is ready. Your database has been set up successfully.</p>

  <?php if (!empty($savedErrors)): ?>
    <?php foreach ($savedErrors as $e): ?>
      <div style="background:rgba(255,68,68,.1);border:1px solid rgba(255,68,68,.4);color:#FF4444;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:8px">⚠ <?= h($e) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div style="max-height:200px;overflow-y:auto;margin-bottom:16px">
    <?php foreach ($savedSuccess as $s): ?>
      <div class="log-item"><span style="color:#00FF9C;flex-shrink:0">✓</span> <?= h($s) ?></div>
    <?php endforeach; ?>
    <?php foreach ($savedInfo as $i): ?>
      <div class="log-item"><span style="color:#00D1FF;flex-shrink:0">→</span> <?= h($i) ?></div>
    <?php endforeach; ?>
  </div>

  <div class="cred-box">
    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#5A5A72;margin-bottom:10px">Your Admin Credentials</div>
    <div class="cred-row"><span class="cred-key">Admin URL</span>     <span class="cred-val"><?= h($appUrl) ?>/admin/</span></div>
    <div class="cred-row"><span class="cred-key">Email</span>         <span class="cred-val"><?= h($adminEmail) ?></span></div>
    <div class="cred-row"><span class="cred-key">Password</span>      <span class="cred-val"><?= h($adminPass) ?></span></div>
  </div>

  <div class="warn-box">
    <strong>🔒 IMPORTANT — Do these immediately:</strong><br/>
    1. <strong>DELETE install.php</strong> from your server right now<br/>
    2. Save your admin credentials in a password manager<br/>
    3. Visit Admin → Settings to configure your app name, support email, and currency<br/>
    4. Visit Admin → Subscriptions to review plan pricing<br/>
    5. Visit Admin → Pages to update your About Us page content
  </div>

  <div class="divider"></div>

  <a href="<?= h($appUrl) ?>/admin/" class="btn">🚀 Go to Admin Panel →</a>
  <a href="<?= h($appUrl) ?>/" class="btn btn-sec">← View Site</a>
</div>
</body></html>
<?php
else:
    header('Location: ?step=1'); exit;
endif;
