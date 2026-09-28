<?php
/*
|--------------------------------------------------------------------------
| LostLink Dashboard
|--------------------------------------------------------------------------
| The main authenticated landing page for the application.
| This implements the application shell (Navbar, Sidebar, Main Content).
|
*/

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../backend/utils/helpers.php';
require_once __DIR__ . '/../backend/auth/auth_check.php';

$user_id = $_SESSION['user_id'] ?? 0;

// Fetch Statistics
$statsQuery = "
    SELECT 
        SUM(CASE WHEN report_type = 'lost' AND status IN ('pending', 'active', 'claimed') THEN 1 ELSE 0 END) AS active_lost,
        SUM(CASE WHEN report_type = 'found' AND status IN ('pending', 'active', 'claimed') THEN 1 ELSE 0 END) AS active_found,
        SUM(CASE WHEN user_id = :user_id AND status != 'closed' THEN 1 ELSE 0 END) AS my_active_reports,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS total_returned
    FROM items
";
$stmt = $pdo->prepare($statsQuery);
$stmt->execute(['user_id' => $user_id]);
$stats = $stmt->fetch();

$active_lost = (int) ($stats['active_lost'] ?? 0);
$active_found = (int) ($stats['active_found'] ?? 0);
$my_active_reports = (int) ($stats['my_active_reports'] ?? 0);
$total_returned = (int) ($stats['total_returned'] ?? 0);

// Fetch Recent Items
$recentItemsQuery = "
    SELECT item_code, title, category, report_type, created_at, status 
    FROM items 
    ORDER BY created_at DESC 
    LIMIT 5
";
$stmt = $pdo->query($recentItemsQuery);
$recent_items = $stmt->fetchAll();

