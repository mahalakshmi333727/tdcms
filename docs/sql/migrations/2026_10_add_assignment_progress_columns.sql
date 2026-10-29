ALTER TABLE `assignments`
    ADD COLUMN `text_copied` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tags`,
    ADD COLUMN `link_clicked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `text_copied`;
