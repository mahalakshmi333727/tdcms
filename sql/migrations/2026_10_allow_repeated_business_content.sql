ALTER TABLE `business_content`
    DROP INDEX `uq_business_content_hash`,
    ADD KEY `idx_business_content_hash` (`business_id`, `content_hash`);
