CREATE TABLE `businesses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
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
    KEY `idx_business_users_user` (`user_id`),
    CONSTRAINT `fk_business_users_business`
        FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_business_users_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
    UNIQUE KEY `uq_business_content_hash` (`business_id`, `content_hash`),
    KEY `idx_business_content_business_archived` (`business_id`, `archived_at`),
    KEY `idx_business_content_import` (`import_id`),
    CONSTRAINT `fk_business_content_business`
        FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_business_content_import`
        FOREIGN KEY (`import_id`) REFERENCES `business_imports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `assignments`
    ADD COLUMN `business_content_id` INT UNSIGNED NULL DEFAULT NULL AFTER `tags`,
    ADD UNIQUE KEY `uq_assignments_business_content` (`business_content_id`),
    ADD CONSTRAINT `fk_assignments_business_content`
        FOREIGN KEY (`business_content_id`) REFERENCES `business_content` (`id`) ON DELETE RESTRICT;
