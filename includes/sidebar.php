<?php
/*
|--------------------------------------------------------------------------
| GLOBAL SIDEBAR
|--------------------------------------------------------------------------
| Shared across authenticated pages
| Automatically detects the active page to highlight the correct item.
|
*/

// Determine active page for highlighting
$current_page = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../backend/utils/helpers.php';

$userIsAdmin = false;
if (function_exists('isAdmin')) {
    $userIsAdmin = isAdmin();
} elseif (isset($_SESSION['role'])) {
    $userIsAdmin = ($_SESSION['role'] === (defined('ROLE_ADMIN') ? ROLE_ADMIN : 'admin'));
}

$totalUnreadMessages = 0;
if (!empty($_SESSION['user_id']) && isset($pdo)) {
    try {
        $unreadMsgStmt = $pdo->prepare("
            SELECT COUNT(*) FROM messages m
            INNER JOIN conversations c ON m.conversation_id = c.id
            WHERE (c.participant_one_id = :uid1 OR c.participant_two_id = :uid2)
              AND m.sender_id != :uid3
              AND m.is_read = 0
        ");
        $unreadMsgStmt->execute([
            'uid1' => $_SESSION['user_id'],
            'uid2' => $_SESSION['user_id'],
            'uid3' => $_SESSION['user_id']
        ]);
        $totalUnreadMessages = (int)$unreadMsgStmt->fetchColumn();
    } catch (Throwable $e) {
        $totalUnreadMessages = 0;
    }
}
?>

<!-- 
|--------------------------------------------------------------------------
| MOBILE DRAWER OVERLAY
|--------------------------------------------------------------------------
-->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="global-sidebar" id="global-sidebar">
    <div class="sidebar-content">
        <!-- 
        |--------------------------------------------------------------------------
        | NAVIGATION ITEMS
        |--------------------------------------------------------------------------
        -->
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>/dashboard/dashboard.php" class="sidebar-link <?php echo ($current_page == 'dashboard.php' && strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') === false) ? 'active' : ''; ?>" title="Dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span class="sidebar-label">Dashboard</span>
                </a>
            </li>
            
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>/items/report.php" class="sidebar-link <?php echo ($current_page == 'report.php') ? 'active' : ''; ?>" title="Report Item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span class="sidebar-label">Report Item</span>
                </a>
            </li>
            
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>/items/browse.php" class="sidebar-link <?php echo ($current_page == 'browse.php') ? 'active' : ''; ?>" title="Browse Items">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <span class="sidebar-label">Browse Items</span>
                </a>
            </li>
            
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>/items/my_reports.php" class="sidebar-link <?php echo ($current_page == 'my_reports.php') ? 'active' : ''; ?>" title="My Reports">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span class="sidebar-label">My Reports</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>/messages/index.php" class="sidebar-link <?php echo (strpos($_SERVER['REQUEST_URI'] ?? '', '/messages/') !== false) ? 'active' : ''; ?>" title="Messages">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span class="sidebar-label">Messages</span>
                    <?php if ($totalUnreadMessages > 0): ?>
                        <span class="sidebar-badge" style="background-color: #2563eb; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 9999px; margin-left: auto;"><?php echo $totalUnreadMessages; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>/dashboard/announcements.php" class="sidebar-link <?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>" title="Announcements">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="sidebar-label">Announcements</span>
                </a>
            </li>

            <?php if ($userIsAdmin): ?>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="sidebar-link <?php echo (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) ? 'active' : ''; ?>" title="Admin Module">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span class="sidebar-label">Admin Module</span>
                </a>
            </li>
            <?php endif; ?>
            
            <li class="sidebar-divider"></li>
            
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>/dashboard/settings.php" class="sidebar-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" title="Settings">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    <span class="sidebar-label">Settings</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- 
    |--------------------------------------------------------------------------
    | BOTTOM SECTION (Info Card & Footer)
    |--------------------------------------------------------------------------
    -->
    <div class="sidebar-footer">
        <div class="sidebar-info-card">
            <h4>Keeping Campus Safe</h4>
            <p>Report responsibly and help others recover their belongings.</p>
        </div>
        <p class="sidebar-copyright">&copy; LostLink 2026</p>
    </div>
</aside>
