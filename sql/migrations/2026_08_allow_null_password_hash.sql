-- Allow NULL password_hash for users created without a password
-- This migration allows administrators to create user accounts without setting an initial password
-- Users with NULL password_hash cannot log in until an admin sets their password via password reset

ALTER TABLE `users` 
MODIFY COLUMN `password_hash` VARCHAR(255) NULL DEFAULT NULL;
