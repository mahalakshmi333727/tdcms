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
DROP TABLE IF EXISTS `admin_notifications`;
DROP TABLE IF EXISTS `business_content`;
DROP TABLE IF EXISTS `business_imports`;
DROP TABLE IF EXISTS `business_user_history`;
DROP TABLE IF EXISTS `business_users`;
DROP TABLE IF EXISTS `businesses`;
CREATE TABLE `users` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name`        VARCHAR(120)  NOT NULL,
    `username`         VARCHAR(60)   NOT NULL,
    `email`            VARCHAR(150)  NOT NULL,
    `password_hash`    VARCHAR(255)  NULL DEFAULT NULL,
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
    `business_content_id` INT UNSIGNED NULL DEFAULT NULL,
    `text_copied`      TINYINT(1) NOT NULL DEFAULT 0,
    `link_clicked`     TINYINT(1) NOT NULL DEFAULT 0,
    `status`           ENUM('pending','completed') NOT NULL DEFAULT 'pending',
    `completion_prompt_pending` TINYINT(1) NOT NULL DEFAULT 0,
    `archived_at`      DATETIME NULL DEFAULT NULL,
    `assigned_by`      INT UNSIGNED NOT NULL,
    `assigned_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `due_at`           DATETIME NULL DEFAULT NULL,
    `completed_at`     DATETIME NULL DEFAULT NULL,
    `ip_on_completion` VARCHAR(45) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_assignments_user_status` (`user_id`, `status`),
    KEY `idx_assignments_assigned_by` (`assigned_by`),
    KEY `idx_assignments_category` (`category`),
    KEY `idx_assignments_overdue_lookup` (`status`, `archived_at`, `due_at`),
    UNIQUE KEY `uq_assignments_business_content` (`business_content_id`),
    CONSTRAINT `fk_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assignments_admin` FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: admin_notifications
-- ---------------------------------------------------------------------
CREATE TABLE `admin_notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assignment_id` INT UNSIGNED NULL DEFAULT NULL,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `user_name` VARCHAR(120) NOT NULL,
    `assignment_title` VARCHAR(150) NULL DEFAULT NULL,
    `assigned_at` DATETIME NOT NULL,
    `deadline_at` DATETIME NOT NULL,
    `read_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_notifications_assignment` (`assignment_id`),
    KEY `idx_admin_notifications_unread` (`read_at`, `created_at`),
    CONSTRAINT `fk_admin_notifications_assignment`
        FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_admin_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: excel_assignments
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `excel_assignments`;
CREATE TABLE `excel_assignments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NULL DEFAULT NULL,
    `original_file_name` VARCHAR(255) NOT NULL,
    `stored_file_path` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(10) NOT NULL,
    `status` ENUM('pending','completed') NOT NULL DEFAULT 'pending',
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_excel_assignments_user_status` (`user_id`, `status`),
    KEY `idx_excel_assignments_created_by` (`created_by`),
    CONSTRAINT `fk_excel_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_excel_assignments_admin` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: excel_assignment_comments
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `excel_assignment_comments`;
CREATE TABLE `excel_assignment_comments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assignment_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `comment_text` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_excel_assignment_comments_assignment` (`assignment_id`),
    KEY `idx_excel_assignment_comments_user` (`user_id`),
    CONSTRAINT `fk_excel_assignment_comments_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `excel_assignments`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_excel_assignment_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
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

CREATE TABLE `businesses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `reviews_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `category` VARCHAR(100) NOT NULL,
    `reference_link` VARCHAR(500) NULL DEFAULT NULL,
    `daily_assignment_limit` INT UNSIGNED NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_businesses_name` (`name`),
    CONSTRAINT `fk_businesses_admin`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `business_users` (
    `business_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`business_id`, `user_id`),
    UNIQUE KEY `uq_business_users_business` (`business_id`),
    KEY `idx_business_users_user` (`user_id`),
    CONSTRAINT `fk_business_users_business`
        FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_business_users_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `business_user_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `business_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME NOT NULL,
    `ended_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `changed_by` INT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_business_user_history_business` (`business_id`, `ended_at`),
    KEY `idx_business_user_history_user` (`user_id`, `assigned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `business_imports` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `business_id` INT UNSIGNED NOT NULL,
    `imported_by` INT UNSIGNED NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `imported_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `rejected_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `failure_summary` MEDIUMTEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_business_imports_business_created` (`business_id`, `created_at`),
    CONSTRAINT `fk_business_imports_business`
        FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_business_imports_admin`
        FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `business_content` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `business_id` INT UNSIGNED NOT NULL,
    `import_id` INT UNSIGNED NULL DEFAULT NULL,
    `content_hash` CHAR(64) NOT NULL,
    `source_record` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `text_content` TEXT NOT NULL,
    `reference_link` VARCHAR(500) NULL DEFAULT NULL,
    `archived_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_business_content_hash` (`business_id`, `content_hash`),
    KEY `idx_business_content_business_archived` (`business_id`, `archived_at`),
    KEY `idx_business_content_import` (`import_id`),
    CONSTRAINT `fk_business_content_business`
        FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_business_content_import`
        FOREIGN KEY (`import_id`) REFERENCES `business_imports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `assignments`
    ADD CONSTRAINT `fk_assignments_business_content`
        FOREIGN KEY (`business_content_id`) REFERENCES `business_content` (`id`) ON DELETE RESTRICT;

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
