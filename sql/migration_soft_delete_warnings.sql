-- =====================================================================
-- Migration: Soft delete + user warnings
-- Target: MariaDB 10.4+ (uppp database)
-- Safe to run once. Adds `deleted_at` columns and a `warnings` table.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Soft-delete columns. A row is considered "deleted" when deleted_at
-- IS NOT NULL. All read queries must filter `deleted_at IS NULL`.
-- ---------------------------------------------------------------------
ALTER TABLE `submissions`
    ADD COLUMN IF NOT EXISTS `deleted_at` datetime DEFAULT NULL AFTER `created_at`,
    ADD KEY IF NOT EXISTS `idx_submissions_deleted_at` (`deleted_at`);

ALTER TABLE `comments`
    ADD COLUMN IF NOT EXISTS `deleted_at` datetime DEFAULT NULL AFTER `created_at`,
    ADD KEY IF NOT EXISTS `idx_comments_deleted_at` (`deleted_at`);

ALTER TABLE `votes`
    ADD COLUMN IF NOT EXISTS `deleted_at` datetime DEFAULT NULL AFTER `created_at`;

ALTER TABLE `categories`
    ADD COLUMN IF NOT EXISTS `deleted_at` datetime DEFAULT NULL;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `deleted_at` datetime DEFAULT NULL AFTER `created_at`,
    ADD KEY IF NOT EXISTS `idx_users_deleted_at` (`deleted_at`);

ALTER TABLE `reports`
    ADD COLUMN IF NOT EXISTS `deleted_at` datetime DEFAULT NULL AFTER `created_at`;

-- ---------------------------------------------------------------------
-- Helpful composite indexes for the common "approved + not deleted"
-- listing queries.
-- ---------------------------------------------------------------------
ALTER TABLE `submissions`
    ADD KEY IF NOT EXISTS `idx_submissions_status_deleted` (`status`, `deleted_at`);

-- ---------------------------------------------------------------------
-- User warnings. Admins issue warnings before banning. Warnings are
-- shown to the user until acknowledged. Warnings themselves are
-- soft-deletable (deleted_at).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `warnings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `issued_by` int(11) DEFAULT NULL,
    `reason` text NOT NULL,
    `acknowledged_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `deleted_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_warnings_user` (`user_id`),
    KEY `idx_warnings_issued_by` (`issued_by`),
    CONSTRAINT `warnings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `warnings_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
