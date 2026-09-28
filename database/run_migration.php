<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "Running LostLink Messaging Migration...\n";

try {
    // 1. Create contact_requests table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `contact_requests` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `request_code` CHAR(13) NOT NULL UNIQUE COMMENT 'Public Request ID (e.g. REQ-840512937)',
            `item_id` BIGINT UNSIGNED NOT NULL COMMENT 'References items.id',
            `sender_id` BIGINT UNSIGNED NOT NULL COMMENT 'References users.id (the user contacting)',
            `reporter_id` BIGINT UNSIGNED NOT NULL COMMENT 'References users.id (the item reporter)',
            `initial_message` TEXT NOT NULL COMMENT 'Initial inquiry message',
            `status` ENUM('pending', 'accepted', 'rejected', 'closed') NOT NULL DEFAULT 'pending' COMMENT 'Status of request',
            `rejection_reason` TEXT DEFAULT NULL COMMENT 'Optional rejection reason',
            `responded_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when reporter accepted or rejected',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_cr_item` (`item_id`),
            INDEX `idx_cr_sender` (`sender_id`),
            INDEX `idx_cr_reporter` (`reporter_id`),
            INDEX `idx_cr_status` (`status`),
            INDEX `idx_cr_created` (`created_at`),
            CONSTRAINT `fk_contact_requests_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `fk_contact_requests_sender` FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
            CONSTRAINT `fk_contact_requests_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[OK] contact_requests table created or exists.\n";

    // 2. Create conversations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `conversations` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `conversation_code` CHAR(13) NOT NULL UNIQUE COMMENT 'Public Conversation ID (e.g. CNV-582903741)',
            `contact_request_id` BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'References contact_requests.id',
            `item_id` BIGINT UNSIGNED NOT NULL COMMENT 'References items.id',
            `participant_one_id` BIGINT UNSIGNED NOT NULL COMMENT 'References users.id (initial sender)',
            `participant_two_id` BIGINT UNSIGNED NOT NULL COMMENT 'References users.id (item reporter)',
            `status` ENUM('active', 'closed') NOT NULL DEFAULT 'active' COMMENT 'active or closed',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_conv_item` (`item_id`),
            INDEX `idx_conv_p1` (`participant_one_id`),
            INDEX `idx_conv_p2` (`participant_two_id`),
            INDEX `idx_conv_status` (`status`),
            INDEX `idx_conv_updated` (`updated_at`),
            CONSTRAINT `fk_conversations_request` FOREIGN KEY (`contact_request_id`) REFERENCES `contact_requests`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `fk_conversations_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `fk_conversations_p1` FOREIGN KEY (`participant_one_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
            CONSTRAINT `fk_conversations_p2` FOREIGN KEY (`participant_two_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[OK] conversations table created or exists.\n";

    // 3. Create messages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `messages` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `message_code` CHAR(13) NOT NULL UNIQUE COMMENT 'Public Message ID (e.g. MSG-274910385)',
            `conversation_id` BIGINT UNSIGNED NOT NULL COMMENT 'References conversations.id',
            `sender_id` BIGINT UNSIGNED NOT NULL COMMENT 'References users.id',
            `message` TEXT NOT NULL COMMENT 'Message text',
            `is_read` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = Unread, 1 = Read',
            `read_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when read by recipient',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_msg_conv` (`conversation_id`),
            INDEX `idx_msg_sender` (`sender_id`),
            INDEX `idx_msg_read` (`is_read`),
            INDEX `idx_msg_created` (`created_at`),
            CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[OK] messages table created or exists.\n";

    // 4. Update notifications table category enum
    $pdo->exec("
        ALTER TABLE `notifications`
        MODIFY COLUMN `category` ENUM('account','report','claim','admin','system','contact') NOT NULL;
    ");
    echo "[OK] notifications table category enum updated.\n";

    // 5. Add contact_request_id and conversation_id to notifications if they don't exist
    $notifColsStmt = $pdo->query("SHOW COLUMNS FROM `notifications`");
    $notifCols = $notifColsStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('contact_request_id', $notifCols, true)) {
        $pdo->exec("
            ALTER TABLE `notifications`
            ADD COLUMN `contact_request_id` BIGINT UNSIGNED DEFAULT NULL AFTER `claim_id`,
            ADD CONSTRAINT `fk_notifications_contact_request` FOREIGN KEY (`contact_request_id`) REFERENCES `contact_requests`(`id`) ON UPDATE CASCADE ON DELETE SET NULL;
        ");
        echo "[OK] Added contact_request_id column to notifications.\n";
    }

    if (!in_array('conversation_id', $notifCols, true)) {
        $pdo->exec("
            ALTER TABLE `notifications`
            ADD COLUMN `conversation_id` BIGINT UNSIGNED DEFAULT NULL AFTER `contact_request_id`,
            ADD CONSTRAINT `fk_notifications_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON UPDATE CASCADE ON DELETE SET NULL;
        ");
        echo "[OK] Added conversation_id column to notifications.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Throwable $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
