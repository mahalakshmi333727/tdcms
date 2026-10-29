CREATE TABLE IF NOT EXISTS `business_user_history` (
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

CREATE TEMPORARY TABLE `migration_current_business_assignees` (
    `business_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME NOT NULL,
    PRIMARY KEY (`business_id`)
) ENGINE=Memory;

INSERT INTO `migration_current_business_assignees` (`business_id`, `user_id`, `assigned_at`)
SELECT bu.`business_id`, bu.`user_id`, bu.`created_at`
FROM `business_users` bu
WHERE NOT EXISTS (
    SELECT 1
    FROM `business_users` newer
    WHERE newer.`business_id` = bu.`business_id`
      AND (
          newer.`created_at` > bu.`created_at`
          OR (newer.`created_at` = bu.`created_at` AND newer.`user_id` > bu.`user_id`)
      )
);

INSERT INTO `business_user_history` (`business_id`, `user_id`, `assigned_at`, `ended_at`)
SELECT previous.`business_id`, previous.`user_id`, previous.`created_at`, CURRENT_TIMESTAMP
FROM `business_users` previous
JOIN `migration_current_business_assignees` current_assignee
  ON current_assignee.`business_id` = previous.`business_id`
WHERE previous.`user_id` <> current_assignee.`user_id`;

UPDATE `assignments` a
JOIN `business_content` bc ON bc.`id` = a.`business_content_id`
JOIN `migration_current_business_assignees` current_assignee
  ON current_assignee.`business_id` = bc.`business_id`
SET a.`user_id` = current_assignee.`user_id`,
    a.`text_copied` = 0,
    a.`link_clicked` = 0,
    a.`completion_prompt_pending` = 0
WHERE a.`status` = 'pending' AND a.`archived_at` IS NULL
  AND a.`user_id` <> current_assignee.`user_id`;

UPDATE `admin_notifications` n
JOIN `assignments` a ON a.`id` = n.`assignment_id`
JOIN `business_content` bc ON bc.`id` = a.`business_content_id`
JOIN `migration_current_business_assignees` current_assignee
  ON current_assignee.`business_id` = bc.`business_id`
JOIN `users` u ON u.`id` = current_assignee.`user_id`
SET n.`user_id` = current_assignee.`user_id`,
    n.`user_name` = u.`full_name`
WHERE a.`status` = 'pending' AND a.`archived_at` IS NULL;

DELETE previous
FROM `business_users` previous
JOIN `migration_current_business_assignees` current_assignee
  ON current_assignee.`business_id` = previous.`business_id`
WHERE previous.`user_id` <> current_assignee.`user_id`;

DROP TEMPORARY TABLE `migration_current_business_assignees`;

ALTER TABLE `business_users`
    ADD UNIQUE KEY `uq_business_users_business` (`business_id`);
