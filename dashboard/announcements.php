<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * LOSTLINK ANNOUNCEMENTS SYSTEM
 * ==============================================================================
 * Official campus notice and communication board for LostLink.
 * - Normal users: Read-only, clean spacious vertical layout, pinned notices first.
 * - Administrators: Full management (create, publish, draft, schedule, pin, archive, delete).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../backend/utils/helpers.php';
require_once __DIR__ . '/../backend/auth/auth_check.php';

// Reliable role synchronization from database for authenticated user
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
if ($currentUserId > 0 && isset($pdo)) {
    try {
        $uRoleStmt = $pdo->prepare("SELECT role, name FROM users WHERE id = :id LIMIT 1");
        $uRoleStmt->execute(['id' => $currentUserId]);
        if ($uRoleData = $uRoleStmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($uRoleData['role'])) {
                $_SESSION['role'] = (string)$uRoleData['role'];
            }
            if (!empty($uRoleData['name'])) {
                $_SESSION['name'] = (string)$uRoleData['name'];
            }
        }
    } catch (Throwable $e) {}
}

$currentUserName = (string)($_SESSION['name'] ?? 'User');
$currentUserRole = (string)($_SESSION['role'] ?? ROLE_STUDENT);
$isAdmin = ($currentUserRole === ROLE_ADMIN || $currentUserRole === 'admin');

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

$current_page = 'announcements.php';

// =============================================================================
// ON-ACCESS SCHEDULING TRANSITION ENGINE
// =============================================================================
try {
    $pdo->exec("
        UPDATE announcements
        SET status = 'published',
            published_at = COALESCE(scheduled_at, NOW())
        WHERE status = 'scheduled'
          AND scheduled_at IS NOT NULL
          AND scheduled_at <= NOW()
    ");
} catch (Throwable $e) {
    // Graceful fallback if database temporarily unavailable
}

// Check for deep-link / single announcement view
$detailCode = trim((string)($_GET['id'] ?? $_GET['anc'] ?? ''));
$detailAnnouncement = null;

if ($detailCode !== '') {
    try {
        $detailQuery = "
            SELECT a.*, u.name AS author_name, u.role AS author_role
            FROM announcements a
            JOIN users u ON a.user_id = u.id
            WHERE (a.announcement_code = :code OR a.id = :id)
        ";
        if (!$isAdmin) {
            $detailQuery .= " AND a.status = 'published' AND (a.expires_at IS NULL OR a.expires_at > NOW())";
        }
        $detailQuery .= " LIMIT 1";

        $stmt = $pdo->prepare($detailQuery);
        $stmt->execute([
            'code' => $detailCode,
            'id' => is_numeric($detailCode) ? (int)$detailCode : 0
        ]);
        $detailAnnouncement = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $detailAnnouncement = null;
    }
}

// Counts for admin tabs
$counts = [
    'active' => 0,
    'draft' => 0,
    'scheduled' => 0,
    'archived' => 0
];
if ($isAdmin) {
    try {
        $cStmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN status = 'published' AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) AS active_cnt,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_cnt,
                SUM(CASE WHEN status = 'scheduled' AND (scheduled_at > NOW() OR scheduled_at IS NULL) THEN 1 ELSE 0 END) AS scheduled_cnt,
                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) AS archived_cnt
            FROM announcements
        ");
        if ($cRow = $cStmt->fetch(PDO::FETCH_ASSOC)) {
            $counts['active'] = (int)($cRow['active_cnt'] ?? 0);
            $counts['draft'] = (int)($cRow['draft_cnt'] ?? 0);
            $counts['scheduled'] = (int)($cRow['scheduled_cnt'] ?? 0);
            $counts['archived'] = (int)($cRow['archived_cnt'] ?? 0);
        }
    } catch (Throwable $e) {}
}

$rawTab = strtolower(trim((string)($_GET['tab'] ?? 'active')));
// Normal users cannot access non-active tabs
$currentTab = ($isAdmin && in_array($rawTab, ['draft', 'scheduled', 'archived', 'active'], true)) ? $rawTab : 'active';

// Fetch announcements list if not in detail view
$announcements = [];

