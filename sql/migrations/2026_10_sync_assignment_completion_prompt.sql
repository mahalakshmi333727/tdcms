ALTER TABLE `assignments`
    ADD COLUMN `completion_prompt_pending` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `status`;

-- Restore unresolved prompts for assignments that had already reached the
-- completion step before this migration was installed.
UPDATE `assignments`
SET `completion_prompt_pending` = 1
WHERE `status` = 'pending'
  AND `text_copied` = 1
  AND (`reference_link` IS NULL OR `reference_link` = '' OR `link_clicked` = 1);
