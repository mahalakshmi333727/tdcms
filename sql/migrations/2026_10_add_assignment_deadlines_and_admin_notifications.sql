ALTER TABLE `assignments`
    ADD COLUMN `due_at` DATETIME NULL DEFAULT NULL AFTER `assigned_at`,
    ADD KEY `idx_assignments_overdue_lookup` (`status`, `archived_at`, `due_at`);

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
