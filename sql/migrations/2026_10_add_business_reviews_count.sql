ALTER TABLE `businesses`
    ADD COLUMN `reviews_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `name`;

UPDATE `businesses` b
SET b.`reviews_count` = (
    SELECT COUNT(*)
    FROM `business_content` bc
    WHERE bc.`business_id` = b.`id`
      AND bc.`archived_at` IS NULL
);
