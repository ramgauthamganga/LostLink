<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

try {
    echo "Running blocking migration...\n";

    // Check if column already exists
    $cols = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'blocked_by_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("
            ALTER TABLE conversations
            ADD COLUMN blocked_by_id BIGINT UNSIGNED DEFAULT NULL AFTER participant_two_id,
            ADD COLUMN blocked_at TIMESTAMP NULL DEFAULT NULL AFTER blocked_by_id
        ");
        $pdo->exec("
            ALTER TABLE conversations
            ADD CONSTRAINT fk_conversations_blocked_by
            FOREIGN KEY (blocked_by_id)
            REFERENCES users(id)
            ON UPDATE CASCADE
            ON DELETE SET NULL
        ");
        echo "Added blocked_by_id and blocked_at columns to conversations.\n";
    } else {
        echo "conversations.blocked_by_id already exists.\n";
    }

    // Create blocked_users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blocked_users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            blocker_id BIGINT UNSIGNED NOT NULL COMMENT 'User who initiated block',
            blocked_id BIGINT UNSIGNED NOT NULL COMMENT 'User who was blocked',
            conversation_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Optional originating conversation',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY uq_blocker_blocked (blocker_id, blocked_id),
            INDEX idx_blocked_blocker (blocker_id),
            INDEX idx_blocked_target (blocked_id),

            CONSTRAINT fk_bu_blocker FOREIGN KEY (blocker_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT fk_bu_blocked FOREIGN KEY (blocked_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT fk_bu_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON UPDATE CASCADE ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "created/verified blocked_users table.\n";

    echo "Migration completed successfully!\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
