<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * LOSTLINK SEND MESSAGE CONTROLLER
 * ==============================================================================
 * Handles:
 * - Sending new messages in an active conversation
 * - Server-side sender authentication (sender_id is NEVER trusted from client)
 * - Conversation participant authorization
 * - Updating unread state and recipient notifications
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['success' => false, 'error' => 'Method not allowed.']);
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
    $banStmt = $pdo->prepare("SELECT is_banned, name FROM users WHERE id = :id LIMIT 1");
    $banStmt->execute(['id' => $currentUserId]);
    $userRow = $banStmt->fetch();
    if (!$userRow || (int)$userRow['is_banned'] === 1) {
        sendJsonResponse(403, ['success' => false, 'error' => 'Your account is suspended. Contact support.']);
    }
} catch (Throwable $e) {
    sendJsonResponse(500, ['success' => false, 'error' => 'Database error verifying account state.']);
}

// 3. CSRF Validation
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verifyCsrfToken($token)) {
    sendJsonResponse(403, ['success' => false, 'error' => 'Invalid or expired session security token. Please refresh the page.']);
}

// 4. Validate Inputs
$convIdentifier = trim((string)($_POST['conversation_code'] ?? $_POST['conversation_id'] ?? ''));
$messageText = trim((string)($_POST['message'] ?? ''));

if ($convIdentifier === '') {
    sendJsonResponse(422, ['success' => false, 'error' => 'Conversation identifier is required.']);
}

if ($messageText === '') {
    sendJsonResponse(422, ['success' => false, 'error' => 'Message text cannot be empty.']);
}

if (mb_strlen($messageText, 'UTF-8') > 2000) {
    sendJsonResponse(422, ['success' => false, 'error' => 'Message exceeds maximum limit of 2000 characters.']);
}

try {
    // 5. Query conversation & verify participant authorization
    $idNum = ctype_digit($convIdentifier) ? (int)$convIdentifier : 0;
    $stmt = $pdo->prepare("
        SELECT c.*, i.title AS item_title, i.status AS item_status, i.item_code 
        FROM conversations c
        INNER JOIN items i ON c.item_id = i.id
        WHERE c.conversation_code = :code OR c.id = :id_num
        LIMIT 1
    ");
    $stmt->execute(['code' => $convIdentifier, 'id_num' => $idNum]);
    $conv = $stmt->fetch();

    if (!$conv) {
        sendJsonResponse(404, ['success' => false, 'error' => 'Conversation not found.']);
    }

    $convId = (int)$conv['id'];
    $p1Id = (int)$conv['participant_one_id'];
    $p2Id = (int)$conv['participant_two_id'];

    // Strict participant check
    if ($currentUserId !== $p1Id && $currentUserId !== $p2Id && !$isAdmin) {
        sendJsonResponse(403, ['success' => false, 'error' => 'You are not a participant in this conversation.']);
    }

    // Check conversation status
    if ($conv['status'] === 'closed' || in_array($conv['item_status'], ['closed', 'returned'], true)) {
        sendJsonResponse(422, ['success' => false, 'error' => 'This conversation is closed. New messages cannot be sent.']);
    }

    // Determine recipient
    $recipientId = ($currentUserId === $p1Id) ? $p2Id : $p1Id;

    // Check if conversation or participants are blocked
    if (!empty($conv['blocked_by_id'])) {
        sendJsonResponse(403, ['success' => false, 'error' => 'This conversation is blocked. Messages cannot be sent.']);
    }

    $blockStmt = $pdo->prepare("
        SELECT id FROM blocked_users 
        WHERE (blocker_id = :b_u1 AND blocked_id = :b_u2)
           OR (blocker_id = :b_u3 AND blocked_id = :b_u4)
        LIMIT 1
    ");
    $blockStmt->execute([
        'b_u1' => $currentUserId, 'b_u2' => $recipientId,
        'b_u3' => $recipientId,   'b_u4' => $currentUserId
    ]);
    if ($blockStmt->fetch()) {
        sendJsonResponse(403, ['success' => false, 'error' => 'You cannot send messages to this user because of a user block.']);
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Generate unique message code
    $msgCode = generatePublicCode('MSG', $pdo, 'messages', 'message_code');

    // Insert message
    $insertStmt = $pdo->prepare("
        INSERT INTO messages (
            message_code,
            conversation_id,
            sender_id,
            message,
            is_read,
            created_at
        ) VALUES (
            :code,
            :cid,
            :sender_id,
            :msg,
            0,
            NOW()
        )
    ");
    $insertStmt->execute([
        'code'      => $msgCode,
        'cid'       => $convId,
        'sender_id' => $currentUserId,
        'msg'       => $messageText
    ]);

    $newMsgId = (int)$pdo->lastInsertId();

    // Update conversation timestamp
    $touchStmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = :cid");
    $touchStmt->execute(['cid' => $convId]);

    // Create Notification for recipient
    $senderName = (string)($_SESSION['name'] ?? 'A user');
    $itemTitle = (string)$conv['item_title'];
    $notifTitle = "New Message";
    $notifMsg = "You have a new message from {$senderName} regarding \"{$itemTitle}\".";

    createNotification(
        $pdo,
        $recipientId,
        $notifTitle,
        $notifMsg,
        'info',
        'contact',
        (int)$conv['item_id'],
        null,
        (int)$conv['contact_request_id'],
        $convId
    );

    $pdo->commit();

    sendJsonResponse(200, [
        'success' => true,
        'message' => 'Message sent successfully.',
        'data' => [
            'id'             => $newMsgId,
            'message_code'   => $msgCode,
            'sender_id'      => $currentUserId,
            'sender_name'    => (string)($_SESSION['name'] ?? 'You'),
            'sender_avatar'  => getProfileImageUrl($_SESSION['profile_image'] ?? null),
            'message'        => $messageText,
            'is_outgoing'    => true,
            'is_read'        => false,
            'created_at'     => date('Y-m-d H:i:s'),
            'created_at_rel' => 'Just now',
            'time_formatted' => date('g:i A')
        ]
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Send message error: " . $e->getMessage());
    sendJsonResponse(500, ['success' => false, 'error' => 'Failed to send message. Please try again.']);
}
