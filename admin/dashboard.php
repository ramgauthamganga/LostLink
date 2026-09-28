<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Admin Module - Foundation Placeholder
|--------------------------------------------------------------------------
| Serves as the minimal, protected landing page for administrators.
| Full admin management modules will be integrated in subsequent phases.
|
*/

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../backend/utils/helpers.php';
require_once __DIR__ . '/../backend/auth/admin_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Module - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Theme Logic -->
    <script src="<?php echo BASE_URL; ?>/assets/js/theme.js"></script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/dashboard/dashboard.css">
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
                
                <!-- Page Header -->
                <div class="dashboard-header-flex" style="margin-bottom: 2rem;">
                    <div class="welcome-text">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: rgba(37, 99, 235, 0.1); color: var(--color-primary, #2563eb);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </span>
                            <h1 style="margin: 0;">Admin Module</h1>
                        </div>
                        <p class="text-secondary" style="margin: 0; font-size: 0.95rem;">Administration features will be added here.</p>
                    </div>
                </div>

                <!-- Clean Placeholder Card / Empty State -->
                <div class="content-card" style="padding: 3rem 2rem; text-align: center; background: var(--color-surface, #ffffff); border: 1px solid var(--color-border, #e2e8f0); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="width: 64px; height: 64px; margin: 0 auto 1.5rem; border-radius: 50%; background: rgba(37, 99, 235, 0.08); display: flex; align-items: center; justify-content: center; color: var(--color-primary, #2563eb);">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--color-text-primary, #0f172a);">Administrative Foundation Active</h2>
                    <p style="max-width: 520px; margin: 0 auto 1.5rem; color: var(--color-text-secondary, #64748b); line-height: 1.6; font-size: 0.925rem;">
                        This area is reserved for campus administration tools. Navigation, role-based authentication guards, and session redirection are established. Management modules will be introduced here in the upcoming phase.
                    </p>
                    <div style="display: inline-flex; gap: 0.75rem; flex-wrap: wrap; justify-content: center;">
                        <a href="<?php echo BASE_URL; ?>/dashboard/dashboard.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 500; font-size: 0.875rem; text-decoration: none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Go to User Dashboard
                        </a>
                        <a href="<?php echo BASE_URL; ?>/items/browse.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 500; font-size: 0.875rem; text-decoration: none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            Browse Items
                        </a>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>
