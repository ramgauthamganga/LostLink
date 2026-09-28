<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * LOSTLINK CONVERSATION CONTROLLER
 * ==============================================================================
 * Handles:
 * - Listing all conversations for the authenticated user
 * - Viewing a conversation with full message thread
 * - Automatic read-state marking upon retrieval
 * 
 * Strict authorization: Only participating users can access conversations.
 */

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../backend/utils/helpers.php';

function sendJsonResponse(int $statusCode, array $payload): never {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Verify Authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || empty($_SESSION['user_id'])) {
    sendJsonResponse(401, ['success' => false, 'error' => 'Please log in to continue.']);
}

$currentUserId = (int)$_SESSION['user_id'];
$currentUserRole = (string)($_SESSION['role'] ?? 'student');
$isAdmin = ($currentUserRole === 'admin');

// 2. Check if user is banned
try {
    $banStmt = $pdo->prepare("SELECT is_banned FROM users WHERE id = :id LIMIT 1");
    $banStmt->execute(['id' => $currentUserId]);
    $userRow = $banStmt->fetch();
    if (!$userRow || (int)$userRow['is_banned'] === 1) {
        sendJsonResponse(403, ['success' => false, 'error' => 'Your account is suspended.']);
    }
} catch (Throwable $e) {
    sendJsonResponse(500, ['success' => false, 'error' => 'Database error.']);
}

$action = trim((string)($_REQUEST['action'] ?? ''));

// ==============================================================================
// ACTION: LIST CONVERSATIONS (GET)
// ==============================================================================
if ($action === 'list' || (empty($action) && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['code']) && !isset($_GET['c']))) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.conversation_code,
                c.status AS conversation_status,
                c.created_at AS conversation_created_at,
                c.updated_at AS conversation_updated_at,
                c.blocked_by_id,
                c.blocked_at,
                c.item_id,
                c.participant_one_id,
                c.participant_two_id,
                i.title AS item_title,
                i.item_code,
                i.report_type AS item_type,
                i.status AS item_status,
                i.event_location AS item_location,
                p1.id AS p1_id, p1.name AS p1_name, p1.department AS p1_dept, p1.profile_image AS p1_avatar,
                p2.id AS p2_id, p2.name AS p2_name, p2.department AS p2_dept, p2.profile_image AS p2_avatar
            FROM conversations c
            INNER JOIN items i ON c.item_id = i.id
            INNER JOIN users p1 ON c.participant_one_id = p1.id
            INNER JOIN users p2 ON c.participant_two_id = p2.id
            WHERE c.participant_one_id = :uid1 OR c.participant_two_id = :uid2
            ORDER BY c.updated_at DESC, c.created_at DESC
        ");
        $stmt->execute(['uid1' => $currentUserId, 'uid2' => $currentUserId]);
        $rawConversations = $stmt->fetchAll();

        $conversations = [];
        $totalUnreadAll = 0;

        foreach ($rawConversations as $row) {
            $convId = (int)$row['id'];
            $isP1 = ($currentUserId === (int)$row['participant_one_id']);

            $otherParty = [
                'id'         => $isP1 ? (int)$row['p2_id'] : (int)$row['p1_id'],
                'name'       => $isP1 ? (string)$row['p2_name'] : (string)$row['p1_name'],
                'department' => $isP1 ? (string)$row['p2_dept'] : (string)$row['p1_dept'],
                'avatar_url' => getProfileImageUrl($isP1 ? $row['p2_avatar'] : $row['p1_avatar'])
            ];

            // Latest message
            $msgStmt = $pdo->prepare("
                SELECT message, sender_id, created_at, is_read 
                FROM messages 
                WHERE conversation_id = :cid 
                ORDER BY created_at DESC, id DESC 
                LIMIT 1
            ");
            $msgStmt->execute(['cid' => $convId]);
            $latestMsg = $msgStmt->fetch();

            // Unread count for current user
            $unreadStmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count 
                FROM messages 
                WHERE conversation_id = :cid 
                  AND sender_id != :uid 
                  AND is_read = 0
            ");
            $unreadStmt->execute(['cid' => $convId, 'uid' => $currentUserId]);
            $unreadCount = (int)($unreadStmt->fetch()['unread_count'] ?? 0);
            $totalUnreadAll += $unreadCount;

            // Thumbnail for item
            $thumbStmt = $pdo->prepare("SELECT image FROM item_images WHERE item_id = :iid ORDER BY image_order ASC LIMIT 1");
            $thumbStmt->execute(['iid' => (int)$row['item_id']]);
            $thumbRow = $thumbStmt->fetch();
            $thumbImage = $thumbRow['image'] ?? null;
            $thumbUrl = getItemThumbnailUrl($thumbImage);

            $previewText = $latestMsg ? (string)$latestMsg['message'] : 'No messages yet';
            if (mb_strlen($previewText, 'UTF-8') > 70) {
                $previewText = mb_substr($previewText, 0, 70) . '...';
            }

            $blockedById = $row['blocked_by_id'] !== null ? (int)$row['blocked_by_id'] : null;

            $conversations[] = [
                'id'                   => $convId,
                'conversation_code'    => (string)$row['conversation_code'],
                'status'               => (string)$row['conversation_status'],
                'is_blocked'           => ($blockedById !== null),
                'blocked_by_id'        => $blockedById,
                'blocked_by_current_user' => ($blockedById === $currentUserId),
                'created_at'           => (string)$row['conversation_created_at'],
                'updated_at'           => (string)$row['conversation_updated_at'],
                'item' => [
                    'id'               => (int)$row['item_id'],
                    'item_code'        => (string)$row['item_code'],
                    'title'            => (string)$row['item_title'],
                    'type'             => (string)$row['item_type'],
                    'status'           => (string)$row['item_status'],
                    'location'         => (string)$row['item_location'],
                    'thumbnail_url'    => $thumbUrl
                ],
                'other_party'          => $otherParty,
                'latest_message' => [
                    'text'             => $previewText,
                    'created_at'       => $latestMsg ? (string)$latestMsg['created_at'] : (string)$row['conversation_created_at'],
                    'created_at_rel'   => $latestMsg ? getRelativeTime((string)$latestMsg['created_at']) : getRelativeTime((string)$row['conversation_created_at']),
                    'is_outgoing'      => $latestMsg ? ((int)$latestMsg['sender_id'] === $currentUserId) : false,
                ],
                'unread_count'         => $unreadCount
            ];
        }

        sendJsonResponse(200, [
            'success' => true,
            'data' => [
                'conversations'    => $conversations,
                'total_unread'     => $totalUnreadAll,
                'count'            => count($conversations)
            ]
        ]);

    } catch (Throwable $e) {
        error_log("Conversation list error: " . $e->getMessage());
        sendJsonResponse(500, ['success' => false, 'error' => 'Failed to load conversations.']);
    }
}

