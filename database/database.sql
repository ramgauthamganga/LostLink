-- =========================================================
-- LostLink Database Schema
-- =========================================================
-- Project : LostLink
-- Database: lostlink
-- Engine  : MySQL
-- Charset : utf8mb4
-- Collation: utf8mb4_unicode_ci
-- =========================================================

CREATE DATABASE IF NOT EXISTS lostlink
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE lostlink;

-- =========================================================
-- Table: users
-- Purpose:
-- Stores all registered users including students and
-- administrators.
-- =========================================================

CREATE TABLE users (

    -- Internal Identifier
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Public Identifier
    user_code CHAR(13) NOT NULL UNIQUE COMMENT 'Public User ID (e.g. USR-840512937)',

    -- Personal Information
    name VARCHAR(150) NOT NULL,
    student_id VARCHAR(20) NOT NULL UNIQUE COMMENT 'Unique college student ID',
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,

    -- Academic Information
    department VARCHAR(100) NOT NULL COMMENT 'Academic department',

    -- Contact Information
    phone VARCHAR(15) NOT NULL,
    profile_image VARCHAR(255)
        NOT NULL
        DEFAULT 'default-profile.png',

    -- Account Information
    role ENUM('student','admin')
        NOT NULL
        DEFAULT 'student'
        COMMENT 'student or admin',

    is_banned TINYINT(1)
        NOT NULL
        DEFAULT 0
        COMMENT '0 = Active, 1 = Banned',

    last_login TIMESTAMP
    NULL
    DEFAULT NULL
    COMMENT 'Last successful login',

    -- Timestamps
    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_student_id (student_id),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_banned (is_banned)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Table: items
-- Purpose:
-- Stores all Lost and Found items reported by users.
-- =========================================================

CREATE TABLE items (

    -- Internal Identifier
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Public Identifier
    item_code CHAR(13)
        NOT NULL
        UNIQUE
        COMMENT 'Public Item ID (e.g. ITM-582903741)',

    -- Reporter Information
    user_id BIGINT UNSIGNED
        NOT NULL
        COMMENT 'References users.id',

    -- Item Information
    title VARCHAR(150)
        NOT NULL
        COMMENT 'Short title of the reported item',

    description TEXT
        NOT NULL
        COMMENT 'Detailed description of the item',

    category VARCHAR(100)
        NOT NULL
        COMMENT 'Main item category',

    subcategory VARCHAR(100)
        NOT NULL
        COMMENT 'Item subcategory',

    report_type ENUM('lost','found')
        NOT NULL
        COMMENT 'Indicates whether the report is for a lost or found item',

    event_location VARCHAR(255)
        NOT NULL
        COMMENT 'General location where the item was lost or found',

    event_date DATE
        NOT NULL
        COMMENT 'Date when the item was lost or found',

    additional_note TEXT
        NULL
        COMMENT 'Optional additional information provided by the reporter',

    verification_method ENUM('description','questions')
        NOT NULL
        COMMENT 'Ownership verification method selected by the reporter',

    status ENUM('pending','active','claimed','returned','closed')
        NOT NULL
        DEFAULT 'pending'
        COMMENT 'Current lifecycle status of the item',

    approved_claim_id BIGINT UNSIGNED
        NULL
        COMMENT 'References the approved claim after ownership verification',

    -- Timestamps
    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_title (title),
    INDEX idx_category (category),
    INDEX idx_subcategory (subcategory),
    INDEX idx_report_type (report_type),
    INDEX idx_status (status),
    INDEX idx_event_date (event_date)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Table: item_images
-- Purpose:
-- Stores images uploaded for reported items.
-- Each item can have a maximum of five images.
-- =========================================================

CREATE TABLE item_images (

    -- Internal Identifier
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Public Identifier
    image_code CHAR(13)
        NOT NULL
        UNIQUE
        COMMENT 'Public Image ID (e.g. IMG-274910385)',

    -- Item Reference
    item_id BIGINT UNSIGNED
        NOT NULL
        COMMENT 'References items.id',

    -- Image Information
    image VARCHAR(255)
        NOT NULL
        COMMENT 'Uploaded image filename/path',

        image_order TINYINT UNSIGNED
        NOT NULL
        COMMENT 'Display order of the image (1-5)',

    -- Ensure valid display order
    CONSTRAINT chk_image_order
        CHECK (image_order BETWEEN 1 AND 5),

    -- Timestamps
    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_item_id (item_id),
    INDEX idx_image_order (image_order)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- Table: claims
-- Purpose:
-- Stores ownership claims submitted by users for reported items.
-- =========================================================

CREATE TABLE claims (

    -- Internal Identifier
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Public Identifier
    claim_code CHAR(13)
        NOT NULL
        UNIQUE
        COMMENT 'Public Claim ID (e.g. CLM-284750193)',

    -- References
    item_id BIGINT UNSIGNED
        NOT NULL
        COMMENT 'References items.id',

    claimant_id BIGINT UNSIGNED
        NOT NULL
        COMMENT 'References users.id',

    -- Claim Information
    verification_response JSON
        NOT NULL
        COMMENT 'Description or answers submitted by the claimant',

    status ENUM('pending','approved','rejected','cancelled')
        NOT NULL
        DEFAULT 'pending'
        COMMENT 'Current status of the claim',

    reporter_note TEXT
        DEFAULT NULL
        COMMENT 'Optional note added by the reporter',

    approved_at TIMESTAMP
        NULL
        DEFAULT NULL
        COMMENT 'Timestamp when the claim was approved',

    rejected_at TIMESTAMP
        NULL
        DEFAULT NULL
        COMMENT 'Timestamp when the claim was rejected',

    -- Timestamps
    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    -- Constraints
    UNIQUE KEY unique_item_claimant (item_id, claimant_id),

    -- Indexes
    INDEX idx_item_id (item_id),
    INDEX idx_claimant_id (claimant_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Table: notifications
-- Purpose:
-- Stores system-generated notifications for users, including
-- report updates, claim activities, account events, and
-- administrative announcements.
-- =========================================================

CREATE TABLE notifications (

    -- Internal Identifier
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Public Identifier
    notification_code CHAR(13)
        NOT NULL
        UNIQUE
        COMMENT 'Public Notification ID (e.g. NTF-840512937)',

    -- Notification Recipient
    user_id BIGINT UNSIGNED NOT NULL,

    -- Related Item (Optional)
    item_id BIGINT UNSIGNED DEFAULT NULL,

    -- Related Claim (Optional)
    claim_id BIGINT UNSIGNED DEFAULT NULL,

    -- Notification Information
    title VARCHAR(150) NOT NULL,

    message TEXT NOT NULL,

    type ENUM(
        'success',
        'info',
        'warning',
        'error'
    ) NOT NULL,

    category ENUM(
        'account',
        'report',
        'claim',
        'admin',
        'system',
        'contact'
    ) NOT NULL,

    -- Related Contact Request (Optional)
    contact_request_id BIGINT UNSIGNED DEFAULT NULL,

    -- Related Conversation (Optional)
    conversation_id BIGINT UNSIGNED DEFAULT NULL,

    -- Read Status
    is_read TINYINT(1)
        NOT NULL
        DEFAULT 0,

    read_at TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    -- Performance Indexes
    INDEX idx_user (user_id),
    INDEX idx_item (item_id),
    INDEX idx_claim (claim_id),
    INDEX idx_type (type),
    INDEX idx_category (category),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Foreign Key Constraints
-- =========================================================

-- ---------------------------------------------------------
-- items → users
-- Every reported item belongs to one registered user.
-- Users are never permanently deleted.
-- ---------------------------------------------------------

ALTER TABLE items
ADD CONSTRAINT fk_items_user
FOREIGN KEY (user_id)
REFERENCES users(id)
ON UPDATE CASCADE
ON DELETE RESTRICT;


-- ---------------------------------------------------------
-- items → claims
-- Stores the approved ownership claim for an item.
-- If an approved claim is removed, the reference becomes NULL.
-- ---------------------------------------------------------

ALTER TABLE items
ADD CONSTRAINT fk_items_approved_claim
FOREIGN KEY (approved_claim_id)
REFERENCES claims(id)
ON UPDATE CASCADE
ON DELETE SET NULL;


-- ---------------------------------------------------------
-- item_images → items
-- Every image belongs to exactly one item.
-- Deleting an item removes all associated images.
-- ---------------------------------------------------------

ALTER TABLE item_images
ADD CONSTRAINT fk_item_images_item
FOREIGN KEY (item_id)
REFERENCES items(id)
ON UPDATE CASCADE
ON DELETE CASCADE;


-- ---------------------------------------------------------
-- claims → items
-- Every claim belongs to one reported item.
-- Claims cannot exist without their parent item.
-- ---------------------------------------------------------

ALTER TABLE claims
ADD CONSTRAINT fk_claims_item
FOREIGN KEY (item_id)
REFERENCES items(id)
ON UPDATE CASCADE
ON DELETE CASCADE;


-- ---------------------------------------------------------
-- claims → users
-- Every claim belongs to one claimant.
-- Users are never permanently deleted.
-- ---------------------------------------------------------

ALTER TABLE claims
ADD CONSTRAINT fk_claims_claimant
FOREIGN KEY (claimant_id)
REFERENCES users(id)
ON UPDATE CASCADE
ON DELETE RESTRICT;


-- ---------------------------------------------------------
-- notifications → users
-- Every notification belongs to one recipient.
-- Users are never permanently deleted.
-- ---------------------------------------------------------

ALTER TABLE notifications
ADD CONSTRAINT fk_notifications_user
FOREIGN KEY (user_id)
REFERENCES users(id)
ON UPDATE CASCADE
ON DELETE RESTRICT;


-- ---------------------------------------------------------
-- notifications → items
-- Notifications may optionally reference an item.
-- If the item is removed, keep the notification.
-- ---------------------------------------------------------

ALTER TABLE notifications
ADD CONSTRAINT fk_notifications_item
FOREIGN KEY (item_id)
REFERENCES items(id)
ON UPDATE CASCADE
ON DELETE SET NULL;


-- ---------------------------------------------------------
-- notifications → claims
-- Notifications may optionally reference a claim.
-- If the claim is removed, keep the notification.
-- ---------------------------------------------------------

ALTER TABLE notifications
ADD CONSTRAINT fk_notifications_claim
FOREIGN KEY (claim_id)
REFERENCES claims(id)
ON UPDATE CASCADE
ON DELETE SET NULL;


-- =========================================================
-- Table: contact_requests
-- Purpose:
-- Stores initial contact inquiries submitted by users to reporters.
-- =========================================================

CREATE TABLE IF NOT EXISTS contact_requests (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_code CHAR(13) NOT NULL UNIQUE COMMENT 'Public Request ID (e.g. REQ-840512937)',
    item_id BIGINT UNSIGNED NOT NULL COMMENT 'References items.id',
    sender_id BIGINT UNSIGNED NOT NULL COMMENT 'References users.id (the user contacting)',
    reporter_id BIGINT UNSIGNED NOT NULL COMMENT 'References users.id (the item reporter)',
    initial_message TEXT NOT NULL COMMENT 'Initial inquiry message',
    status ENUM('pending', 'accepted', 'rejected', 'closed') NOT NULL DEFAULT 'pending' COMMENT 'Status of request',
    rejection_reason TEXT DEFAULT NULL COMMENT 'Optional rejection reason',
    responded_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when reporter accepted or rejected',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_cr_item (item_id),
    INDEX idx_cr_sender (sender_id),
    INDEX idx_cr_reporter (reporter_id),
    INDEX idx_cr_status (status),
    INDEX idx_cr_created (created_at),

    CONSTRAINT fk_contact_requests_item FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_contact_requests_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contact_requests_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Table: conversations
-- Purpose:
-- Stores active and closed two-way messaging channels created
-- after a contact request is accepted.
-- =========================================================

CREATE TABLE IF NOT EXISTS conversations (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_code CHAR(13) NOT NULL UNIQUE COMMENT 'Public Conversation ID (e.g. CNV-582903741)',
    contact_request_id BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'References contact_requests.id',
    item_id BIGINT UNSIGNED NOT NULL COMMENT 'References items.id',
    participant_one_id BIGINT UNSIGNED NOT NULL COMMENT 'References users.id (initial sender)',
    participant_two_id BIGINT UNSIGNED NOT NULL COMMENT 'References users.id (item reporter)',
    blocked_by_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'References users.id if conversation is blocked',
    blocked_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when blocked',
    status ENUM('active', 'closed') NOT NULL DEFAULT 'active' COMMENT 'active or closed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_conv_item (item_id),
    INDEX idx_conv_p1 (participant_one_id),
    INDEX idx_conv_p2 (participant_two_id),
    INDEX idx_conv_status (status),
    INDEX idx_conv_updated (updated_at),

    CONSTRAINT fk_conversations_request FOREIGN KEY (contact_request_id) REFERENCES contact_requests(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_conversations_item FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_conversations_p1 FOREIGN KEY (participant_one_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_conversations_p2 FOREIGN KEY (participant_two_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_conversations_blocked_by FOREIGN KEY (blocked_by_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Table: messages
-- Purpose:
-- Stores individual chat messages exchanged between participants.
-- =========================================================

CREATE TABLE IF NOT EXISTS messages (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_code CHAR(13) NOT NULL UNIQUE COMMENT 'Public Message ID (e.g. MSG-274910385)',
    conversation_id BIGINT UNSIGNED NOT NULL COMMENT 'References conversations.id',
    sender_id BIGINT UNSIGNED NOT NULL COMMENT 'References users.id',
    message TEXT NOT NULL COMMENT 'Message text',
    is_read TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = Unread, 1 = Read',
    read_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when read by recipient',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_msg_conv (conversation_id),
    INDEX idx_msg_sender (sender_id),
    INDEX idx_msg_read (is_read),
    INDEX idx_msg_created (created_at),

    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- notifications → contact_requests
-- ---------------------------------------------------------

ALTER TABLE notifications
ADD CONSTRAINT fk_notifications_contact_request
FOREIGN KEY (contact_request_id)
REFERENCES contact_requests(id)
ON UPDATE CASCADE
ON DELETE SET NULL;


-- ---------------------------------------------------------
-- notifications → conversations
-- ---------------------------------------------------------

ALTER TABLE notifications
ADD CONSTRAINT fk_notifications_conversation
FOREIGN KEY (conversation_id)
REFERENCES conversations(id)
ON UPDATE CASCADE
ON DELETE SET NULL;


-- =========================================================
-- Table: blocked_users
-- Purpose:
-- Stores user blocking relationships across conversations.
-- =========================================================

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


-- =========================================================
-- Table: announcements
-- Purpose:
-- Stores campus, system, and LostLink notices posted by
-- administrators for students and staff.
-- =========================================================

CREATE TABLE IF NOT EXISTS `announcements` (

    -- Internal Identifier
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Public Identifier
    `announcement_code` CHAR(13) NOT NULL UNIQUE COMMENT 'Public Announcement ID (e.g. ANC-840512937)',

    -- Author / Admin Reference
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'References users.id (admin author)',

    -- Announcement Content
    `title` VARCHAR(200) NOT NULL COMMENT 'Announcement title',
    `content` TEXT NOT NULL COMMENT 'Full announcement text content',

    -- Categorization & Priority
    `category` ENUM('college', 'lostlink', 'system', 'event', 'important') NOT NULL DEFAULT 'lostlink',
    `priority` ENUM('normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = Normal, 1 = Pinned',

    -- Status & Scheduling
    `status` ENUM('draft', 'published', 'scheduled', 'archived') NOT NULL DEFAULT 'published',
    `scheduled_at` DATETIME NULL DEFAULT NULL COMMENT 'Target publish time for scheduled announcements',
    `expires_at` DATETIME NULL DEFAULT NULL COMMENT 'Optional expiration time',
    `published_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Actual publication timestamp',

    -- Timestamps
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX `idx_anc_user` (`user_id`),
    INDEX `idx_anc_status` (`status`),
    INDEX `idx_anc_pinned` (`is_pinned`),
    INDEX `idx_anc_priority` (`priority`),
    INDEX `idx_anc_published` (`published_at`),
    INDEX `idx_anc_scheduled` (`scheduled_at`),
    INDEX `idx_anc_expires` (`expires_at`),

    -- Foreign Key Constraints
    CONSTRAINT `fk_announcements_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

