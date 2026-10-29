ALTER TABLE `assignments`
    ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `completion_prompt_pending`,
    ADD KEY `idx_assignments_archived_at` (`archived_at`);
