-- ==============================================================================
-- LOSTLINK BLOCKING SYSTEM MIGRATION
-- ==============================================================================
-- Adds blocking support to conversations and creates blocked_users table.

USE lostlink;

-- 1. Add blocked_by_id and blocked_at to conversations
ALTER TABLE conversations
ADD COLUMN blocked_by_id BIGINT UNSIGNED DEFAULT NULL AFTER participant_two_id,
ADD COLUMN blocked_at TIMESTAMP NULL DEFAULT NULL AFTER blocked_by_id;

ALTER TABLE conversations
ADD CONSTRAINT fk_conversations_blocked_by
FOREIGN KEY (blocked_by_id)
REFERENCES users(id)
ON UPDATE CASCADE
ON DELETE SET NULL;

-- 2. Create blocked_users table for persistent cross-user blocking
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
