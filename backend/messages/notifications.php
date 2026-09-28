<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * LOSTLINK NOTIFICATIONS CONTROLLER
 * ==============================================================================
 * Handles:
 * - Listing recent notifications for the logged-in user
 * - Returning the unread notifications count
 * - Marking notifications as read
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

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || empty($_SESSION['user_id'])) {
    sendJsonResponse(401, ['success' => false, 'error' => 'Please log in to continue.']);
}

$currentUserId = (int)$_SESSION['user_id'];
$action = trim((string)($_REQUEST['action'] ?? 'list'));

// ==============================================================================
// ACTION: LIST NOTIFICATIONS (GET)
// ==============================================================================
if ($action === 'list') {
    try {
        // Fetch unread count
        $cntStmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :uid AND is_read = 0");
        $cntStmt->execute(['uid' => $currentUserId]);
        $unreadCount = (int)($cntStmt->fetch()['unread_count'] ?? 0);

        // Fetch recent 15 notifications
        $stmt = $pdo->prepare("
            SELECT n.*,
                   i.item_code, i.title AS item_title,
                   cr.request_code,
                   c.conversation_code
            FROM notifications n
            LEFT JOIN items i ON n.item_id = i.id
            LEFT JOIN contact_requests cr ON n.contact_request_id = cr.id
            LEFT JOIN conversations c ON n.conversation_id = c.id
            WHERE n.user_id = :uid
            ORDER BY n.created_at DESC
            LIMIT 15
        ");
        $stmt->execute(['uid' => $currentUserId]);
        $rows = $stmt->fetchAll();

        $notifications = [];
        foreach ($rows as $r) {
            // Determine target URL
            $targetUrl = '#';
            if (!empty($r['conversation_code'])) {
                $targetUrl = BASE_URL . '/messages/index.php?c=' . urlencode((string)$r['conversation_code']);
            } elseif (!empty($r['request_code'])) {
                $targetUrl = BASE_URL . '/messages/index.php?r=' . urlencode((string)$r['request_code']);
            } elseif (!empty($r['item_code'])) {
                $targetUrl = BASE_URL . '/items/browse.php?item=' . urlencode((string)$r['item_code']);
            }

            $notifications[] = [
                'id'                => (int)$r['id'],
                'notification_code' => (string)$r['notification_code'],
                'title'             => (string)$r['title'],
                'message'           => (string)$r['message'],
                'type'              => (string)$r['type'],
                'category'          => (string)$r['category'],
                'is_read'           => (bool)$r['is_read'],
                'target_url'        => $targetUrl,
                'created_at'        => (string)$r['created_at'],
                'created_at_rel'    => getRelativeTime((string)$r['created_at'])
            ];
        }

        sendJsonResponse(200, [
            'success'      => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count'  => $unreadCount
            ]
        ]);

    } catch (Throwable $e) {
        error_log("Notifications list error: " . $e->getMessage());
        sendJsonResponse(500, ['success' => false, 'error' => 'Failed to load notifications.']);
    }
}

// ==============================================================================
// ACTION: MARK NOTIFICATIONS READ (POST)
// ==============================================================================
if ($action === 'mark_read') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(405, ['success' => false, 'error' => 'Method not allowed.']);
    }

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($token)) {
        sendJsonResponse(403, ['success' => false, 'error' => 'Invalid session security token.']);
    }

    $notificationId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $markAll = !empty($_POST['all']);

    try {
        if ($markAll) {
            $upStmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :uid AND is_read = 0");
            $upStmt->execute(['uid' => $currentUserId]);
        } elseif ($notificationId > 0) {
            $upStmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :uid");
            $upStmt->execute(['id' => $notificationId, 'uid' => $currentUserId]);
        }

        sendJsonResponse(200, ['success' => true, 'message' => 'Notifications updated.']);
    } catch (Throwable $e) {
        error_log("Notifications mark_read error: " . $e->getMessage());
        sendJsonResponse(500, ['success' => false, 'error' => 'Failed to update notification state.']);
    }
}

sendJsonResponse(400, ['success' => false, 'error' => 'Invalid action.']);
