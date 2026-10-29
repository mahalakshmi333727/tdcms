-- =====================================================================
-- Text Distribution & Copy Management System (TDCMS)
-- Database Schema | MySQL 8+
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `tdcms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tdcms`;

-- ---------------------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name`        VARCHAR(120)  NOT NULL,
    `username`         VARCHAR(60)   NOT NULL,
    `email`            VARCHAR(150)  NOT NULL,
    `password_hash`    VARCHAR(255)  NOT NULL,
    `role`             ENUM('admin','user') NOT NULL DEFAULT 'user',
    `status`           ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `failed_attempts`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`     DATETIME NULL DEFAULT NULL,
    `last_login_at`    DATETIME NULL DEFAULT NULL,
    `password_changed_at` DATETIME NULL DEFAULT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: assignments  (the distributed text / links)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `assignments`;
CREATE TABLE `assignments` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED NOT NULL,
    `title`            VARCHAR(150) NULL DEFAULT NULL,
    `category`         VARCHAR(100) NULL DEFAULT NULL,
    `text_content`     TEXT NOT NULL,
    `reference_link`   VARCHAR(500) NULL DEFAULT NULL,
    `tags`             VARCHAR(500) NULL DEFAULT NULL,
    `text_copied`      TINYINT(1) NOT NULL DEFAULT 0,
    `link_clicked`     TINYINT(1) NOT NULL DEFAULT 0,
    `status`           ENUM('pending','completed') NOT NULL DEFAULT 'pending',
    `completion_prompt_pending` TINYINT(1) NOT NULL DEFAULT 0,
    `archived_at`      DATETIME NULL DEFAULT NULL,
    `assigned_by`      INT UNSIGNED NOT NULL,
    `assigned_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at`     DATETIME NULL DEFAULT NULL,
    `ip_on_completion` VARCHAR(45) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_assignments_user_status` (`user_id`, `status`),
    KEY `idx_assignments_assigned_by` (`assigned_by`),
    KEY `idx_assignments_category` (`category`),
    KEY `idx_assignments_archived_at` (`archived_at`),
    CONSTRAINT `fk_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assignments_admin` FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: activity_logs (admin + system actions)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NULL DEFAULT NULL,
    `action`      VARCHAR(80) NOT NULL,
    `description` VARCHAR(500) NULL DEFAULT NULL,
    `ip_address`  VARCHAR(45) NULL DEFAULT NULL,
    `user_agent`  VARCHAR(255) NULL DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_user` (`user_id`),
    KEY `idx_activity_action` (`action`),
    KEY `idx_activity_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: login_logs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE `login_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NULL DEFAULT NULL,
    `username`    VARCHAR(60) NOT NULL,
    `status`      ENUM('success','failed','locked') NOT NULL,
    `ip_address`  VARCHAR(45) NULL DEFAULT NULL,
    `user_agent`  VARCHAR(255) NULL DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_logs_user` (`user_id`),
    KEY `idx_login_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: rate_limits (generic throttling for login/ajax endpoints)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bucket_key`  VARCHAR(150) NOT NULL,
    `hits`        INT UNSIGNED NOT NULL DEFAULT 1,
    `window_start` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rate_bucket` (`bucket_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Seed data: default administrator
-- Username: admin | Password: Admin@12345  (CHANGE IMMEDIATELY AFTER INSTALL)
-- Hash generated with PHP password_hash() using PASSWORD_DEFAULT (bcrypt)
-- ---------------------------------------------------------------------
INSERT INTO `users` (`full_name`, `username`, `email`, `password_hash`, `role`, `status`, `password_changed_at`)
VALUES (
    'System Administrator',
    'admin',
    'admin@example.com',
    '$2y$10$mF2f8Q7d3l9m0uQO0v1H1uJt1H3vJm5wKq2H3q0m2wKq2H3q0m2wK', -- placeholder, regenerate on install (see INSTALL.md)
    'admin',
    'active',
    NOW()
);
