<?php
/*
|--------------------------------------------------------------------------
| GLOBAL TOP NAVIGATION
|--------------------------------------------------------------------------
| Shared across every authenticated page.
| Provides access to sidebar toggling, search, theme toggling,
| notifications, and user profile management.
|
*/

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../backend/utils/helpers.php';
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
}

$navCurrentUserId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$unreadNotificationCount = 0;
$navNotifications = [];

if ($navCurrentUserId > 0 && isset($pdo)) {
    try {
        // Synchronize session avatar, name & role with database
        $navUserStmt = $pdo->prepare("SELECT name, profile_image, role FROM users WHERE id = :id LIMIT 1");
        $navUserStmt->execute(['id' => $navCurrentUserId]);
        if ($navUserData = $navUserStmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($navUserData['name'])) {
                $_SESSION['name'] = $navUserData['name'];
            }
            if (!empty($navUserData['role'])) {
                $_SESSION['role'] = $navUserData['role'];
            }
            $_SESSION['profile_image'] = $navUserData['profile_image'] ?? null;
        }

        // Fetch unread notifications count
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
        $cntStmt->execute(['uid' => $navCurrentUserId]);
        $unreadNotificationCount = (int)$cntStmt->fetchColumn();

        // Fetch recent 5 notifications with deep link joins
        $listStmt = $pdo->prepare("
            SELECT n.*,
                   i.item_code,
                   cr.request_code,
                   c.conversation_code
            FROM notifications n
            LEFT JOIN items i ON n.item_id = i.id
            LEFT JOIN contact_requests cr ON n.contact_request_id = cr.id
            LEFT JOIN conversations c ON n.conversation_id = c.id
            WHERE n.user_id = :uid
            ORDER BY n.created_at DESC
            LIMIT 5
        ");
        $listStmt->execute(['uid' => $navCurrentUserId]);
        $navNotifications = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Fallback silently
    }
}

$rawNavAvatar = $_SESSION['profile_image'] ?? null;
$navAvatarUrl = getProfileImageUrl($rawNavAvatar);
if (!empty($rawNavAvatar) && $navAvatarUrl !== BASE_URL . '/profile/default_user.png') {
    $avatarDiskFile = __DIR__ . '/../' . ltrim($rawNavAvatar, '/');
    $avatarVer = file_exists($avatarDiskFile) ? filemtime($avatarDiskFile) : time();
    $navAvatarUrl .= (strpos($navAvatarUrl, '?') === false ? '?' : '&') . 'v=' . $avatarVer;
}
?>
<header class="global-navbar">
    <div class="navbar-left">
        <!-- 
        |--------------------------------------------------------------------------
        | SIDEBAR TOGGLE
        |--------------------------------------------------------------------------
        | Controls expanded/collapsed desktop sidebar and mobile drawer behaviour.
        -->
        <button type="button" class="sidebar-toggle" aria-label="Toggle Sidebar" id="sidebar-toggle-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-hamburger">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        
        <div class="navbar-logo">
            <a href="<?php echo BASE_URL; ?>/dashboard/dashboard.php">
                <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect width="32" height="32" rx="8" fill="#2563EB" />
                    <path d="M16 8C12.134 8 9 11.134 9 15C9 18.866 12.134 22 16 22C19.866 22 23 18.866 23 15" stroke="white" stroke-width="2.5" stroke-linecap="round" />
                    <circle cx="16" cy="15" r="2.5" fill="white" />
                    <path d="M20 11L23 8L26 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span>LostLink</span>
            </a>
        </div>
    </div>

    <div class="navbar-center">
        <!-- 
        |--------------------------------------------------------------------------
        | FUTURE ENHANCEMENT: Global search functionality.
        |--------------------------------------------------------------------------
        | TODO: Implement search logic and backend API integration.
        -->
        <div class="navbar-search-container">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="search-icon">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" class="navbar-search-input" placeholder="Search lost & found items..." aria-label="Search">
        </div>
    </div>

    <div class="navbar-right">
        <!-- 
        |--------------------------------------------------------------------------
        | THEME TOGGLE
        |--------------------------------------------------------------------------
        | Uses the shared theme.js and theme.css to toggle between light/dark mode.
        -->
        <button type="button" class="theme-toggle" aria-label="Toggle Theme" title="Toggle Theme">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-sun">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-moon">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
        </button>

        <!-- 
        |--------------------------------------------------------------------------
        | NOTIFICATIONS DROPDOWN
        |--------------------------------------------------------------------------
        -->
        <div class="dropdown-container notification-dropdown">
            <button type="button" class="icon-button dropdown-toggle" aria-label="Notifications" aria-expanded="false" id="notification-toggle">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if ($unreadNotificationCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount; ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu notification-menu" aria-labelledby="notification-toggle">
                <div class="dropdown-header">
                    <h4>Notifications</h4>
                    <?php if ($unreadNotificationCount > 0): ?>
                        <span class="notification-header-count"><?php echo $unreadNotificationCount; ?> new</span>
                    <?php endif; ?>
                </div>
                <div class="dropdown-content">
                    <?php if (empty($navNotifications)): ?>
                        <p class="empty-state">No new notifications</p>
                    <?php else: ?>
                        <?php foreach ($navNotifications as $notif):
                            $notifTargetUrl = '#';
                            if (!empty($notif['conversation_code'])) {
                                $notifTargetUrl = BASE_URL . '/messages/index.php?c=' . urlencode((string)$notif['conversation_code']);
                            } elseif (!empty($notif['request_code'])) {
                                $notifTargetUrl = BASE_URL . '/messages/index.php?r=' . urlencode((string)$notif['request_code']);
                            } elseif (!empty($notif['item_code'])) {
                                $notifTargetUrl = BASE_URL . '/items/browse.php?item=' . urlencode((string)$notif['item_code']);
                            }
                            $isNotifUnread = empty($notif['is_read']);
                        ?>
                            <a href="<?php echo htmlspecialchars($notifTargetUrl); ?>" class="dropdown-item notification-item <?php echo $isNotifUnread ? 'is-unread' : ''; ?>">
                                <div class="notification-item-content">
                                    <span class="notification-item-title"><?php echo htmlspecialchars((string)$notif['title']); ?></span>
                                    <p class="notification-item-msg"><?php echo htmlspecialchars((string)$notif['message']); ?></p>
                                    <span class="notification-item-time"><?php echo htmlspecialchars(getRelativeTime((string)$notif['created_at'])); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="dropdown-footer">
                    <a href="<?php echo BASE_URL; ?>/messages/index.php" class="view-all-link">View All in Messages &rarr;</a>
                </div>
            </div>
        </div>

        <!-- 
        |--------------------------------------------------------------------------
        | USER PROFILE SECTION
        |--------------------------------------------------------------------------
        -->
        <div class="dropdown-container profile-dropdown">
            <button type="button" class="profile-toggle dropdown-toggle" aria-label="User Profile" aria-expanded="false" id="profile-toggle">
                <img src="<?php echo htmlspecialchars($navAvatarUrl); ?>" alt="User Avatar" class="user-avatar" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>/profile/default_user.png'">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Guest'); ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron-down">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>
            <div class="dropdown-menu" aria-labelledby="profile-toggle">
                <a href="<?php echo BASE_URL; ?>/dashboard/settings.php#account" class="dropdown-item">My Profile</a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo BASE_URL; ?>/backend/auth/logout.php" class="dropdown-item text-danger">Logout</a>
            </div>
        </div>
    </div>
</header>
