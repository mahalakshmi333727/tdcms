-- Migration: Add excel_assignments table for permanent Excel assignment storage

CREATE TABLE IF NOT EXISTS `excel_assignments` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED NOT NULL,
    `title`            VARCHAR(150) NULL DEFAULT NULL,
    `original_file_name` VARCHAR(255) NOT NULL,
    `stored_file_path` VARCHAR(255) NOT NULL,
    `file_type`        VARCHAR(10) NOT NULL,
    `status`           ENUM('pending','completed') NOT NULL DEFAULT 'pending',
    `assigned_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by`       INT UNSIGNED NOT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_excel_assignments_user_status` (`user_id`, `status`),
    KEY `idx_excel_assignments_created_by` (`created_by`),
    CONSTRAINT `fk_excel_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_excel_assignments_admin` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `excel_assignment_comments`;
CREATE TABLE IF NOT EXISTS `excel_assignment_comments` (
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
