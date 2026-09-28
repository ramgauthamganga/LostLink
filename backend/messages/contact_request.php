<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * LOSTLINK CONTACT REQUEST CONTROLLER
 * ==============================================================================
 * Handles:
 * - Submitting new contact requests
 * - Viewing contact request details
 * - Listing incoming/outgoing requests
 * - Accepting / Rejecting requests (reporter only)
 * 
 * Strict server-side authorization is enforced for every operation.
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

// Helper for JSON responses
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
    $banStmt = $pdo->prepare("SELECT is_banned, name FROM users WHERE id = :id LIMIT 1");
    $banStmt->execute(['id' => $currentUserId]);
    $userRow = $banStmt->fetch();
    if (!$userRow || (int)$userRow['is_banned'] === 1) {
        sendJsonResponse(403, ['success' => false, 'error' => 'Your account is suspended. Contact support.']);
    }
} catch (Throwable $e) {
    sendJsonResponse(500, ['success' => false, 'error' => 'Database error verifying account state.']);
}

$action = trim((string)($_REQUEST['action'] ?? ''));

// ==============================================================================
// ACTION: CREATE CONTACT REQUEST (POST)
// ==============================================================================
if ($action === 'create' || ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action))) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(405, ['success' => false, 'error' => 'Method not allowed']);
    }

    // CSRF Protection
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($token)) {
        sendJsonResponse(403, ['success' => false, 'error' => 'Invalid or expired session security token. Please refresh the page and try again.']);
    }

    $itemIdentifier = trim((string)($_POST['item_id'] ?? $_POST['item_code'] ?? ''));
    $initialMessage = trim((string)($_POST['message'] ?? ''));

    if ($itemIdentifier === '') {
        sendJsonResponse(422, ['success' => false, 'error' => 'Item identifier is required.']);
    }

    if ($initialMessage === '') {
        sendJsonResponse(422, ['success' => false, 'error' => 'Please provide an initial message explaining your inquiry.']);
    }

    if (mb_strlen($initialMessage, 'UTF-8') > 1000) {
        sendJsonResponse(422, ['success' => false, 'error' => 'Message is too long. Maximum length is 1000 characters.']);
    }

    try {
        // Resolve item
        $itemStmt = $pdo->prepare("
            SELECT id, item_code, user_id, title, status, report_type 
            FROM items 
            WHERE id = :id_num OR item_code = :code 
            LIMIT 1
        ");
        $idNum = ctype_digit($itemIdentifier) ? (int)$itemIdentifier : 0;
        $itemStmt->execute(['id_num' => $idNum, 'code' => $itemIdentifier]);
        $item = $itemStmt->fetch();

        if (!$item) {
            sendJsonResponse(404, ['success' => false, 'error' => 'The requested item could not be found.']);
        }

        $numericItemId = (int)$item['id'];
        $reporterId = (int)$item['user_id'];
        $itemStatus = (string)$item['status'];

        // Item status check
        if (in_array($itemStatus, ['closed', 'returned'], true)) {
            sendJsonResponse(422, ['success' => false, 'error' => 'This item is already ' . $itemStatus . ' and no longer accepting contact requests.']);
        }

        // Rule: Sender cannot contact themselves
        if ($currentUserId === $reporterId) {
            sendJsonResponse(403, ['success' => false, 'error' => 'You cannot contact yourself regarding an item you reported.']);
        }

        // Check reporter account active
        $repCheck = $pdo->prepare("SELECT id, name, is_banned FROM users WHERE id = :id LIMIT 1");
        $repCheck->execute(['id' => $reporterId]);
        $reporterUser = $repCheck->fetch();

        if (!$reporterUser) {
            sendJsonResponse(404, ['success' => false, 'error' => 'The reporter for this item is no longer available.']);
        }

        // Check if either user has blocked the other
        $blockCheck = $pdo->prepare("
            SELECT id FROM blocked_users 
            WHERE (blocker_id = :b_u1 AND blocked_id = :b_u2)
               OR (blocker_id = :b_u3 AND blocked_id = :b_u4)
            LIMIT 1
        ");
        $blockCheck->execute([
            'b_u1' => $currentUserId, 'b_u2' => $reporterId,
            'b_u3' => $reporterId,    'b_u4' => $currentUserId
        ]);
        if ($blockCheck->fetch()) {
            sendJsonResponse(403, ['success' => false, 'error' => 'You cannot send a contact request to this user.']);
        }

        // Check duplicate pending request
        $dupStmt = $pdo->prepare("
            SELECT id, request_code, status 
            FROM contact_requests 
            WHERE item_id = :item_id 
              AND sender_id = :sender_id 
              AND status = 'pending' 
            LIMIT 1
        ");
        $dupStmt->execute(['item_id' => $numericItemId, 'sender_id' => $currentUserId]);
        $existingPending = $dupStmt->fetch();

        if ($existingPending) {
            sendJsonResponse(409, [
                'success' => false, 
                'error' => 'You already have a pending contact request for this item. Please wait for the reporter to respond.',
                'request_code' => $existingPending['request_code']
            ]);
        }

        // Check if an accepted conversation already exists
        $convCheck = $pdo->prepare("
            SELECT c.conversation_code 
            FROM conversations c 
            WHERE c.item_id = :item_id 
              AND ((c.participant_one_id = :uid1 AND c.participant_two_id = :uid2) 
                OR (c.participant_one_id = :uid3 AND c.participant_two_id = :uid4))
              AND c.status = 'active'
            LIMIT 1
        ");
        $convCheck->execute([
            'item_id' => $numericItemId,
            'uid1' => $currentUserId, 'uid2' => $reporterId,
            'uid3' => $reporterId, 'uid4' => $currentUserId
        ]);
        $existingConv = $convCheck->fetch();

        if ($existingConv) {
            sendJsonResponse(200, [
                'success' => true,
                'message' => 'An active conversation already exists for this item.',
                'already_active' => true,
                'data' => [
                    'conversation_code' => $existingConv['conversation_code']
                ]
            ]);
        }

        // Begin transaction
        $pdo->beginTransaction();

        $requestCode = generatePublicCode('REQ', $pdo, 'contact_requests', 'request_code');

        $insertStmt = $pdo->prepare("
            INSERT INTO contact_requests (
                request_code,
                item_id,
                sender_id,
                reporter_id,
                initial_message,
                status,
                created_at
            ) VALUES (
                :code,
                :item_id,
                :sender_id,
                :reporter_id,
                :msg,
                'pending',
                NOW()
            )
        ");

        $insertStmt->execute([
            'code'        => $requestCode,
            'item_id'     => $numericItemId,
            'sender_id'   => $currentUserId,
            'reporter_id' => $reporterId,
            'msg'         => $initialMessage
        ]);

        $newRequestId = (int)$pdo->lastInsertId();

        // Create Notification for Reporter
        $senderName = (string)($_SESSION['name'] ?? 'A user');
        $itemTitle = (string)$item['title'];
        $notifTitle = "New Contact Request";
        $notifMsg = "{$senderName} sent a contact request regarding \"{$itemTitle}\".";

        createNotification(
            $pdo,
            $reporterId,
            $notifTitle,
            $notifMsg,
            'info',
            'contact',
            $numericItemId,
            null,
            $newRequestId,
            null
        );

        $pdo->commit();

        sendJsonResponse(200, [
            'success' => true,
            'message' => 'Contact request sent successfully.',
            'data' => [
                'request_code' => $requestCode,
                'item_code'    => $item['item_code'],
                'status'       => 'pending'
            ]
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Contact request create error: " . $e->getMessage());
        sendJsonResponse(500, ['success' => false, 'error' => 'Unable to send contact request. Please try again.']);
    }
}

// ==============================================================================
// ACTION: GET SINGLE CONTACT REQUEST (GET)
// ==============================================================================
if ($action === 'get') {
    $requestCode = trim((string)($_GET['code'] ?? $_GET['request_code'] ?? $_GET['r'] ?? ''));

    if ($requestCode === '') {
        sendJsonResponse(400, ['success' => false, 'error' => 'Request code is required.']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT cr.*,
                   i.item_code, i.title AS item_title, i.report_type AS item_type,
                   i.category AS item_category, i.event_location AS item_location,
                   i.status AS item_status,
                   u_sender.name AS sender_name, u_sender.department AS sender_department,
                   u_sender.profile_image AS sender_avatar, u_sender.user_code AS sender_code,
                   u_rep.name AS reporter_name, u_rep.department AS reporter_department,
                   u_rep.profile_image AS reporter_avatar, u_rep.user_code AS reporter_code,
                   c.conversation_code
            FROM contact_requests cr
            INNER JOIN items i ON cr.item_id = i.id
            INNER JOIN users u_sender ON cr.sender_id = u_sender.id
            INNER JOIN users u_rep ON cr.reporter_id = u_rep.id
            LEFT JOIN conversations c ON c.contact_request_id = cr.id
            WHERE cr.request_code = :code OR cr.id = :id_num
            LIMIT 1
        ");
        $idNum = ctype_digit($requestCode) ? (int)$requestCode : 0;
        $stmt->execute(['code' => $requestCode, 'id_num' => $idNum]);
        $req = $stmt->fetch();

        if (!$req) {
            sendJsonResponse(404, ['success' => false, 'error' => 'Contact request not found.']);
        }

        // Authorization check: User must be sender or reporter (or admin)
        $senderId = (int)$req['sender_id'];
        $reporterId = (int)$req['reporter_id'];

        if ($currentUserId !== $senderId && $currentUserId !== $reporterId && !$isAdmin) {
            sendJsonResponse(403, ['success' => false, 'error' => 'You are not authorized to view this contact request.']);
        }

        // Image for the item
        $imgStmt = $pdo->prepare("SELECT image FROM item_images WHERE item_id = :item_id ORDER BY image_order ASC LIMIT 1");
        $imgStmt->execute(['item_id' => $req['item_id']]);
        $itemImgRow = $imgStmt->fetch();
        $itemImg = $itemImgRow['image'] ?? null;
        $itemImgUrl = getItemThumbnailUrl($itemImg);

        sendJsonResponse(200, [
            'success' => true,
            'data' => [
                'id'                 => (int)$req['id'],
                'request_code'       => $req['request_code'],
                'initial_message'    => $req['initial_message'],
                'status'             => $req['status'],
                'rejection_reason'   => $req['rejection_reason'],
                'created_at'         => $req['created_at'],
                'created_at_rel'     => getRelativeTime($req['created_at']),
                'responded_at'       => $req['responded_at'],
                'is_reporter'        => ($currentUserId === $reporterId),
                'is_sender'          => ($currentUserId === $senderId),
                'conversation_code'  => $req['conversation_code'] ?? null,
                'item' => [
                    'id'             => (int)$req['item_id'],
                    'item_code'      => $req['item_code'],
                    'title'          => $req['item_title'],
                    'type'           => $req['item_type'],
                    'category'       => $req['item_category'],
                    'location'       => $req['item_location'],
                    'status'         => $req['item_status'],
                    'image_url'      => $itemImgUrl
                ],
                'sender' => [
                    'name'           => $req['sender_name'],
                    'department'     => $req['sender_department'],
                    'avatar_url'     => getProfileImageUrl($req['sender_avatar']),
                    'user_code'      => $req['sender_code']
                ],
                'reporter' => [
                    'name'           => $req['reporter_name'],
                    'department'     => $req['reporter_department'],
                    'avatar_url'     => getProfileImageUrl($req['reporter_avatar']),
                    'user_code'      => $req['reporter_code']
                ]
            ]
        ]);

    } catch (Throwable $e) {
        error_log("Contact request get error: " . $e->getMessage());
        sendJsonResponse(500, ['success' => false, 'error' => 'Failed to retrieve contact request details.']);
    }
}

// ==============================================================================
// ACTION: LIST CONTACT REQUESTS (GET)
// ==============================================================================
if ($action === 'list') {
    try {
        // Incoming requests (where current user is reporter)
        $incStmt = $pdo->prepare("
            SELECT cr.id, cr.request_code, cr.initial_message, cr.status, cr.created_at, cr.responded_at,
                   i.item_code, i.title AS item_title, i.report_type AS item_type,
                   u.name AS sender_name, u.department AS sender_department, u.profile_image AS sender_avatar,
                   c.conversation_code
            FROM contact_requests cr
            INNER JOIN items i ON cr.item_id = i.id
            INNER JOIN users u ON cr.sender_id = u.id
            LEFT JOIN conversations c ON c.contact_request_id = cr.id
            WHERE cr.reporter_id = :uid
            ORDER BY cr.created_at DESC
        ");
        $incStmt->execute(['uid' => $currentUserId]);
        $incoming = $incStmt->fetchAll();

        // Outgoing requests (where current user is sender)
        $outStmt = $pdo->prepare("
            SELECT cr.id, cr.request_code, cr.initial_message, cr.status, cr.created_at, cr.responded_at,
                   i.item_code, i.title AS item_title, i.report_type AS item_type,
                   u.name AS reporter_name, u.department AS reporter_department, u.profile_image AS reporter_avatar,
                   c.conversation_code
            FROM contact_requests cr
            INNER JOIN items i ON cr.item_id = i.id
            INNER JOIN users u ON cr.reporter_id = u.id
            LEFT JOIN conversations c ON c.contact_request_id = cr.id
            WHERE cr.sender_id = :uid
            ORDER BY cr.created_at DESC
        ");
        $outStmt->execute(['uid' => $currentUserId]);
        $outgoing = $outStmt->fetchAll();

        // Format
        $formatList = function (array $rows, bool $isIncoming): array {
            $formatted = [];
            foreach ($rows as $r) {
                $otherName = $isIncoming ? $r['sender_name'] : $r['reporter_name'];
                $otherDept = $isIncoming ? $r['sender_department'] : $r['reporter_department'];
                $otherAvatar = $isIncoming ? $r['sender_avatar'] : $r['reporter_avatar'];

                $formatted[] = [
                    'id'                => (int)$r['id'],
                    'request_code'      => $r['request_code'],
                    'message_snippet'   => mb_substr((string)$r['initial_message'], 0, 80) . (mb_strlen((string)$r['initial_message']) > 80 ? '...' : ''),
                    'status'            => $r['status'],
                    'created_at'        => $r['created_at'],
                    'created_at_rel'    => getRelativeTime($r['created_at']),
                    'item_code'         => $r['item_code'],
                    'item_title'        => $r['item_title'],
                    'item_type'         => $r['item_type'],
                    'other_party_name'  => $otherName,
                    'other_party_dept'  => $otherDept,
                    'other_party_avatar'=> getProfileImageUrl($otherAvatar),
                    'conversation_code' => $r['conversation_code'] ?? null
                ];
            }
            return $formatted;
        };

        sendJsonResponse(200, [
            'success' => true,
            'data' => [
                'incoming' => $formatList($incoming, true),
                'outgoing' => $formatList($outgoing, false),
                'pending_incoming_count' => count(array_filter($incoming, fn($x) => $x['status'] === 'pending'))
            ]
        ]);

    } catch (Throwable $e) {
        error_log("Contact request list error: " . $e->getMessage());
        sendJsonResponse(500, ['success' => false, 'error' => 'Failed to retrieve contact requests list.']);
    }
}

// ==============================================================================
// ACTION: RESPOND TO REQUEST (ACCEPT / REJECT) (POST)
// ==============================================================================
if ($action === 'respond') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(405, ['success' => false, 'error' => 'Method not allowed']);
    }

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($token)) {
        sendJsonResponse(403, ['success' => false, 'error' => 'Invalid security token. Please refresh and try again.']);
    }

    $requestCode = trim((string)($_POST['request_code'] ?? $_POST['id'] ?? ''));
    $decision = trim(strtolower((string)($_POST['decision'] ?? $_POST['response'] ?? '')));
    $rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));

    if ($requestCode === '' || !in_array($decision, ['accept', 'reject'], true)) {
        sendJsonResponse(422, ['success' => false, 'error' => 'Valid request code and decision (accept or reject) are required.']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT cr.*, i.title AS item_title, i.item_code 
            FROM contact_requests cr
            INNER JOIN items i ON cr.item_id = i.id
            WHERE cr.request_code = :code OR cr.id = :id_num
            LIMIT 1
        ");
        $idNum = ctype_digit($requestCode) ? (int)$requestCode : 0;
        $stmt->execute(['code' => $requestCode, 'id_num' => $idNum]);
        $request = $stmt->fetch();

        if (!$request) {
            sendJsonResponse(404, ['success' => false, 'error' => 'Contact request not found.']);
        }

        // Rule: Only the item reporter can respond
        $reporterId = (int)$request['reporter_id'];
        $senderId = (int)$request['sender_id'];
        $requestId = (int)$request['id'];
        $numericItemId = (int)$request['item_id'];
        $itemTitle = (string)$request['item_title'];

        if ($currentUserId !== $reporterId && !$isAdmin) {
            sendJsonResponse(403, ['success' => false, 'error' => 'Only the item reporter can accept or reject this request.']);
        }

        // If request is already accepted
        if ($request['status'] === 'accepted') {
            $convStmt = $pdo->prepare("SELECT conversation_code FROM conversations WHERE contact_request_id = :crid LIMIT 1");
            $convStmt->execute(['crid' => $requestId]);
            $convRow = $convStmt->fetch();

            sendJsonResponse(200, [
                'success' => true,
                'message' => 'This request was already accepted.',
                'data' => [
                    'status'            => 'accepted',
                    'conversation_code' => $convRow['conversation_code'] ?? null
                ]
            ]);
        }

        // If already rejected
        if ($request['status'] === 'rejected') {
            sendJsonResponse(400, ['success' => false, 'error' => 'This contact request has already been declined.']);
        }

        $pdo->beginTransaction();

        if ($decision === 'accept') {
            // 1. Update contact_requests to accepted
            $upStmt = $pdo->prepare("
                UPDATE contact_requests 
                SET status = 'accepted', responded_at = NOW() 
                WHERE id = :id
            ");
            $upStmt->execute(['id' => $requestId]);

            // 2. Check or create conversation (idempotent)
            $convCheck = $pdo->prepare("SELECT id, conversation_code FROM conversations WHERE contact_request_id = :crid LIMIT 1");
            $convCheck->execute(['crid' => $requestId]);
            $existingConv = $convCheck->fetch();

            if ($existingConv) {
                $convId = (int)$existingConv['id'];
                $convCode = (string)$existingConv['conversation_code'];
            } else {
                $convCode = generatePublicCode('CNV', $pdo, 'conversations', 'conversation_code');

                $createConv = $pdo->prepare("
                    INSERT INTO conversations (
                        conversation_code,
                        contact_request_id,
                        item_id,
                        participant_one_id,
                        participant_two_id,
                        status,
                        created_at
                    ) VALUES (
                        :code,
                        :crid,
                        :item_id,
                        :p1,
                        :p2,
                        'active',
                        NOW()
                    )
                ");
                $createConv->execute([
                    'code'    => $convCode,
                    'crid'    => $requestId,
                    'item_id' => $numericItemId,
                    'p1'      => $senderId,
                    'p2'      => $reporterId
                ]);
                $convId = (int)$pdo->lastInsertId();

                // Insert the initial contact message as the first conversation message
                $msgCode = generatePublicCode('MSG', $pdo, 'messages', 'message_code');
                $initMsgStmt = $pdo->prepare("
                    INSERT INTO messages (
                        message_code,
                        conversation_id,
                        sender_id,
                        message,
                        is_read,
                        read_at,
                        created_at
                    ) VALUES (
                        :code,
                        :cid,
                        :sender_id,
                        :msg,
                        1,
                        NOW(),
                        :created_at
                    )
                ");
                $initMsgStmt->execute([
                    'code'       => $msgCode,
                    'cid'        => $convId,
                    'sender_id'  => $senderId,
                    'msg'        => $request['initial_message'],
                    'created_at' => $request['created_at']
                ]);
            }

            // 3. Notify the requester
            $repName = (string)($_SESSION['name'] ?? 'The reporter');
            $notifTitle = "Contact Request Accepted";
            $notifMsg = "Your contact request regarding \"{$itemTitle}\" was accepted by {$repName}.";

            createNotification(
                $pdo,
                $senderId,
                $notifTitle,
                $notifMsg,
                'success',
                'contact',
                $numericItemId,
                null,
                $requestId,
                $convId
            );

            $pdo->commit();

            sendJsonResponse(200, [
                'success' => true,
                'message' => 'Contact request accepted. Conversation channel created.',
                'data' => [
                    'status'            => 'accepted',
                    'conversation_code' => $convCode
                ]
            ]);

        } else {
            // Reject decision
            $upStmt = $pdo->prepare("
                UPDATE contact_requests 
                SET status = 'rejected', 
                    rejection_reason = :reason, 
                    responded_at = NOW() 
                WHERE id = :id
            ");
            $upStmt->execute([
                'id'     => $requestId,
                'reason' => $rejectionReason !== '' ? $rejectionReason : null
            ]);

            // Notify requester
            $repName = (string)($_SESSION['name'] ?? 'The reporter');
            $notifTitle = "Contact Request Declined";
            $notifMsg = "Your contact request regarding \"{$itemTitle}\" was declined by {$repName}.";

            createNotification(
                $pdo,
                $senderId,
                $notifTitle,
                $notifMsg,
                'warning',
                'contact',
                $numericItemId,
                null,
                $requestId,
                null
            );

            $pdo->commit();

            sendJsonResponse(200, [
                'success' => true,
                'message' => 'Contact request declined.',
                'data' => [
                    'status' => 'rejected'
                ]
            ]);
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Contact request respond error: " . $e->getMessage());
        sendJsonResponse(500, ['success' => false, 'error' => 'An error occurred while processing your response.']);
    }
}

// Fallback for unknown action
sendJsonResponse(400, ['success' => false, 'error' => 'Invalid action specified.']);
