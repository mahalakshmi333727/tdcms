-- =====================================================================
-- Migration: add category + tags to assignments
-- Run this ONLY if you already installed TDCMS before this change.
-- Fresh installs get these columns automatically from sql/schema.sql.
-- =====================================================================

USE `tdcms`;

ALTER TABLE `assignments`
    ADD COLUMN `category` VARCHAR(100) NULL DEFAULT NULL AFTER `title`,
    ADD COLUMN `tags`     VARCHAR(500) NULL DEFAULT NULL AFTER `reference_link`,
    ADD KEY `idx_assignments_category` (`category`);
