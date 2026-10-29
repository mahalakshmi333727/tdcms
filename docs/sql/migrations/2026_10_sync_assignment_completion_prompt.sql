ALTER TABLE `assignments`
    ADD COLUMN `completion_prompt_pending` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `status`;

UPDATE `assignments`
SET `completion_prompt_pending` = 1
WHERE `status` = 'pending'
  AND `text_copied` = 1
  AND (`reference_link` IS NULL OR `reference_link` = '' OR `link_clicked` = 1);