if (!$detailAnnouncement) {
    try {
        if ($isAdmin && $currentTab === 'draft') {
            $query = "
                SELECT a.*, u.name AS author_name, u.role AS author_role
                FROM announcements a
                JOIN users u ON a.user_id = u.id
                WHERE a.status = 'draft'
                ORDER BY a.created_at DESC, a.id DESC
            ";
        } elseif ($isAdmin && $currentTab === 'scheduled') {
            $query = "
                SELECT a.*, u.name AS author_name, u.role AS author_role
                FROM announcements a
                JOIN users u ON a.user_id = u.id
                WHERE a.status = 'scheduled'
                ORDER BY COALESCE(a.scheduled_at, a.created_at) ASC, a.id DESC
            ";
        } elseif ($isAdmin && $currentTab === 'archived') {
            $query = "
                SELECT a.*, u.name AS author_name, u.role AS author_role
                FROM announcements a
                JOIN users u ON a.user_id = u.id
                WHERE a.status = 'archived'
                ORDER BY a.updated_at DESC, a.id DESC
            ";
        } else {
            // Active / published announcements: strictly published, non-expired, deterministic ordering
            $query = "
                SELECT a.*, u.name AS author_name, u.role AS author_role
                FROM announcements a
                JOIN users u ON a.user_id = u.id
                WHERE a.status = 'published'
                  AND (a.expires_at IS NULL OR a.expires_at > NOW())
                ORDER BY 
                    a.is_pinned DESC,
                    FIELD(a.priority, 'urgent', 'high', 'normal'),
                    COALESCE(a.published_at, a.created_at) DESC,
                    a.id DESC
            ";
        }
        $stmt = $pdo->query($query);
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $announcements = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo $detailAnnouncement ? htmlspecialchars($detailAnnouncement['title']) . ' - ' : ''; ?>Announcements - <?php echo APP_NAME; ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Theme initialization -->
    <script src="../assets/js/theme.js"></script>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="announcements.css?v=<?php echo time(); ?>">
</head>
<body class="app-body">

    <!-- Global Top Navigation -->
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="app-container">
        <!-- Shared Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="app-main-content anc-page-main" id="announcements-main-wrapper" data-base-url="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="anc-layout-container">

                <!-- ============================================================== -->
                <!-- HEADER & ACTIONS BAR                                           -->
                <!-- ============================================================== -->
                <div class="anc-page-header">
                    <div class="anc-header-text">
                        <h1 class="anc-page-title">Announcements</h1>
                        <p class="anc-page-subtitle">Official announcements and important updates from LostLink.</p>
                    </div>

                    <?php if ($isAdmin): ?>
                    <div class="anc-admin-header-controls">
                        <!-- State View Tabs for Admin -->
                        <div class="anc-admin-tabs" role="tablist" aria-label="Announcements View Toggle">
                            <a href="announcements.php" class="anc-tab-link <?php echo ($currentTab === 'active' && !$detailAnnouncement) ? 'active' : ''; ?>">
                                <span>Published</span>
                                <?php if ($counts['active'] > 0): ?>
                                    <span class="anc-tab-badge"><?php echo $counts['active']; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="announcements.php?tab=draft" class="anc-tab-link <?php echo ($currentTab === 'draft') ? 'active' : ''; ?>">
                                <span>Drafts</span>
                                <?php if ($counts['draft'] > 0): ?>
                                    <span class="anc-tab-badge anc-tab-badge-warning"><?php echo $counts['draft']; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="announcements.php?tab=scheduled" class="anc-tab-link <?php echo ($currentTab === 'scheduled') ? 'active' : ''; ?>">
                                <span>Scheduled</span>
                                <?php if ($counts['scheduled'] > 0): ?>
                                    <span class="anc-tab-badge anc-tab-badge-info"><?php echo $counts['scheduled']; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="announcements.php?tab=archived" class="anc-tab-link <?php echo ($currentTab === 'archived') ? 'active' : ''; ?>">
                                <span>Archived</span>
                                <?php if ($counts['archived'] > 0): ?>
                                    <span class="anc-tab-badge"><?php echo $counts['archived']; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>

                        <!-- Obvious + New Announcement Button for Admin -->
                        <button type="button" class="btn btn-primary" id="btn-open-create-modal">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>New Announcement</span>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Alert Message Banner Container (Empty by default, ZERO empty space when no message) -->
                <div id="anc-alert-container" class="anc-alert-container">
                    <?php if (!empty($_SESSION['success']) || !empty($_SESSION['error']) || !empty($_SESSION['info'])): 
                        $isErr = !empty($_SESSION['error']);
                        $flashText = (string)($_SESSION['error'] ?? $_SESSION['success'] ?? $_SESSION['info']);
                        unset($_SESSION['error'], $_SESSION['success'], $_SESSION['info']);
                    ?>
                    <div class="anc-alert-banner <?php echo $isErr ? 'is-error' : 'is-success'; ?>">
                        <span><?php echo htmlspecialchars($flashText, ENT_QUOTES, 'UTF-8'); ?></span>
                        <button type="button" class="anc-alert-close" aria-label="Dismiss message" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ============================================================== -->
                <!-- STATE A: DETAIL VIEW                                           -->
                <!-- ============================================================== -->
                <?php if ($detailAnnouncement): ?>
                    <div class="anc-detail-container">
                        <nav class="anc-detail-nav">
                            <a href="announcements.php<?php echo ($currentTab !== 'active') ? '?tab=' . urlencode($currentTab) : ''; ?>" class="anc-back-link">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <line x1="19" y1="12" x2="5" y2="12"></line>
                                    <polyline points="12 19 5 12 12 5"></polyline>
                                </svg>
                                <span>Back to Announcements</span>
                            </a>

                            <?php if ($isAdmin): ?>
                            <div class="anc-card-admin-actions">
                                <?php if ($detailAnnouncement['status'] === 'draft'): ?>
                                    <button type="button" class="btn btn-primary btn-xs btn-publish-announcement" data-code="<?php echo htmlspecialchars($detailAnnouncement['announcement_code']); ?>" title="Publish now">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <span>Publish Now</span>
                                    </button>
                                <?php elseif ($detailAnnouncement['status'] === 'scheduled'): ?>
                                    <button type="button" class="btn btn-primary btn-xs btn-publish-announcement" data-code="<?php echo htmlspecialchars($detailAnnouncement['announcement_code']); ?>" title="Publish immediately">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <span>Publish Now</span>
                                    </button>
                                <?php elseif ($detailAnnouncement['status'] === 'published'): ?>
                                    <button type="button" class="btn btn-outline btn-xs btn-toggle-pin <?php echo $detailAnnouncement['is_pinned'] ? 'is-active-pinned' : ''; ?>" data-code="<?php echo htmlspecialchars($detailAnnouncement['announcement_code']); ?>" data-pinned="<?php echo (int)$detailAnnouncement['is_pinned']; ?>" title="<?php echo $detailAnnouncement['is_pinned'] ? 'Unpin from top' : 'Pin to top'; ?>">
                                        <span>&#128204; <?php echo $detailAnnouncement['is_pinned'] ? 'Pinned' : 'Pin'; ?></span>
                                    </button>
                                    <button type="button" class="btn btn-outline btn-xs btn-archive-announcement" data-code="<?php echo htmlspecialchars($detailAnnouncement['announcement_code']); ?>" title="Archive">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>
                                        <span>Archive</span>
                                    </button>
                                <?php elseif ($detailAnnouncement['status'] === 'archived'): ?>
                                    <button type="button" class="btn btn-outline btn-xs btn-unarchive-announcement" data-code="<?php echo htmlspecialchars($detailAnnouncement['announcement_code']); ?>" title="Restore">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                                        <span>Restore</span>
                                    </button>
                                <?php endif; ?>

                                <button type="button" class="btn btn-outline btn-xs btn-edit-announcement" data-code="<?php echo htmlspecialchars($detailAnnouncement['announcement_code']); ?>" title="Edit notice">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    <span>Edit</span>
                                </button>
                                <button type="button" class="btn btn-outline btn-xs btn-delete-announcement text-danger" data-code="<?php echo htmlspecialchars($detailAnnouncement['announcement_code']); ?>" data-title="<?php echo htmlspecialchars($detailAnnouncement['title']); ?>" title="Delete">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                    <span>Delete</span>
                                </button>
                            </div>
                            <?php endif; ?>
                        </nav>

                        <article class="anc-detail-card <?php echo $detailAnnouncement['is_pinned'] ? 'has-pin' : ''; ?>">
                            <header class="anc-detail-card-header">
                                <div class="anc-badges-row">
                                    <?php if ((int)$detailAnnouncement['is_pinned'] === 1): ?>
                                    <span class="anc-pin-badge" title="Pinned to top">
                                        <span class="anc-pin-icon" aria-hidden="true">&#128204;</span>
                                        <span>Pinned</span>
                                    </span>
                                    <?php endif; ?>

                                    <span class="anc-cat-badge anc-cat-<?php echo htmlspecialchars($detailAnnouncement['category']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($detailAnnouncement['category'])); ?>
                                    </span>

                                    <?php if ($detailAnnouncement['priority'] !== 'normal'): ?>
                                    <span class="anc-prio-badge anc-prio-<?php echo htmlspecialchars($detailAnnouncement['priority']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($detailAnnouncement['priority'])); ?>
                                    </span>
                                    <?php endif; ?>

                                    <?php if ($isAdmin && $detailAnnouncement['status'] !== 'published'): ?>
                                    <span class="anc-status-pill anc-status-<?php echo htmlspecialchars($detailAnnouncement['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($detailAnnouncement['status'])); ?>
                                    </span>
                                    <?php endif; ?>

                                    <span class="anc-date-text">
                                        <?php 
                                        if ($detailAnnouncement['status'] === 'scheduled' && !empty($detailAnnouncement['scheduled_at'])) {
                                            echo 'Scheduled for ' . date('M j, Y, g:i a', strtotime($detailAnnouncement['scheduled_at']));
                                        } else {
                                            echo 'Published ' . getRelativeTime($detailAnnouncement['published_at'] ?? $detailAnnouncement['created_at']);
                                        }
                                        ?>
                                    </span>
                                </div>

                                <h2 class="anc-detail-title"><?php echo htmlspecialchars($detailAnnouncement['title']); ?></h2>

                                <div class="anc-detail-meta-row">
                                    <span class="anc-author-info">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                        <span>Posted by <strong><?php echo htmlspecialchars($detailAnnouncement['author_name'] ?? 'Administration'); ?></strong></span>
                                    </span>

                                    <?php if (!empty($detailAnnouncement['published_at'])): ?>
                                    <span class="anc-exact-date">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <span><?php echo date('F j, Y, g:i a', strtotime($detailAnnouncement['published_at'])); ?></span>
                                    </span>
                                    <?php endif; ?>

                                    <?php if (!empty($detailAnnouncement['expires_at'])): ?>
                                    <span class="anc-expiry-date">
                                        <span>Valid until: <?php echo date('M j, Y', strtotime($detailAnnouncement['expires_at'])); ?></span>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </header>

                            <div class="anc-detail-body">
                                <?php echo nl2br(htmlspecialchars($detailAnnouncement['content'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>

                            <?php if (!empty($detailAnnouncement['updated_at']) && $detailAnnouncement['updated_at'] !== $detailAnnouncement['created_at']): ?>
                            <footer class="anc-detail-card-footer">
                                <span class="anc-updated-text">Last updated: <?php echo date('M j, Y, g:i a', strtotime($detailAnnouncement['updated_at'])); ?></span>
                            </footer>
                            <?php endif; ?>
                        </article>
                    </div>

                <!-- ============================================================== -->
                <!-- STATE B: VERTICAL ANNOUNCEMENT LIST                            -->
                <!-- ============================================================== -->
                <?php else: ?>
                    <div class="anc-public-container">
                        <?php if (empty($announcements)): ?>
                            <!-- Empty State: Clear isolation, no notification fallback -->
                            <div class="empty-state-modern">
                                <div class="empty-icon bg-gray" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                    </svg>
                                </div>
                                <?php if ($currentTab === 'draft'): ?>
                                    <h4>No drafts at the moment.</h4>
                                    <p class="text-secondary">Click "+ New Announcement" above to write and save a draft.</p>
                                <?php elseif ($currentTab === 'scheduled'): ?>
                                    <h4>No scheduled announcements.</h4>
                                    <p class="text-secondary">Scheduled notices will appear here until their publication time arrives.</p>
                                <?php elseif ($currentTab === 'archived'): ?>
                                    <h4>No archived announcements.</h4>
                                    <p class="text-secondary">Archived notices will be stored here for historical reference.</p>
                                <?php else: ?>
                                    <h4>No announcements at the moment.</h4>
                                    <p class="text-secondary">Check back later for new updates.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Spacious Vertical List (Natural pinned-first order, NO artificial subheadings) -->
                            <div class="anc-vertical-list">
                                <?php foreach ($announcements as $anc): ?>
                                <article class="anc-card <?php echo $anc['is_pinned'] ? 'is-pinned' : ''; ?> anc-priority-<?php echo htmlspecialchars($anc['priority']); ?>" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>">
                                    <div class="anc-card-header">
                                        <div class="anc-card-meta">
                                            <?php if ((int)$anc['is_pinned'] === 1): ?>
                                            <span class="anc-pin-badge" title="Pinned to top">
                                                <span class="anc-pin-icon" aria-hidden="true">&#128204;</span>
                                                <span>Pinned</span>
                                            </span>
                                            <?php endif; ?>

                                            <span class="anc-cat-badge anc-cat-<?php echo htmlspecialchars($anc['category']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($anc['category'])); ?>
                                            </span>

                                            <?php if ($anc['priority'] !== 'normal'): ?>
                                            <span class="anc-prio-badge anc-prio-<?php echo htmlspecialchars($anc['priority']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($anc['priority'])); ?>
                                            </span>
                                            <?php endif; ?>

                                            <?php if ($isAdmin && $anc['status'] !== 'published'): ?>
                                            <span class="anc-status-pill anc-status-<?php echo htmlspecialchars($anc['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($anc['status'])); ?>
                                                <?php if ($anc['status'] === 'scheduled' && !empty($anc['scheduled_at'])): ?>
                                                    : <?php echo date('M j, g:i a', strtotime($anc['scheduled_at'])); ?>
                                                <?php endif; ?>
                                            </span>
                                            <?php endif; ?>

                                            <time class="anc-card-date">
                                                <?php 
                                                if ($anc['status'] === 'scheduled' && !empty($anc['scheduled_at'])) {
                                                    echo 'Scheduled for ' . date('M j, Y, g:i a', strtotime($anc['scheduled_at']));
                                                } elseif ($anc['status'] === 'archived') {
                                                    echo 'Archived ' . getRelativeTime($anc['updated_at']);
                                                } elseif ($anc['status'] === 'draft') {
                                                    echo 'Created ' . getRelativeTime($anc['created_at']);
                                                } else {
                                                    echo getRelativeTime($anc['published_at'] ?? $anc['created_at']);
                                                }
                                                ?>
                                            </time>
                                        </div>

                                        <?php if ($isAdmin): ?>
                                        <div class="anc-card-admin-actions">
                                            <?php if ($anc['status'] === 'published'): ?>
                                                <button type="button" class="btn btn-outline btn-xs btn-edit-announcement" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" title="Edit notice">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                    <span>Edit</span>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-xs btn-toggle-pin <?php echo $anc['is_pinned'] ? 'is-active-pinned' : ''; ?>" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" data-pinned="<?php echo (int)$anc['is_pinned']; ?>" title="<?php echo $anc['is_pinned'] ? 'Unpin from top' : 'Pin to top'; ?>">
                                                    <span>&#128204; <?php echo $anc['is_pinned'] ? 'Pinned' : 'Pin'; ?></span>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-xs btn-archive-announcement" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" title="Archive notice">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>
                                                    <span>Archive</span>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-xs btn-delete-announcement text-danger" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" data-title="<?php echo htmlspecialchars($anc['title']); ?>" title="Delete notice">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    <span>Delete</span>
                                                </button>
                                            <?php elseif ($anc['status'] === 'draft'): ?>
                                                <button type="button" class="btn btn-primary btn-xs btn-publish-announcement" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" title="Publish this announcement now">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                    <span>Publish Now</span>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-xs btn-edit-announcement" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" title="Edit draft">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                    <span>Edit</span>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-xs btn-delete-announcement text-danger" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" data-title="<?php echo htmlspecialchars($anc['title']); ?>" title="Delete draft">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    <span>Delete</span>
                                                </button>
                                            <?php elseif ($anc['status'] === 'scheduled'): ?>
                                                <button type="button" class="btn btn-primary btn-xs btn-publish-announcement" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" title="Publish immediately">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                    <span>Publish Now</span>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-xs btn-edit-announcement" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" title="Edit notice">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                    <span>Edit</span>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-xs btn-archive-announcement" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" title="Archive">
                                                    <span>Archive</span>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-xs btn-delete-announcement text-danger" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" data-title="<?php echo htmlspecialchars($anc['title']); ?>" title="Delete">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    <span>Delete</span>
                                                </button>
                                            <?php elseif ($anc['status'] === 'archived'): ?>
                                                <button type="button" class="btn btn-outline btn-xs btn-unarchive-announcement" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" title="Restore to active notices">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                                                    <span>Restore</span>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-xs btn-edit-announcement" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" title="Edit">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                    <span>Edit</span>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-xs btn-delete-announcement text-danger" data-code="<?php echo htmlspecialchars($anc['announcement_code']); ?>" data-title="<?php echo htmlspecialchars($anc['title']); ?>" title="Delete permanently">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    <span>Delete</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <h2 class="anc-card-title">
                                        <a href="announcements.php?id=<?php echo urlencode($anc['announcement_code']); ?>">
                                            <?php echo htmlspecialchars($anc['title']); ?>
                                        </a>
                                    </h2>

                                    <p class="anc-card-preview">
                                        <?php 
                                        $fullText = $anc['content'];
                                        $previewLimit = 220;
                                        if (mb_strlen($fullText) > $previewLimit) {
                                            echo htmlspecialchars(mb_substr($fullText, 0, $previewLimit)) . '...';
                                        } else {
                                            echo htmlspecialchars($fullText);
                                        }
                                        ?>
                                    </p>

                                    <div class="anc-card-footer">
                                        <a href="announcements.php?id=<?php echo urlencode($anc['announcement_code']); ?>" class="anc-read-more-btn">
                                            <span>Read full announcement</span>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                                <polyline points="12 5 19 12 12 19"></polyline>
                                            </svg>
                                        </a>
                                    </div>
                                </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- ====================================================================== -->
    <!-- ADMIN MODALS (Only rendered for authenticated administrators)          -->
    <!-- ====================================================================== -->
    <?php if ($isAdmin): ?>
    <!-- 1. Create / Edit Announcement Modal -->
    <div class="modal-overlay" id="anc-form-modal" hidden>
        <div class="modal-content anc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="anc-modal-title">
            <div class="modal-dialog-header">
                <div class="anc-modal-header-left">
                    <div class="anc-modal-header-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </div>
                    <div class="anc-modal-header-text">
                        <h3 class="modal-dialog-title" id="anc-modal-title">Create Announcement</h3>
                        <p class="modal-dialog-subtitle" id="anc-modal-subtitle">Compose and publish an official campus announcement.</p>
                    </div>
                </div>
                <button type="button" class="modal-dialog-close" id="anc-modal-close-btn" aria-label="Close dialog">&times;</button>
            </div>

            <form id="anc-announcement-form" novalidate>
                <input type="hidden" name="action" id="anc-form-action" value="create">
                <input type="hidden" name="announcement_code" id="anc-form-code" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-dialog-body anc-form-body">
                    <!-- Title -->
                    <div class="form-group">
                        <label for="anc-title-input">Title <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="anc-title-input" name="title" class="form-control" placeholder="e.g. Campus Library Schedule Update" maxlength="200" required>
                        <span class="field-error" id="err-anc-title"></span>
                    </div>

                    <!-- Category & Priority Row -->
                    <div class="anc-form-row">
                        <div class="form-group">
                            <label for="anc-category-select">Category</label>
                            <div class="anc-select-wrapper">
                                <select id="anc-category-select" name="category" class="form-control">
                                    <option value="college">College</option>
                                    <option value="lostlink" selected>LostLink</option>
                                    <option value="system">System</option>
                                    <option value="event">Event</option>
                                    <option value="important">Important</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="anc-priority-select">Priority</label>
                            <div class="anc-select-wrapper">
                                <select id="anc-priority-select" name="priority" class="form-control">
                                    <option value="normal" selected>Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="form-group">
                        <div class="anc-label-row">
                            <label for="anc-content-input">Announcement Content <span class="required" aria-hidden="true">*</span></label>
                            <span class="char-count" id="anc-char-count">0 characters</span>
                        </div>
                        <textarea id="anc-content-input" name="content" class="form-control anc-textarea" rows="5" placeholder="Write the announcement details clearly..." required></textarea>
                        <span class="field-error" id="err-anc-content"></span>
                    </div>

                    <!-- Pin Checkbox -->
                    <div class="anc-checkbox-card">
                        <label class="anc-checkbox-label" for="anc-pinned-input">
                            <input type="checkbox" id="anc-pinned-input" name="is_pinned" value="1" class="anc-checkbox-input">
                            <span class="anc-checkbox-custom" aria-hidden="true">
                                <svg width="12" height="10" viewBox="0 0 12 10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="1.5 5 4.5 8 10.5 2"></polyline>
                                </svg>
                            </span>
                            <div class="anc-checkbox-text-wrap">
                                <span class="anc-checkbox-title">📌 Pin this announcement to top</span>
                                <span class="anc-checkbox-hint">Pinned announcements remain at the top of the feed for maximum visibility.</span>
                            </div>
                        </label>
                    </div>

                    <!-- Publication Options -->
                    <div class="form-group anc-pub-options">
                        <label class="anc-section-label">Publication Option</label>
                        <div class="anc-status-cards-grid">
                            <label class="anc-status-card" for="anc-status-published">
                                <input type="radio" name="status" value="published" id="anc-status-published" class="anc-status-radio" checked>
                                <div class="anc-status-card-box">
                                    <div class="anc-status-card-header">
                                        <span class="anc-status-dot anc-dot-published"></span>
                                        <span class="anc-status-card-title">Publish now</span>
                                    </div>
                                    <span class="anc-status-card-desc">Visible immediately to all campus users</span>
                                </div>
                            </label>

                            <label class="anc-status-card" for="anc-status-draft">
                                <input type="radio" name="status" value="draft" id="anc-status-draft" class="anc-status-radio">
                                <div class="anc-status-card-box">
                                    <div class="anc-status-card-header">
                                        <span class="anc-status-dot anc-dot-draft"></span>
                                        <span class="anc-status-card-title">Save as draft</span>
                                    </div>
                                    <span class="anc-status-card-desc">Saved privately for administrators</span>
                                </div>
                            </label>

                            <label class="anc-status-card" for="anc-status-scheduled">
                                <input type="radio" name="status" value="scheduled" id="anc-status-scheduled" class="anc-status-radio">
                                <div class="anc-status-card-box">
                                    <div class="anc-status-card-header">
                                        <span class="anc-status-dot anc-dot-scheduled"></span>
                                        <span class="anc-status-card-title">Schedule</span>
                                    </div>
                                    <span class="anc-status-card-desc">Auto-publish on specific date & time</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Schedule Datetime (shown when schedule is checked) -->
                    <div class="anc-schedule-card" id="anc-schedule-wrap" hidden>
                        <div class="form-group">
                            <label for="anc-scheduled-at">Schedule Publication Time <span class="required" aria-hidden="true">*</span></label>
                            <input type="datetime-local" id="anc-scheduled-at" name="scheduled_at" class="form-control">
                            <span class="helper-text">Announcement will automatically publish when this exact time arrives.</span>
                            <span class="field-error" id="err-anc-schedule"></span>
                        </div>
                    </div>

                    <!-- Optional Expiration -->
                    <div class="form-group">
                        <label for="anc-expires-at">Optional Expiration Date / Time</label>
                        <input type="datetime-local" id="anc-expires-at" name="expires_at" class="form-control">
                        <span class="helper-text">After this time, the announcement will automatically leave the active public list.</span>
                    </div>
                </div>

                <div class="modal-dialog-footer">
                    <button type="button" class="btn btn-outline" id="anc-modal-cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="anc-submit-btn">
                        <span id="anc-submit-btn-text">Publish Announcement</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Delete Confirmation Modal -->
    <div class="modal-overlay" id="anc-delete-modal" hidden>
        <div class="modal-content modal-block-dialog anc-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="anc-delete-modal-title">
            <div class="modal-dialog-header">
                <div class="anc-modal-header-left">
                    <div class="anc-modal-header-icon anc-icon-danger" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                    </div>
                    <div class="anc-modal-header-text">
                        <h3 class="modal-dialog-title" id="anc-delete-modal-title">Delete Announcement</h3>
                        <p class="modal-dialog-subtitle">This action is permanent and cannot be undone.</p>
                    </div>
                </div>
                <button type="button" class="modal-dialog-close" id="anc-delete-modal-close" aria-label="Close dialog">&times;</button>
            </div>
            <div class="modal-dialog-body">
                <p>Are you sure you want to permanently delete <strong id="anc-delete-target-title">this announcement</strong>?</p>
                <p class="modal-dialog-subtext">The record will be completely removed from the database and will no longer be visible to any users.</p>
            </div>
            <div class="modal-dialog-footer">
                <button type="button" class="btn btn-outline" id="anc-delete-cancel-btn">Cancel</button>
                <button type="button" class="btn btn-danger" id="anc-delete-confirm-btn">Delete Announcement</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Interactive UI Components Logic (Sidebar Toggle, Hamburger, Dropdowns) -->
    <script src="../assets/js/components.js"></script>
    <!-- Page JavaScript -->
    <script src="announcements.js?v=<?php echo time(); ?>"></script>
</body>
</html>
