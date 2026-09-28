-- =========================================================
-- LostLink Migration: Announcements System
-- =========================================================
-- Project : LostLink
-- Database: lostlink
-- Engine  : MySQL
-- Charset : utf8mb4
-- Collation: utf8mb4_unicode_ci
-- =========================================================

CREATE TABLE IF NOT EXISTS `announcements` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `announcement_code` CHAR(13) NOT NULL UNIQUE COMMENT 'Public Announcement ID (e.g. ANC-840512937)',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'References users.id (admin author)',
    `title` VARCHAR(200) NOT NULL COMMENT 'Announcement title',
    `content` TEXT NOT NULL COMMENT 'Full announcement text content',
    `category` ENUM('college', 'lostlink', 'system', 'event', 'important') NOT NULL DEFAULT 'lostlink',
    `priority` ENUM('normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = Normal, 1 = Pinned',
    `status` ENUM('draft', 'published', 'scheduled', 'archived') NOT NULL DEFAULT 'published',
    `scheduled_at` DATETIME NULL DEFAULT NULL COMMENT 'Target publish time for scheduled announcements',
    `expires_at` DATETIME NULL DEFAULT NULL COMMENT 'Optional expiration time',
    `published_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Actual publication timestamp',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_anc_user` (`user_id`),
    INDEX `idx_anc_status` (`status`),
    INDEX `idx_anc_pinned` (`is_pinned`),
    INDEX `idx_anc_priority` (`priority`),
    INDEX `idx_anc_published` (`published_at`),
    INDEX `idx_anc_scheduled` (`scheduled_at`),
    INDEX `idx_anc_expires` (`expires_at`),

    CONSTRAINT `fk_announcements_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
