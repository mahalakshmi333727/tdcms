-- =====================================================================
-- Migration: ensure ip_address columns can hold a full IPv4/IPv6 value
-- Run this ONLY if you already installed TDCMS before this change.
-- Fresh installs already get VARCHAR(45) from sql/schema.sql.
--
-- VARCHAR(45) is the standard safe length for an IP address column:
-- it fits the longest possible IPv6 literal, including an IPv4-mapped
-- IPv6 form such as "0000:0000:0000:0000:0000:ffff:255.255.255.255"
-- (45 characters). This does NOT truncate normal addresses like
-- "127.0.0.1", "::1", or "49.47.218.207" — it simply guarantees the
-- column is wide enough for every valid case, including the longest one.
-- =====================================================================

USE `tdcms`;

ALTER TABLE `activity_logs`
    MODIFY COLUMN `ip_address` VARCHAR(45) NULL DEFAULT NULL;

ALTER TABLE `login_logs`
    MODIFY COLUMN `ip_address` VARCHAR(45) NULL DEFAULT NULL;