// ==============================================================================
// ACTION: GET CONVERSATION WITH MESSAGES (GET)
// ==============================================================================
if ($action === 'get' || isset($_GET['code']) || isset($_GET['c'])) {
    $convCode = trim((string)($_GET['code'] ?? $_GET['conversation_code'] ?? $_GET['c'] ?? ''));

    if ($convCode === '') {
        sendJsonResponse(400, ['success' => false, 'error' => 'Conversation identifier is required.']);
    }

    try {
        $idNum = ctype_digit($convCode) ? (int)$convCode : 0;
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.conversation_code,
                c.status AS conversation_status,
                c.created_at AS conversation_created_at,
                c.updated_at AS conversation_updated_at,
                c.blocked_by_id,
                c.blocked_at,
                c.item_id,
                c.contact_request_id,
                c.participant_one_id,
                c.participant_two_id,
                i.title AS item_title,
                i.item_code,
                i.report_type AS item_type,
                i.status AS item_status,
                i.category AS item_category,
                i.event_location AS item_location,
                i.event_date AS item_event_date,
                p1.id AS p1_id, p1.name AS p1_name, p1.department AS p1_dept, p1.profile_image AS p1_avatar, p1.user_code AS p1_code,
                p2.id AS p2_id, p2.name AS p2_name, p2.department AS p2_dept, p2.profile_image AS p2_avatar, p2.user_code AS p2_code
            FROM conversations c
            INNER JOIN items i ON c.item_id = i.id
            INNER JOIN users p1 ON c.participant_one_id = p1.id
            INNER JOIN users p2 ON c.participant_two_id = p2.id
            WHERE c.conversation_code = :code OR c.id = :id_num
            LIMIT 1
        ");
        $stmt->execute(['code' => $convCode, 'id_num' => $idNum]);
        $conv = $stmt->fetch();

        if (!$conv) {
            sendJsonResponse(404, ['success' => false, 'error' => 'Conversation not found.']);
        }

        $convId = (int)$conv['id'];
        $p1Id = (int)$conv['participant_one_id'];
        $p2Id = (int)$conv['participant_two_id'];

        // Strict participant check
        if ($currentUserId !== $p1Id && $currentUserId !== $p2Id && !$isAdmin) {
            sendJsonResponse(403, ['success' => false, 'error' => 'You are not authorized to access this conversation.']);
        }

        // Mark incoming messages as read for current user
        $markStmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = 1, read_at = NOW() 
            WHERE conversation_id = :cid 
              AND sender_id != :uid 
              AND is_read = 0
        ");
        $markStmt->execute(['cid' => $convId, 'uid' => $currentUserId]);

        // Fetch messages
        $msgStmt = $pdo->prepare("
            SELECT 
                m.id,
                m.message_code,
                m.sender_id,
                m.message,
                m.is_read,
                m.read_at,
                m.created_at,
                u.name AS sender_name,
                u.profile_image AS sender_avatar
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = :cid
            ORDER BY m.created_at ASC, m.id ASC
        ");
        $msgStmt->execute(['cid' => $convId]);
        $rawMessages = $msgStmt->fetchAll();

        $messages = [];
        foreach ($rawMessages as $m) {
            $isOutgoing = ((int)$m['sender_id'] === $currentUserId);
            $messages[] = [
                'id'             => (int)$m['id'],
                'message_code'   => (string)$m['message_code'],
                'sender_id'      => (int)$m['sender_id'],
                'sender_name'    => (string)$m['sender_name'],
                'sender_avatar'  => getProfileImageUrl($m['sender_avatar']),
                'message'        => (string)$m['message'],
                'is_outgoing'    => $isOutgoing,
                'is_read'        => (bool)$m['is_read'],
                'read_at'        => $m['read_at'],
                'created_at'     => (string)$m['created_at'],
                'created_at_rel' => getRelativeTime((string)$m['created_at']),
                'time_formatted' => date('g:i A', strtotime((string)$m['created_at']))
            ];
        }

        // Determine other party info
        $isP1 = ($currentUserId === $p1Id);
        $otherParty = [
            'id'         => $isP1 ? (int)$conv['p2_id'] : (int)$conv['p1_id'],
            'name'       => $isP1 ? (string)$conv['p2_name'] : (string)$conv['p1_name'],
            'department' => $isP1 ? (string)$conv['p2_dept'] : (string)$conv['p1_dept'],
            'avatar_url' => getProfileImageUrl($isP1 ? $conv['p2_avatar'] : $conv['p1_avatar']),
            'user_code'  => $isP1 ? (string)$conv['p2_code'] : (string)$conv['p1_code']
        ];

        // Item primary thumbnail
        $imgStmt = $pdo->prepare("SELECT image FROM item_images WHERE item_id = :iid ORDER BY image_order ASC LIMIT 1");
        $imgStmt->execute(['iid' => (int)$conv['item_id']]);
        $imgRow = $imgStmt->fetch();
        $thumbImage = $imgRow['image'] ?? null;
        $thumbUrl = getItemThumbnailUrl($thumbImage);

        // Blocking state calculations
        $blockedById = $conv['blocked_by_id'] !== null ? (int)$conv['blocked_by_id'] : null;
        $isBlocked = ($blockedById !== null);
        $blockedByCurrentUser = ($blockedById === $currentUserId);
        $isClosed = ($conv['conversation_status'] === 'closed' || in_array($conv['item_status'], ['closed', 'returned'], true));
        $canMessage = (!$isBlocked && !$isClosed && $conv['conversation_status'] === 'active');

        sendJsonResponse(200, [
            'success' => true,
            'data' => [
                'id'                      => $convId,
                'conversation_code'       => (string)$conv['conversation_code'],
                'status'                  => (string)$conv['conversation_status'],
                'is_closed'               => $isClosed,
                'is_blocked'              => $isBlocked,
                'blocked_by_id'           => $blockedById,
                'blocked_by_current_user' => $blockedByCurrentUser,
                'can_message'             => $canMessage,
                'created_at'              => (string)$conv['conversation_created_at'],
                'item' => [
                    'id'                  => (int)$conv['item_id'],
                    'item_code'           => (string)$conv['item_code'],
                    'title'               => (string)$conv['item_title'],
                    'type'                => (string)$conv['item_type'],
                    'category'            => (string)$conv['item_category'],
                    'location'            => (string)$conv['item_location'],
                    'event_date'          => (string)$conv['item_event_date'],
                    'status'              => (string)$conv['item_status'],
                    'thumbnail_url'       => $thumbUrl
                ],
                'other_party'             => $otherParty,
                'messages'                => $messages,
                'csrf_token'              => (string)($_SESSION['csrf_token'] ?? '')
            ]
        ]);

    } catch (Throwable $e) {
        error_log("Conversation get error: " . $e->getMessage());
        sendJsonResponse(500, ['success' => false, 'error' => 'Failed to load conversation messages.']);
    }
}

// ==============================================================================
// ACTION: BLOCK USER IN CONVERSATION (POST)
// ==============================================================================
if ($action === 'block') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(405, ['success' => false, 'error' => 'Method not allowed.']);
    }

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($token)) {
        sendJsonResponse(403, ['success' => false, 'error' => 'Invalid or expired session security token.']);
    }

    $convCode = trim((string)($_POST['code'] ?? $_POST['conversation_code'] ?? $_POST['c'] ?? ''));
    if ($convCode === '') {
        sendJsonResponse(400, ['success' => false, 'error' => 'Conversation identifier is required.']);
    }

    try {
        $idNum = ctype_digit($convCode) ? (int)$convCode : 0;
        $stmt = $pdo->prepare("
            SELECT id, conversation_code, participant_one_id, participant_two_id, blocked_by_id 
            FROM conversations 
            WHERE conversation_code = :code OR id = :id_num 
            LIMIT 1
        ");
        $stmt->execute(['code' => $convCode, 'id_num' => $idNum]);
        $conv = $stmt->fetch();

        if (!$conv) {
            sendJsonResponse(404, ['success' => false, 'error' => 'Conversation not found.']);
        }

        $convId = (int)$conv['id'];
        $p1Id = (int)$conv['participant_one_id'];
        $p2Id = (int)$conv['participant_two_id'];

        if ($currentUserId !== $p1Id && $currentUserId !== $p2Id && !$isAdmin) {
            sendJsonResponse(403, ['success' => false, 'error' => 'You are not authorized to manage this conversation.']);
        }

        $otherUserId = ($currentUserId === $p1Id) ? $p2Id : $p1Id;

        $pdo->beginTransaction();

        // 1. Insert into blocked_users table
        $blockStmt = $pdo->prepare("
            INSERT INTO blocked_users (blocker_id, blocked_id, conversation_id, created_at)
            VALUES (:blocker, :blocked, :cid, NOW())
            ON DUPLICATE KEY UPDATE conversation_id = :cid_up, created_at = NOW()
        ");
        $blockStmt->execute([
            'blocker' => $currentUserId,
            'blocked' => $otherUserId,
            'cid'     => $convId,
            'cid_up'  => $convId
        ]);

        // 2. Mark conversation as blocked by current user
        $convStmt = $pdo->prepare("
            UPDATE conversations 
            SET blocked_by_id = :uid, blocked_at = NOW() 
            WHERE id = :cid
        ");
        $convStmt->execute(['uid' => $currentUserId, 'cid' => $convId]);

        $pdo->commit();

        sendJsonResponse(200, [
            'success' => true,
            'message' => 'User has been blocked. Messaging is now disabled.',
            'data' => [
                'conversation_code'       => (string)$conv['conversation_code'],
                'is_blocked'              => true,
                'blocked_by_id'           => $currentUserId,
                'blocked_by_current_user' => true,
                'can_message'             => false
            ]
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Block user error: " . $e->getMessage());
        sendJsonResponse(500, ['success' => false, 'error' => 'Failed to block user.']);
    }
}

// ==============================================================================
// ACTION: UNBLOCK USER IN CONVERSATION (POST)
// ==============================================================================
if ($action === 'unblock') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(405, ['success' => false, 'error' => 'Method not allowed.']);
    }

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($token)) {
        sendJsonResponse(403, ['success' => false, 'error' => 'Invalid or expired session security token.']);
    }

    $convCode = trim((string)($_POST['code'] ?? $_POST['conversation_code'] ?? $_POST['c'] ?? ''));
    if ($convCode === '') {
        sendJsonResponse(400, ['success' => false, 'error' => 'Conversation identifier is required.']);
    }

    try {
        $idNum = ctype_digit($convCode) ? (int)$convCode : 0;
        $stmt = $pdo->prepare("
            SELECT c.id, c.conversation_code, c.participant_one_id, c.participant_two_id, c.blocked_by_id, c.status AS conv_status, i.status AS item_status 
            FROM conversations c
            INNER JOIN items i ON c.item_id = i.id
            WHERE c.conversation_code = :code OR c.id = :id_num 
            LIMIT 1
        ");
        $stmt->execute(['code' => $convCode, 'id_num' => $idNum]);
        $conv = $stmt->fetch();

        if (!$conv) {
            sendJsonResponse(404, ['success' => false, 'error' => 'Conversation not found.']);
        }

        $convId = (int)$conv['id'];
        $p1Id = (int)$conv['participant_one_id'];
        $p2Id = (int)$conv['participant_two_id'];

        if ($currentUserId !== $p1Id && $currentUserId !== $p2Id && !$isAdmin) {
            sendJsonResponse(403, ['success' => false, 'error' => 'You are not authorized to manage this conversation.']);
        }

        $otherUserId = ($currentUserId === $p1Id) ? $p2Id : $p1Id;

        // Check if current user actually blocked other user
        $checkStmt = $pdo->prepare("SELECT id FROM blocked_users WHERE blocker_id = :me AND blocked_id = :other LIMIT 1");
        $checkStmt->execute(['me' => $currentUserId, 'other' => $otherUserId]);
        if (!$checkStmt->fetch() && (int)$conv['blocked_by_id'] !== $currentUserId && !$isAdmin) {
            sendJsonResponse(403, ['success' => false, 'error' => 'You cannot unblock this conversation because you did not block this user.']);
        }

        $pdo->beginTransaction();

        // 1. Delete from blocked_users where current user is blocker
        $delStmt = $pdo->prepare("DELETE FROM blocked_users WHERE blocker_id = :me AND blocked_id = :other");
        $delStmt->execute(['me' => $currentUserId, 'other' => $otherUserId]);

        // 2. Check if the other user also blocked current user
        $otherBlockStmt = $pdo->prepare("SELECT blocker_id, created_at FROM blocked_users WHERE blocker_id = :other AND blocked_id = :me LIMIT 1");
        $otherBlockStmt->execute(['other' => $otherUserId, 'me' => $currentUserId]);
        $otherBlock = $otherBlockStmt->fetch();

        if ($otherBlock) {
            // Still blocked by the other user!
            $upConv = $pdo->prepare("UPDATE conversations SET blocked_by_id = :other, blocked_at = :cat WHERE id = :cid");
            $upConv->execute([
                'other' => $otherUserId,
                'cat'   => $otherBlock['created_at'],
                'cid'   => $convId
            ]);
            $isStillBlocked = true;
            $blockedByCurrentUser = false;
            $blockedById = $otherUserId;
        } else {
            // Completely unblocked
            $upConv = $pdo->prepare("UPDATE conversations SET blocked_by_id = NULL, blocked_at = NULL WHERE id = :cid");
            $upConv->execute(['cid' => $convId]);
            $isStillBlocked = false;
            $blockedByCurrentUser = false;
            $blockedById = null;
        }

        $pdo->commit();

        $isClosed = ($conv['conv_status'] === 'closed' || in_array($conv['item_status'], ['closed', 'returned'], true));
        $canMessage = (!$isStillBlocked && !$isClosed && $conv['conv_status'] === 'active');

        sendJsonResponse(200, [
            'success' => true,
            'message' => $isStillBlocked ? 'You have unblocked this user, but they have also blocked you.' : 'User has been unblocked. Messaging restored.',
            'data' => [
                'conversation_code'       => (string)$conv['conversation_code'],
                'is_blocked'              => $isStillBlocked,
                'blocked_by_id'           => $blockedById,
                'blocked_by_current_user' => $blockedByCurrentUser,
                'can_message'             => $canMessage
            ]
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Unblock user error: " . $e->getMessage());
        sendJsonResponse(500, ['success' => false, 'error' => 'Failed to unblock user.']);
    }
}

sendJsonResponse(400, ['success' => false, 'error' => 'Invalid action.']);
