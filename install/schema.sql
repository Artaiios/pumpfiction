-- Pumpfiction (Tracking Edition) – Database Schema
-- Version 1.0.0

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nickname` VARCHAR(20) NOT NULL UNIQUE,
    `pin_hash` VARCHAR(255) NOT NULL,
    `avatar` VARCHAR(50) NOT NULL DEFAULT 'bear',
    `xp` INT UNSIGNED NOT NULL DEFAULT 0,
    `level` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `current_streak` INT UNSIGNED NOT NULL DEFAULT 0,
    `longest_streak` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_streak_date` DATE DEFAULT NULL,
    `cookie_token` VARCHAR(64) DEFAULT NULL,
    `has_seen_intro` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME DEFAULT NULL,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_cookie` (`cookie_token`),
    INDEX `idx_active` (`is_deleted`, `last_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CHALLENGES
-- ============================================================
CREATE TABLE IF NOT EXISTS `challenges` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('number','yesno') NOT NULL,
    `unit` VARCHAR(30) DEFAULT NULL,
    `daily_target` DECIMAL(10,2) NOT NULL,
    `icon` VARCHAR(50) DEFAULT '🏋️',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CHALLENGE LOGS (daily entries)
-- ============================================================
CREATE TABLE IF NOT EXISTS `challenge_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `challenge_id` INT UNSIGNED NOT NULL,
    `log_date` DATE NOT NULL,
    `value` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_challenge_date` (`user_id`, `challenge_id`, `log_date`),
    INDEX `idx_date` (`log_date`),
    INDEX `idx_user_date` (`user_id`, `log_date`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`challenge_id`) REFERENCES `challenges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER MILESTONES (personal goals)
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_milestones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `challenge_id` INT UNSIGNED NOT NULL,
    `target_value` DECIMAL(12,2) NOT NULL,
    `description` VARCHAR(200) DEFAULT NULL,
    `is_reached` TINYINT(1) NOT NULL DEFAULT 0,
    `reached_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`, `is_reached`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`challenge_id`) REFERENCES `challenges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BADGES
-- ============================================================
CREATE TABLE IF NOT EXISTS `badges` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(60) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `icon` VARCHAR(10) NOT NULL DEFAULT '🏅',
    `condition_type` VARCHAR(50) NOT NULL,
    `condition_value` DECIMAL(12,2) DEFAULT NULL,
    `condition_extra` VARCHAR(100) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER BADGES
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_badges` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `badge_id` INT UNSIGNED NOT NULL,
    `unlocked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_badge` (`user_id`, `badge_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`badge_id`) REFERENCES `badges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VOTING: NEW CHALLENGE PROPOSALS
-- ============================================================
CREATE TABLE IF NOT EXISTS `voting_proposals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proposed_by` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('number','yesno') NOT NULL,
    `unit` VARCHAR(30) DEFAULT NULL,
    `daily_target` DECIMAL(10,2) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `vote_month` TINYINT UNSIGNED NOT NULL,
    `vote_year` SMALLINT UNSIGNED NOT NULL,
    `status` ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_period` (`vote_year`, `vote_month`, `status`),
    FOREIGN KEY (`proposed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VOTING: CHALLENGE KEEP/REMOVE VOTES
-- ============================================================
CREATE TABLE IF NOT EXISTS `voting_challenge_votes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `challenge_id` INT UNSIGNED NOT NULL,
    `vote` TINYINT NOT NULL COMMENT '1=keep, -1=remove',
    `vote_month` TINYINT UNSIGNED NOT NULL,
    `vote_year` SMALLINT UNSIGNED NOT NULL,
    `final_vote_used` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_challenge_period` (`user_id`, `challenge_id`, `vote_year`, `vote_month`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`challenge_id`) REFERENCES `challenges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VOTING: TARGET ADJUSTMENT VOTES
-- ============================================================
CREATE TABLE IF NOT EXISTS `voting_target_votes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `challenge_id` INT UNSIGNED NOT NULL,
    `proposed_target` DECIMAL(10,2) NOT NULL,
    `vote_month` TINYINT UNSIGNED NOT NULL,
    `vote_year` SMALLINT UNSIGNED NOT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_challenge_target` (`user_id`, `challenge_id`, `vote_year`, `vote_month`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`challenge_id`) REFERENCES `challenges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VOTING: PROPOSAL VOTES
-- ============================================================
CREATE TABLE IF NOT EXISTS `voting_proposal_votes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `proposal_id` INT UNSIGNED NOT NULL,
    `vote` TINYINT NOT NULL COMMENT '1=yes, -1=no',
    `final_vote_used` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_proposal` (`user_id`, `proposal_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`proposal_id`) REFERENCES `voting_proposals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WALL OF SHAME & FAME
-- ============================================================
CREATE TABLE IF NOT EXISTS `wall_entries` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `entry_type` ENUM('fame','shame','system') NOT NULL,
    `message` VARCHAR(500) NOT NULL,
    `icon` VARCHAR(10) DEFAULT '📢',
    `related_badge_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_type_date` (`entry_type`, `created_at`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MOTIVATION QUOTES
-- ============================================================
CREATE TABLE IF NOT EXISTS `motivation_quotes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quote_text` TEXT NOT NULL,
    `context_type` ENUM('streak','no_streak','behind','leading','new_user','general','perfect_day','comeback') NOT NULL DEFAULT 'general',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    INDEX `idx_context` (`context_type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER PRIVATE CHALLENGES
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_private_challenges` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `challenge_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_priv` (`user_id`, `challenge_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`challenge_id`) REFERENCES `challenges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- XP LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS `xp_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `amount` INT NOT NULL,
    `reason` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`, `created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WEEKLY WINNERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `weekly_winners` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `week_start` DATE NOT NULL,
    `week_end` DATE NOT NULL,
    `xp_earned` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_week` (`week_start`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
