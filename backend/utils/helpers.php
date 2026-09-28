<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application Helper Functions
|--------------------------------------------------------------------------
|
| Reusable utility functions for formatting and data manipulation.
|
*/

/**
 * Returns a human-readable relative time string.
 * 
 * @param string|null $datetime MySQL timestamp string
 * @return string Formatted relative time
 */
function getRelativeTime(?string $datetime): string {
    if (!$datetime) return 'Unknown';
    
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    
    $minutes = (int) round($diff / 60);
    if ($minutes < 60) return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    
    $hours = (int) round($diff / 3600);
    if ($hours < 24) return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    
    $days = (int) round($diff / 86400);
    if ($days == 1) return 'Yesterday';
    if ($days < 7) return $days . ' days ago';
    
    return date('M j, Y', $time);
}

/**
 * Returns structured data for rendering a status badge.
 * Does NOT return raw HTML.
 * 
 * @param string $status Current item status (pending, active, claimed, returned, closed)
 * @param string $report_type The type of report (lost, found)
 * @return array Associative array with 'class' and 'text' keys
 */
function getStatusDisplay(string $status, string $report_type): array {
    if ($status === 'returned') {
        return ['class' => 'badge-returned', 'text' => 'Returned'];
    }
    
    if ($status === 'closed') {
        return ['class' => 'badge-closed', 'text' => 'Closed'];
    }
    
    // For pending/active/claimed, base it on the report_type
    if ($report_type === 'lost') {
        return ['class' => 'badge-lost', 'text' => 'Lost'];
    }
    
    if ($report_type === 'found') {
        return ['class' => 'badge-found', 'text' => 'Found'];
    }
    
    return ['class' => 'badge-default', 'text' => 'Unknown'];
}

/**
 * Global fallback for user profile images.
 * Handles cases where profile image is null, empty, or missing.
 * 
 * @param string|null $profileImage
 * @return string
 */
function getProfileImageUrl(?string $profileImage): string {
    $defaultImageUrl = BASE_URL . '/profile/default_user.png';
    
    if (empty($profileImage) || $profileImage === 'default-profile.png' || $profileImage === 'default_user.png') {
        return $defaultImageUrl;
    }

    $imagePath = ltrim($profileImage, '/');
    
    // Safety check: if file doesn't exist on server, use fallback
    $serverPath = realpath(__DIR__ . '/../../') . '/' . $imagePath;
    if (!file_exists($serverPath)) {
        return $defaultImageUrl;
    }
    
    return BASE_URL . '/' . $imagePath;
}

/**
 * Global helper for item thumbnails.
 * Resolves item image URL with fallback to default placeholder.
 * Handles paths starting with 'http', 'assets/', 'uploads/', or just filename.
 * 
 * @param string|null $image
 * @return string
 */
function getItemThumbnailUrl(?string $image): string {
    $defaultImageUrl = BASE_URL . '/assets/images/default_no_image.png';
    
    if (empty($image) || $image === 'default_no_image.png') {
        return $defaultImageUrl;
    }
    
    if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
        return $image;
    }
    
    $cleanPath = ltrim($image, '/');
    $rootDir = realpath(__DIR__ . '/../../');
    
    // Check if path already starts with assets/ or uploads/
    if (str_starts_with($cleanPath, 'assets/') || str_starts_with($cleanPath, 'uploads/')) {
        if ($rootDir && file_exists($rootDir . '/' . $cleanPath)) {
            return BASE_URL . '/' . $cleanPath;
        }
    }
    
    // Check inside assets/uploads/items/
    if ($rootDir && file_exists($rootDir . '/assets/uploads/items/' . basename($cleanPath))) {
        return BASE_URL . '/assets/uploads/items/' . basename($cleanPath);
    }
    
    // Check inside uploads/items/
    if ($rootDir && file_exists($rootDir . '/uploads/items/' . basename($cleanPath))) {
        return BASE_URL . '/uploads/items/' . basename($cleanPath);
    }
    
    // Direct path check
    if ($rootDir && file_exists($rootDir . '/' . $cleanPath)) {
        return BASE_URL . '/' . $cleanPath;
    }
    
    return $defaultImageUrl;
}

/**
 * Generates a unique 13-character public code (e.g. REQ-123456789, CNV-123456789).
 * 
 * @param string $prefix 3-letter prefix
 * @param PDO $pdo
 * @param string $table
 * @param string $column
 * @return string
 */
function generatePublicCode(string $prefix, PDO $pdo, string $table, string $column): string {
    $prefix = strtoupper(trim($prefix));
    $maxAttempts = 10;
    
    for ($i = 0; $i < $maxAttempts; $i++) {
        $code = $prefix . '-' . mt_rand(100000000, 999999999);
        $stmt = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }
    
    // Fallback if random attempts collided
    return $prefix . '-' . substr((string)time(), -9);
}

/**
 * Validates a submitted CSRF token against the current session.
 * 
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || empty($token)) {
        return false;
    }
    return hash_equals($sessionToken, (string)$token);
}

/**
 * Creates a system notification for a recipient user.
 * 
 * @param PDO $pdo
 * @param int $userId
 * @param string $title
 * @param string $message
 * @param string $type success|info|warning|error
 * @param string $category account|report|claim|admin|system|contact
 * @param int|null $itemId
 * @param int|null $claimId
 * @param int|null $contactRequestId
 * @param int|null $conversationId
 * @return string The generated notification_code
 */
function createNotification(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    string $type = 'info',
    string $category = 'system',
    ?int $itemId = null,
    ?int $claimId = null,
    ?int $contactRequestId = null,
    ?int $conversationId = null
): string {
    $notificationCode = generatePublicCode('NTF', $pdo, 'notifications', 'notification_code');
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            notification_code,
            user_id,
            item_id,
            claim_id,
            contact_request_id,
            conversation_id,
            title,
            message,
            type,
            category,
            is_read,
            created_at
        ) VALUES (
            :code,
            :user_id,
            :item_id,
            :claim_id,
            :contact_request_id,
            :conversation_id,
            :title,
            :message,
            :type,
            :category,
            0,
            NOW()
        )
    ");
    
    $stmt->execute([
        'code'               => $notificationCode,
        'user_id'            => $userId,
        'item_id'            => $itemId,
        'claim_id'           => $claimId,
        'contact_request_id' => $contactRequestId,
        'conversation_id'    => $conversationId,
        'title'              => mb_substr($title, 0, 150),
        'message'            => $message,
        'type'               => $type,
        'category'           => $category
    ]);
    
    return $notificationCode;
}

/**
 * Checks if the currently authenticated user has administrator privileges.
 *
 * @return bool
 */
function isAdmin(): bool {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    $role = $_SESSION['role'] ?? null;
    $adminRole = defined('ROLE_ADMIN') ? ROLE_ADMIN : 'admin';
    return $role === $adminRole;
}



