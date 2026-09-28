<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * LOSTLINK ANNOUNCEMENTS API CONTROLLER
 * ==============================================================================
 * Centralized endpoint for announcement actions:
 * - create (Admin)
 * - update (Admin)
 * - toggle_pin (Admin)
 * - archive / unarchive (Admin)
 * - delete (Admin)
 * - get (Public / Admin)
 * 
 * Enforces server-side authentication, admin authorization, CSRF validation,
 * and executes the on-access scheduling transition engine.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../backend/utils/helpers.php';
require_once __DIR__ . '/../../backend/auth/auth_check.php';

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
if ($currentUserId > 0 && isset($pdo)) {
    try {
        $uRoleStmt = $pdo->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
        $uRoleStmt->execute(['id' => $currentUserId]);
        $fetchedRole = $uRoleStmt->fetchColumn();
        if ($fetchedRole) {
            $_SESSION['role'] = (string)$fetchedRole;
        }
    } catch (Throwable $e) {}
}
$currentUserRole = (string)($_SESSION['role'] ?? '');
$isAdmin = ($currentUserRole === ROLE_ADMIN || $currentUserRole === 'admin');

/**
 * Executes the on-access scheduling transition engine.
 * Automatically transitions due scheduled announcements to 'published'.
 */
function processDueAnnouncements(PDO $pdo): void {
    try {
        $stmt = $pdo->prepare("
            UPDATE announcements
            SET status = 'published',
                published_at = COALESCE(scheduled_at, NOW())
            WHERE status = 'scheduled'
              AND scheduled_at IS NOT NULL
              AND scheduled_at <= NOW()
        ");
        $stmt->execute();
    } catch (Throwable $e) {
        // Silently fail if table not available
    }
}

// Always run scheduling transition check on access
processDueAnnouncements($pdo);

$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

if ($action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing action parameter.']);
    exit;
}

try {

    // =========================================================================
    // ACTION: GET (View single announcement details)
    // =========================================================================
    if ($action === 'get') {
        $code = trim((string)($_GET['code'] ?? $_POST['code'] ?? ''));
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

        if ($code === '' && $id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Announcement code or ID is required.']);
            exit;
        }

        $query = "
            SELECT a.*, u.name AS author_name, u.role AS author_role
            FROM announcements a
            JOIN users u ON a.user_id = u.id
            WHERE (a.announcement_code = :code OR a.id = :id)
        ";

        // If not admin, restrict to active published and non-expired announcements
        if (!$isAdmin) {
            $query .= " AND a.status = 'published' AND (a.expires_at IS NULL OR a.expires_at > NOW())";
        }

        $query .= " LIMIT 1";

        $stmt = $pdo->prepare($query);
        $stmt->execute(['code' => $code, 'id' => $id]);
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$announcement) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Announcement not found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'announcement' => [
                'id' => (int)$announcement['id'],
                'announcement_code' => $announcement['announcement_code'],
                'title' => $announcement['title'],
                'content' => $announcement['content'],
                'category' => $announcement['category'],
                'priority' => $announcement['priority'],
                'is_pinned' => (int)$announcement['is_pinned'],
                'status' => $announcement['status'],
                'scheduled_at' => $announcement['scheduled_at'],
                'expires_at' => $announcement['expires_at'],
                'published_at' => $announcement['published_at'],
                'created_at' => $announcement['created_at'],
                'updated_at' => $announcement['updated_at'],
                'author_name' => $announcement['author_name'],
                'author_role' => $announcement['author_role'],
                'relative_published' => getRelativeTime($announcement['published_at'] ?? $announcement['created_at'])
            ]
        ]);
        exit;
    }

    // =========================================================================
    // ALL SUBSEQUENT ACTIONS ARE ADMIN ONLY
    // =========================================================================
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden: Administrator privileges required.']);
        exit;
    }

    // CSRF verification for all state-modifying requests
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF security token.']);
        exit;
    }

    // Allowed enum values
    $allowedCategories = ['college', 'lostlink', 'system', 'event', 'important'];
    $allowedPriorities = ['normal', 'high', 'urgent'];
    $allowedStatuses = ['draft', 'published', 'scheduled', 'archived'];

    // =========================================================================
    // ACTION: CREATE (Admin creates an announcement)
    // =========================================================================
    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $category = strtolower(trim((string)($_POST['category'] ?? 'lostlink')));
        $priority = strtolower(trim((string)($_POST['priority'] ?? 'normal')));
        $isPinned = !empty($_POST['is_pinned']) ? 1 : 0;
        $status = strtolower(trim((string)($_POST['status'] ?? 'published')));
        $scheduledAt = !empty($_POST['scheduled_at']) ? trim((string)$_POST['scheduled_at']) : null;
        $expiresAt = !empty($_POST['expires_at']) ? trim((string)$_POST['expires_at']) : null;

        // Validations
        if ($title === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Title is required.']);
            exit;
        }
        if (mb_strlen($title) > 200) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Title must not exceed 200 characters.']);
            exit;
        }
        if ($content === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Announcement content is required.']);
            exit;
        }
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'lostlink';
        }
        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = 'normal';
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'published';
        }

        // Format dates
        $scheduledSql = null;
        if ($status === 'scheduled') {
            if (empty($scheduledAt)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Scheduled publication date and time is required when status is scheduled.']);
                exit;
            }
            $schedTime = strtotime($scheduledAt);
            if ($schedTime === false) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Invalid scheduled date format.']);
                exit;
            }
            $scheduledSql = date('Y-m-d H:i:s', $schedTime);
            // If the scheduled time is already in the past or now, publish immediately
            if ($schedTime <= time()) {
                $status = 'published';
            }
        }

        $expiresSql = null;
        if (!empty($expiresAt)) {
            $expTime = strtotime($expiresAt);
            if ($expTime !== false) {
                $expiresSql = date('Y-m-d H:i:s', $expTime);
            }
        }

        $publishedSql = ($status === 'published') ? date('Y-m-d H:i:s') : null;

        $announcementCode = generatePublicCode('ANC', $pdo, 'announcements', 'announcement_code');

        $stmt = $pdo->prepare("
            INSERT INTO announcements (
                announcement_code,
                user_id,
                title,
                content,
                category,
                priority,
                is_pinned,
                status,
                scheduled_at,
                expires_at,
                published_at,
                created_at
            ) VALUES (
                :code,
                :user_id,
                :title,
                :content,
                :category,
                :priority,
                :is_pinned,
                :status,
                :scheduled_at,
                :expires_at,
                :published_at,
                NOW()
            )
        ");

        $stmt->execute([
            'code' => $announcementCode,
            'user_id' => $currentUserId,
            'title' => $title,
            'content' => $content,
            'category' => $category,
            'priority' => $priority,
            'is_pinned' => $isPinned,
            'status' => $status,
            'scheduled_at' => $scheduledSql,
            'expires_at' => $expiresSql,
            'published_at' => $publishedSql
        ]);

        $newId = (int)$pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => $newId,
            'announcement_code' => $announcementCode,
            'status' => $status,
            'message' => ($status === 'published') 
                ? 'Announcement published successfully.' 
                : (($status === 'scheduled') ? 'Announcement scheduled successfully.' : 'Announcement saved as draft.')
        ]);
        exit;
    }

    // =========================================================================
    // ACTION: UPDATE (Admin edits an announcement)
    // =========================================================================
    if ($action === 'update') {
        $code = trim((string)($_POST['announcement_code'] ?? $_POST['code'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);

        if ($code === '' && $id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Announcement identifier is required.']);
            exit;
        }

        $fetchStmt = $pdo->prepare("SELECT * FROM announcements WHERE announcement_code = :code OR id = :id LIMIT 1");
        $fetchStmt->execute(['code' => $code, 'id' => $id]);
        $existing = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Announcement not found.']);
            exit;
        }

        $title = trim((string)($_POST['title'] ?? $existing['title']));
        $content = trim((string)($_POST['content'] ?? $existing['content']));
        $category = strtolower(trim((string)($_POST['category'] ?? $existing['category'])));
        $priority = strtolower(trim((string)($_POST['priority'] ?? $existing['priority'])));
        $isPinned = isset($_POST['is_pinned']) ? (!empty($_POST['is_pinned']) ? 1 : 0) : (int)$existing['is_pinned'];
        $status = strtolower(trim((string)($_POST['status'] ?? $existing['status'])));
        $scheduledAt = isset($_POST['scheduled_at']) ? trim((string)$_POST['scheduled_at']) : $existing['scheduled_at'];
        $expiresAt = isset($_POST['expires_at']) ? trim((string)$_POST['expires_at']) : $existing['expires_at'];

        if ($title === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Title cannot be empty.']);
            exit;
        }
        if (mb_strlen($title) > 200) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Title must not exceed 200 characters.']);
            exit;
        }
        if ($content === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Content cannot be empty.']);
            exit;
        }
        if (!in_array($category, $allowedCategories, true)) {
            $category = $existing['category'];
        }
        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = $existing['priority'];
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $status = $existing['status'];
        }

        $scheduledSql = null;
        if ($status === 'scheduled') {
            if (empty($scheduledAt)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Scheduled publication date is required.']);
                exit;
            }
            $schedTime = strtotime($scheduledAt);
            if ($schedTime === false) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Invalid scheduled date format.']);
                exit;
            }
            $scheduledSql = date('Y-m-d H:i:s', $schedTime);
            if ($schedTime <= time()) {
                $status = 'published';
            }
        }

        $expiresSql = null;
        if (!empty($expiresAt)) {
            $expTime = strtotime($expiresAt);
            if ($expTime !== false) {
                $expiresSql = date('Y-m-d H:i:s', $expTime);
            }
        }

        // Determine published_at
        $publishedSql = $existing['published_at'];
        if ($status === 'published' && empty($publishedSql)) {
            $publishedSql = date('Y-m-d H:i:s');
        }

        $updateStmt = $pdo->prepare("
            UPDATE announcements
            SET title = :title,
                content = :content,
                category = :category,
                priority = :priority,
                is_pinned = :is_pinned,
                status = :status,
                scheduled_at = :scheduled_at,
                expires_at = :expires_at,
                published_at = :published_at
            WHERE id = :id
        ");

        $updateStmt->execute([
            'title' => $title,
            'content' => $content,
            'category' => $category,
            'priority' => $priority,
            'is_pinned' => $isPinned,
            'status' => $status,
            'scheduled_at' => $scheduledSql,
            'expires_at' => $expiresSql,
            'published_at' => $publishedSql,
            'id' => (int)$existing['id']
        ]);

        echo json_encode([
            'success' => true,
            'announcement_code' => $existing['announcement_code'],
            'message' => 'Announcement updated successfully.'
        ]);
        exit;
    }

    // =========================================================================
    // ACTION: TOGGLE PIN (Admin pins or unpins an announcement)
    // =========================================================================
    if ($action === 'toggle_pin') {
        $code = trim((string)($_POST['announcement_code'] ?? $_POST['code'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);

        $fetchStmt = $pdo->prepare("SELECT id, is_pinned FROM announcements WHERE announcement_code = :code OR id = :id LIMIT 1");
        $fetchStmt->execute(['code' => $code, 'id' => $id]);
        $row = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Announcement not found.']);
            exit;
        }

        $newPin = ((int)$row['is_pinned'] === 1) ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE announcements SET is_pinned = :pin WHERE id = :id");
        $stmt->execute(['pin' => $newPin, 'id' => $row['id']]);

        echo json_encode([
            'success' => true,
            'is_pinned' => $newPin,
            'message' => $newPin ? 'Announcement pinned to top.' : 'Announcement unpinned.'
        ]);
        exit;
    }

    // =========================================================================
    // ACTION: ARCHIVE (Admin archives an announcement)
    // =========================================================================
    if ($action === 'archive') {
        $code = trim((string)($_POST['announcement_code'] ?? $_POST['code'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("UPDATE announcements SET status = 'archived' WHERE announcement_code = :code OR id = :id");
        $stmt->execute(['code' => $code, 'id' => $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Announcement not found or already archived.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Announcement moved to archive.'
        ]);
        exit;
    }

    // =========================================================================
    // ACTION: UNARCHIVE (Admin restores an archived announcement)
    // =========================================================================
    if ($action === 'unarchive') {
        $code = trim((string)($_POST['announcement_code'] ?? $_POST['code'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("UPDATE announcements SET status = 'published', published_at = COALESCE(published_at, NOW()) WHERE announcement_code = :code OR id = :id");
        $stmt->execute(['code' => $code, 'id' => $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Announcement not found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Announcement restored to active notices.'
        ]);
        exit;
    }

    // =========================================================================
    // ACTION: PUBLISH (Admin publishes draft or scheduled notice immediately)
    // =========================================================================
    if ($action === 'publish') {
        $code = trim((string)($_POST['announcement_code'] ?? $_POST['code'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);

        if ($code === '' && $id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Announcement identifier is required.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE announcements 
            SET status = 'published', 
                published_at = COALESCE(published_at, NOW()) 
            WHERE announcement_code = :code OR id = :id
        ");
        $stmt->execute(['code' => $code, 'id' => $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Announcement not found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Announcement published successfully.'
        ]);
        exit;
    }

    // =========================================================================
    // ACTION: DELETE (Admin permanently removes an announcement)
    // =========================================================================
    if ($action === 'delete') {
        $code = trim((string)($_POST['announcement_code'] ?? $_POST['code'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_code = :code OR id = :id");
        $stmt->execute(['code' => $code, 'id' => $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Announcement not found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Announcement deleted permanently.'
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