// Fetch Announcements from announcements table
$announcements = [];
try {
    // Process due scheduled announcements
    $pdo->exec("
        UPDATE announcements
        SET status = 'published',
            published_at = COALESCE(scheduled_at, NOW())
        WHERE status = 'scheduled'
          AND scheduled_at IS NOT NULL
          AND scheduled_at <= NOW()
    ");

    $stmt = $pdo->query("
        SELECT id, announcement_code, title, content AS message, category, priority, is_pinned,
               COALESCE(published_at, created_at) AS created_at
        FROM announcements
        WHERE status = 'published'
          AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY 
            is_pinned DESC,
            FIELD(priority, 'urgent', 'high', 'normal'),
            COALESCE(published_at, created_at) DESC,
            id DESC
        LIMIT 3
    ");
    $announcements = $stmt->fetchAll();
} catch (Throwable $e) {
    $announcements = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Theme Logic (Loaded first to prevent FOUC) -->
    <script src="../assets/js/theme.js"></script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="dashboard.css?v=<?php echo time(); ?>">
</head>
<body class="app-body">

    <!-- Global Top Navigation -->
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="app-main-content">
            <div class="dashboard-content-wrapper animate-fade-in">
                <!-- 
                |--------------------------------------------------------------------------
                | 1. WELCOME SECTION
                |--------------------------------------------------------------------------
                -->
                <div class="dashboard-header-flex">
                    <div class="welcome-text">
                        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['name'] ?? 'User')[0]); ?>!</h1>
                        <p class="text-secondary">Here's what's happening in LostLink on <?php echo date('F j, Y'); ?>.</p>
                    </div>
                    <div class="welcome-actions">
                        <a href="<?php echo BASE_URL; ?>/items/report.php" class="btn btn-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Report New Item
                        </a>
                    </div>
                </div>
                
                <!-- 
                |--------------------------------------------------------------------------
                | 2. STATISTICS
                |--------------------------------------------------------------------------
                -->
                <div class="dashboard-stats-grid">
                    <!-- Stat Card: Lost Items -->
                    <div class="stat-card">
                        <div class="stat-icon-wrapper bg-danger-light text-danger">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">Lost Items</h3>
                            <div class="stat-value"><?php echo $active_lost; ?></div>
                            <span class="stat-trend trend-up">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                <span>Active reports</span>
                            </span>
                        </div>
                    </div>

                    <!-- Stat Card: Found Items -->
                    <div class="stat-card">
                        <div class="stat-icon-wrapper bg-success-light text-success">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                <polyline points="7.5 4.21 12 6.81 16.5 4.21"></polyline>
                                <polyline points="7.5 19.79 7.5 14.6 3 12"></polyline>
                                <polyline points="21 12 16.5 14.6 16.5 19.79"></polyline>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">Found Items</h3>
                            <div class="stat-value"><?php echo $active_found; ?></div>
                            <span class="stat-trend trend-up">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                <span>Active reports</span>
                            </span>
                        </div>
                    </div>

                    <!-- Stat Card: My Reports -->
                    <div class="stat-card">
                        <div class="stat-icon-wrapper bg-primary-light text-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">My Reports</h3>
                            <div class="stat-value"><?php echo $my_active_reports; ?></div>
                            <span class="stat-trend trend-neutral">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                <span>Active reports</span>
                            </span>
                        </div>
                    </div>

                    <!-- Stat Card: Successfully Returned -->
                    <div class="stat-card">
                        <div class="stat-icon-wrapper bg-accent-light text-accent">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">Returned</h3>
                            <div class="stat-value"><?php echo $total_returned; ?></div>
                            <span class="stat-trend trend-up">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                <span>All time</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 
                |--------------------------------------------------------------------------
                | MAIN DASHBOARD GRID (2 Columns)
                |--------------------------------------------------------------------------
                -->
                <div class="dashboard-main-grid">
                    
                    <!-- Left Column: Recent Items -->
                    <div class="dashboard-col-main">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h2>Recent Items</h2>
                                <a href="<?php echo BASE_URL; ?>/items/browse.php" class="text-link">View all</a>
                            </div>
                            <div class="card-body <?php echo empty($recent_items) ? '' : 'no-padding'; ?>">
                                <?php if (empty($recent_items)): ?>
                                    <div class="empty-state-modern">
                                        <div class="empty-icon bg-gray">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                            </svg>
                                        </div>
                                        <h4>No reports yet.</h4>
                                        <p class="text-secondary">Start by reporting your first lost or found item.</p>
                                    </div>
                                <?php else: ?>
                                    <ul class="item-list">
                                        <?php foreach ($recent_items as $item): 
                                            $badge = getStatusDisplay($item['status'], $item['report_type']);
                                        ?>
                                        <li class="item-row" data-item-id="<?php echo htmlspecialchars((string)($item['item_code'] ?? '')); ?>" data-item-context="dashboard" data-item-detail style="cursor: pointer;">
                                            <div class="item-icon bg-gray">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                                </svg>
                                            </div>
                                            <div class="item-details">
                                                <h4><?php echo htmlspecialchars($item['title'] ?? 'Unknown Item'); ?></h4>
                                                <p class="text-secondary"><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></p>
                                            </div>
                                            <div class="item-meta">
                                                <span class="badge <?php echo $badge['class']; ?>"><?php echo $badge['text']; ?></span>
                                                <span class="text-xs text-secondary date-text"><?php echo getRelativeTime($item['created_at']); ?></span>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Quick Actions & Announcements -->
                    <div class="dashboard-col-side">
                        
                        <!-- Quick Actions -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h2>Quick Actions</h2>
                            </div>
                            <div class="card-body pt-0">
                                <div class="quick-actions-grid">
                                    <a href="<?php echo BASE_URL; ?>/items/report.php?type=lost" class="action-card">
                                        <div class="action-icon text-danger">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                        </div>
                                        <div class="action-text">
                                            <h4>Report Lost</h4>
                                            <p>I lost an item</p>
                                        </div>
                                    </a>
                                    
                                    <a href="<?php echo BASE_URL; ?>/items/report.php?type=found" class="action-card">
                                        <div class="action-icon text-success">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="7.5 4.21 12 6.81 16.5 4.21"></polyline><polyline points="7.5 19.79 7.5 14.6 3 12"></polyline><polyline points="21 12 16.5 14.6 16.5 19.79"></polyline><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                        </div>
                                        <div class="action-text">
                                            <h4>Report Found</h4>
                                            <p>I found an item</p>
                                        </div>
                                    </a>
                                    
                                    <a href="<?php echo BASE_URL; ?>/items/browse.php" class="action-card">
                                        <div class="action-icon text-primary">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                        </div>
                                        <div class="action-text">
                                            <h4>Browse Items</h4>
                                            <p>Search database</p>
                                        </div>
                                    </a>
                                    
                                    <a href="<?php echo BASE_URL; ?>/items/my_reports.php" class="action-card">
                                        <div class="action-icon text-accent">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                        </div>
                                        <div class="action-text">
                                            <h4>My Reports</h4>
                                            <p>Manage reports</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Announcements -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h2>Announcements</h2>
                                <a href="<?php echo BASE_URL; ?>/dashboard/announcements.php" class="text-link text-xs">View All &rarr;</a>
                            </div>
                            <div class="card-body <?php echo empty($announcements) ? '' : 'no-padding'; ?>">
                                <?php if (empty($announcements)): ?>
                                    <div class="empty-state-modern">
                                        <div class="empty-icon bg-gray">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                                <path d="M18.63 13A17.89 17.89 0 0 1 18 8"></path>
                                                <path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"></path>
                                                <path d="M18 8a6 6 0 0 0-9.33-5"></path>
                                                <line x1="1" y1="1" x2="23" y2="23"></line>
                                            </svg>
                                        </div>
                                        <h4>No announcements yet.</h4>
                                        <p class="text-secondary">Important updates from administrators will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <ul class="item-list">
                                        <?php foreach ($announcements as $announcement): ?>
                                        <li class="item-row" style="cursor: pointer;" onclick="window.location.href='<?php echo BASE_URL; ?>/dashboard/announcements.php?id=<?php echo urlencode($announcement['announcement_code']); ?>'">
                                            <div class="item-icon bg-primary-light text-primary">
                                                <?php if (!empty($announcement['is_pinned'])): ?>
                                                    <span style="font-size: 14px;" title="Pinned">&#128204;</span>
                                                <?php else: ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h14c0 0-3-2-3-9"></path>
                                                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-details">
                                                <h4><?php echo htmlspecialchars($announcement['title'] ?? 'Announcement'); ?></h4>
                                                <p class="text-secondary"><?php echo htmlspecialchars(mb_substr($announcement['message'] ?? '', 0, 80)) . (mb_strlen($announcement['message'] ?? '') > 80 ? '...' : ''); ?></p>
                                            </div>
                                            <div class="item-meta">
                                                <span class="text-xs text-secondary date-text"><?php echo getRelativeTime($announcement['created_at']); ?></span>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Interactive UI Components Logic -->
    <script src="../assets/js/components.js"></script>
    <!-- Dashboard Specific Logic -->
    <script src="dashboard.js"></script>
    <script>
        // Fix navigation links without modifying shared components
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.sidebar-link').forEach(link => {
                // Prevent clicking the active link (stay on current page)
                if (link.classList.contains('active')) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                    });
                }
            });
        });
    </script>
    <?php require_once __DIR__ . '/../components/item-details/item-details.php'; ?>
</body>
</html>
