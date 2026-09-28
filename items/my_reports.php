<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink — My Reports Page
|--------------------------------------------------------------------------
| Displays and manages reports belonging exclusively to the currently
| authenticated user. Integrates with the top navbar search contextually.
*/

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../backend/utils/helpers.php';
require_once __DIR__ . '/../backend/auth/auth_check.php';

if (!defined('MY_REPORTS_PER_PAGE')) {
    define('MY_REPORTS_PER_PAGE', 12);
}

if (!function_exists('myReportsGetString')) {
    /**
     * Return bounded scalar GET value.
     */
    function myReportsGetString(string $key, int $maxLength = 255): string
    {
        $value = $_GET[$key] ?? '';
        if (!is_string($value)) {
            return '';
        }
        return trim(mb_substr($value, 0, $maxLength));
    }
}

if (!function_exists('myReportsEscape')) {
    /**
     * Escape string for safe HTML output.
     */
    function myReportsEscape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('myReportsCategoryLabel')) {
    /**
     * Return human-friendly label for a category.
     */
    function myReportsCategoryLabel(string $category): string
    {
        $category = trim(str_replace(['_', '-'], ' ', $category));
        return $category === '' ? 'Uncategorized' : ucwords($category);
    }
}

if (!function_exists('myReportsItemImageUrl')) {
    /**
     * Returns safe image URL with fallback to default_no_image.png.
     */
    function myReportsItemImageUrl(?string $storedPath): string
    {
        $defaultImage = BASE_URL . '/assets/images/default_no_image.png';
        if (!$storedPath) {
            return $defaultImage;
        }

        $relativePath = ltrim($storedPath, '/');
        $requiredPrefix = 'assets/uploads/items/';

        if (
            strpos($relativePath, $requiredPrefix) !== 0 ||
            strpos($relativePath, '..') !== false ||
            strpos($relativePath, '\\') !== false
        ) {
            return $defaultImage;
        }

        $projectRoot = realpath(__DIR__ . '/..');
        $uploadsDirectory = $projectRoot ? realpath($projectRoot . '/assets/uploads/items') : false;
        $absolutePath = $projectRoot ? realpath($projectRoot . '/' . $relativePath) : false;

        if (
            !$uploadsDirectory ||
            !$absolutePath ||
            strpos($absolutePath, rtrim($uploadsDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0 ||
            !is_file($absolutePath) ||
            @getimagesize($absolutePath) === false
        ) {
            return $defaultImage;
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $relativePath)));
        return BASE_URL . '/' . $encodedPath;
    }
}

if (!function_exists('myReportsUrl')) {
    /**
     * Build URL preserving state with overrides.
     *
     * @param array<string, string|int> $overrides
     */
    function myReportsUrl(array $overrides = []): string
    {
        $values = [
            'q' => myReportsGetString('q', 150),
            'status' => myReportsGetString('status', 20),
            'sort' => myReportsGetString('sort', 20),
            'page' => (int) ($_GET['page'] ?? 1),
        ];

        foreach ($overrides as $key => $value) {
            $values[$key] = $value;
        }

        $values = array_filter(
            $values,
            static function ($value, $key): bool {
                if ($key === 'status' && ($value === '' || $value === 'all')) {
                    return false;
                }
                if ($key === 'sort' && ($value === '' || $value === 'newest')) {
                    return false;
                }
                if ($key === 'page' && (int) $value <= 1) {
                    return false;
                }
                if ($key === 'q' && trim((string) $value) === '') {
                    return false;
                }
                return true;
            },
            ARRAY_FILTER_USE_BOTH
        );

        $query = http_build_query($values);
        return BASE_URL . '/items/my_reports.php' . ($query !== '' ? '?' . $query : '');
    }
}

if (!function_exists('myReportsClearUrl')) {
    /**
     * Reset URL to clear all filters and search.
     */
    function myReportsClearUrl(): string
    {
        return BASE_URL . '/items/my_reports.php';
    }
}

// -------------------------------------------------------------------------
// User Authentication & Parameters
// -------------------------------------------------------------------------
$currentUserId = (int) $_SESSION['user_id'];

$search = myReportsGetString('q', 150);
$currentStatus = myReportsGetString('status', 20);
$currentSort = myReportsGetString('sort', 20);

$validStatuses = ['all', 'pending', 'active', 'claimed', 'returned', 'closed'];
if (!in_array($currentStatus, $validStatuses, true)) {
    $currentStatus = 'all';
}

$validSorts = ['newest', 'oldest', 'updated'];
if (!in_array($currentSort, $validSorts, true)) {
    $currentSort = 'newest';
}

$rawPage = $_GET['page'] ?? '1';
$page = is_string($rawPage) && ctype_digit($rawPage) ? max(1, (int) $rawPage) : 1;

// -------------------------------------------------------------------------
// Summary Metrics for Authenticated User
// -------------------------------------------------------------------------
$summaryStatement = $pdo->prepare('
    SELECT status, COUNT(*) AS count
    FROM items
    WHERE user_id = :user_id
    GROUP BY status
');
$summaryStatement->bindValue(':user_id', $currentUserId, PDO::PARAM_INT);
$summaryStatement->execute();
$statusSummaryRows = $summaryStatement->fetchAll(PDO::FETCH_KEY_PAIR);

$statusCounts = [
    'pending'  => (int) ($statusSummaryRows['pending'] ?? 0),
    'active'   => (int) ($statusSummaryRows['active'] ?? 0),
    'claimed'  => (int) ($statusSummaryRows['claimed'] ?? 0),
    'returned' => (int) ($statusSummaryRows['returned'] ?? 0),
    'closed'   => (int) ($statusSummaryRows['closed'] ?? 0),
];
$totalUserReports = array_sum($statusCounts);

// -------------------------------------------------------------------------
// Build Filtered Query
// -------------------------------------------------------------------------
$whereClauses = ['i.user_id = :user_id'];
$queryParams = [':user_id' => $currentUserId];

if ($currentStatus !== 'all') {
    $whereClauses[] = 'i.status = :status';
    $queryParams[':status'] = $currentStatus;
}

if ($search !== '') {
    $searchWildcard = '%' . $search . '%';
    $searchColumns = ['title', 'description', 'category', 'subcategory', 'event_location'];
    $searchSubConditions = [];

    foreach ($searchColumns as $idx => $column) {
        $paramKey = ':search_' . $idx;
        $searchSubConditions[] = 'i.' . $column . ' LIKE ' . $paramKey;
        $queryParams[$paramKey] = $searchWildcard;
    }

    $whereClauses[] = '(' . implode(' OR ', $searchSubConditions) . ')';
}

$whereSql = implode(' AND ', $whereClauses);

$orderBySql = [
    'newest'  => 'i.created_at DESC, i.id DESC',
    'oldest'  => 'i.created_at ASC, i.id ASC',
    'updated' => 'i.updated_at DESC, i.id DESC',
][$currentSort];

// Filtered count for pagination
$countStatement = $pdo->prepare("SELECT COUNT(*) FROM items i WHERE {$whereSql}");
foreach ($queryParams as $paramKey => $val) {
    if ($paramKey === ':user_id') {
        $countStatement->bindValue($paramKey, (int) $val, PDO::PARAM_INT);
    } else {
        $countStatement->bindValue($paramKey, $val, PDO::PARAM_STR);
    }
}
$countStatement->execute();
$filteredCount = (int) $countStatement->fetchColumn();

$totalPages = max(1, (int) ceil($filteredCount / MY_REPORTS_PER_PAGE));
$page = min($page, $totalPages);
$offset = ($page - 1) * MY_REPORTS_PER_PAGE;

// Fetch Paginated User Reports
$itemsStatement = $pdo->prepare("
    SELECT
        i.id,
        i.item_code,
        i.title,
        i.description,
        i.category,
        i.subcategory,
        i.report_type,
        i.event_location,
        i.event_date,
        i.status,
        i.created_at,
        i.updated_at,
        (
            SELECT ii.image
            FROM item_images ii
            WHERE ii.item_id = i.id
            ORDER BY ii.image_order ASC, ii.id ASC
            LIMIT 1
        ) AS image_path,
        (
            SELECT COUNT(*)
            FROM item_images ii
            WHERE ii.item_id = i.id
        ) AS image_count,
        (
            SELECT COUNT(*)
            FROM claims c
            WHERE c.item_id = i.id
        ) AS total_claims,
        (
            SELECT COUNT(*)
            FROM claims c
            WHERE c.item_id = i.id AND c.status = 'pending'
        ) AS pending_claims,
        (
            SELECT COUNT(*)
            FROM claims c
            WHERE c.item_id = i.id AND c.status = 'approved'
        ) AS approved_claims
    FROM items i
    WHERE {$whereSql}
    ORDER BY {$orderBySql}
    LIMIT :limit OFFSET :offset
");

foreach ($queryParams as $paramKey => $val) {
    if ($paramKey === ':user_id') {
        $itemsStatement->bindValue($paramKey, (int) $val, PDO::PARAM_INT);
    } else {
        $itemsStatement->bindValue($paramKey, $val, PDO::PARAM_STR);
    }
}
$itemsStatement->bindValue(':limit', MY_REPORTS_PER_PAGE, PDO::PARAM_INT);
$itemsStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$itemsStatement->execute();
$items = $itemsStatement->fetchAll(PDO::FETCH_ASSOC);

$hasActiveFilters = ($search !== '' || $currentStatus !== 'all' || $currentSort !== 'newest');
$shownFrom = $filteredCount === 0 ? 0 : $offset + 1;
$shownTo = $filteredCount === 0 ? 0 : min($offset + MY_REPORTS_PER_PAGE, $filteredCount);

$pageNumbers = [];
if ($totalPages > 1) {
    $pageNumbers[] = 1;
    for ($i = max(2, $page - 1); $i <= min($totalPages - 1, $page + 1); $i++) {
        $pageNumbers[] = $i;
    }
    $pageNumbers[] = $totalPages;
    $pageNumbers = array_values(array_unique($pageNumbers));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports | <?php echo myReportsEscape(APP_NAME); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="my_reports.css?v=<?php echo time(); ?>">
</head>
<body class="app-body my-reports-page">

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="app-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="app-main-content" id="main-content">
            <div class="my-reports-wrapper animate-fade-in"
                 id="my-reports-page"
                 data-search-context="my_reports"
                 data-search-target="<?php echo myReportsEscape(BASE_URL . '/items/my_reports.php'); ?>"
                 data-current-query="<?php echo myReportsEscape($search); ?>"
                 data-current-status="<?php echo myReportsEscape($currentStatus); ?>"
                 data-current-sort="<?php echo myReportsEscape($currentSort); ?>">

                <!-- 1. Page Header -->
                <header class="my-reports-header">
                    <div class="my-reports-header-text">
                        <h1 class="my-reports-title">My Reports</h1>
                        <p class="my-reports-subtitle">Track and manage the lost and found items you have reported.</p>
                    </div>
                    <div class="my-reports-header-actions">
                        <a href="<?php echo myReportsEscape(BASE_URL . '/items/report.php'); ?>" class="btn btn-primary my-reports-btn-new">
                            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>Report New Item</span>
                        </a>
                    </div>
                </header>

                <!-- 2. Compact Reports Summary -->
                <section class="my-reports-summary" aria-label="Reports Summary">
                    <a href="<?php echo myReportsEscape(myReportsUrl(['status' => 'all', 'page' => 1])); ?>"
                       class="summary-tile <?php echo $currentStatus === 'all' ? 'is-active' : ''; ?>"
                       title="View all reports">
                        <div class="summary-tile-icon bg-primary-light text-primary" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                        </div>
                        <div class="summary-tile-data">
                            <span class="summary-tile-count"><?php echo $totalUserReports; ?></span>
                            <span class="summary-tile-label">Total Reports</span>
                        </div>
                    </a>

                    <a href="<?php echo myReportsEscape(myReportsUrl(['status' => 'active', 'page' => 1])); ?>"
                       class="summary-tile <?php echo $currentStatus === 'active' ? 'is-active' : ''; ?>"
                       title="View active reports">
                        <div class="summary-tile-icon bg-primary-light text-primary" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 14 14"></polyline>
                            </svg>
                        </div>
                        <div class="summary-tile-data">
                            <span class="summary-tile-count"><?php echo $statusCounts['active']; ?></span>
                            <span class="summary-tile-label">Active</span>
                        </div>
                    </a>

                    <a href="<?php echo myReportsEscape(myReportsUrl(['status' => 'pending', 'page' => 1])); ?>"
                       class="summary-tile <?php echo $currentStatus === 'pending' ? 'is-active' : ''; ?>"
                       title="View pending reports">
                        <div class="summary-tile-icon bg-warning-light text-warning" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <div class="summary-tile-data">
                            <span class="summary-tile-count"><?php echo $statusCounts['pending']; ?></span>
                            <span class="summary-tile-label">Pending</span>
                        </div>
                    </a>

                    <a href="<?php echo myReportsEscape(myReportsUrl(['status' => 'claimed', 'page' => 1])); ?>"
                       class="summary-tile <?php echo $currentStatus === 'claimed' ? 'is-active' : ''; ?>"
                       title="View claimed reports">
                        <div class="summary-tile-icon bg-accent-light text-accent" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <polyline points="16 11 18 13 22 9"></polyline>
                            </svg>
                        </div>
                        <div class="summary-tile-data">
                            <span class="summary-tile-count"><?php echo $statusCounts['claimed']; ?></span>
                            <span class="summary-tile-label">Claimed</span>
                        </div>
                    </a>

                    <a href="<?php echo myReportsEscape(myReportsUrl(['status' => 'returned', 'page' => 1])); ?>"
                       class="summary-tile <?php echo $currentStatus === 'returned' ? 'is-active' : ''; ?>"
                       title="View returned reports">
                        <div class="summary-tile-icon bg-success-light text-success" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <div class="summary-tile-data">
                            <span class="summary-tile-count"><?php echo $statusCounts['returned']; ?></span>
                            <span class="summary-tile-label">Returned</span>
                        </div>
                    </a>

                    <a href="<?php echo myReportsEscape(myReportsUrl(['status' => 'closed', 'page' => 1])); ?>"
                       class="summary-tile <?php echo $currentStatus === 'closed' ? 'is-active' : ''; ?>"
                       title="View closed reports">
                        <div class="summary-tile-icon bg-gray text-secondary" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                        <div class="summary-tile-data">
                            <span class="summary-tile-count"><?php echo $statusCounts['closed']; ?></span>
                            <span class="summary-tile-label">Closed</span>
                        </div>
                    </a>
                </section>

                <!-- 3. Status Filters & Sort Toolbar -->
                <div class="my-reports-toolbar">
                    <div class="my-reports-status-filters" role="group" aria-label="Filter reports by status">
                        <?php
                        $filterTabs = [
                            'all'      => ['label' => 'All', 'count' => $totalUserReports],
                            'active'   => ['label' => 'Active', 'count' => $statusCounts['active']],
                            'pending'  => ['label' => 'Pending', 'count' => $statusCounts['pending']],
                            'claimed'  => ['label' => 'Claimed', 'count' => $statusCounts['claimed']],
                            'returned' => ['label' => 'Returned', 'count' => $statusCounts['returned']],
                            'closed'   => ['label' => 'Closed', 'count' => $statusCounts['closed']],
                        ];
                        foreach ($filterTabs as $tabKey => $tab):
                            $isSelected = ($currentStatus === $tabKey);
                        ?>
                            <a href="<?php echo myReportsEscape(myReportsUrl(['status' => $tabKey, 'page' => 1])); ?>"
                               class="status-filter-tab <?php echo $isSelected ? 'is-selected' : ''; ?>"
                               aria-pressed="<?php echo $isSelected ? 'true' : 'false'; ?>">
                                <span><?php echo $tab['label']; ?></span>
                                <span class="tab-count"><?php echo $tab['count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="my-reports-sort-control">
                        <label for="my-reports-sort" class="sort-label">Sort by:</label>
                        <div class="sort-select-wrapper">
                            <select id="my-reports-sort" name="sort" aria-label="Sort reports">
                                <option value="newest" <?php echo $currentSort === 'newest' ? 'selected' : ''; ?>>Newest first</option>
                                <option value="oldest" <?php echo $currentSort === 'oldest' ? 'selected' : ''; ?>>Oldest first</option>
                                <option value="updated" <?php echo $currentSort === 'updated' ? 'selected' : ''; ?>>Recently updated</option>
                            </select>
                            <svg class="sort-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- 4. Contextual Search / Filter Feedback -->
                <?php if ($search !== '' || $currentStatus !== 'all'): ?>
                    <div class="my-reports-state-bar">
                        <div class="state-bar-left">
                            <?php if ($search !== ''): ?>
                                <span class="state-indicator search-indicator">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                    Searching: <strong>"<?php echo myReportsEscape($search); ?>"</strong>
                                    <a href="<?php echo myReportsEscape(myReportsUrl(['q' => '', 'page' => 1])); ?>" class="remove-chip-btn" aria-label="Remove search query" title="Clear search query">×</a>
                                </span>
                            <?php endif; ?>

                            <?php if ($currentStatus !== 'all'): ?>
                                <span class="state-indicator status-indicator">
                                    Status: <strong><?php echo myReportsEscape(ucfirst($currentStatus)); ?></strong>
                                    <a href="<?php echo myReportsEscape(myReportsUrl(['status' => 'all', 'page' => 1])); ?>" class="remove-chip-btn" aria-label="Remove status filter" title="Clear status filter">×</a>
                                </span>
                            <?php endif; ?>

                            <span class="state-results-count">
                                (<?php echo $filteredCount; ?> <?php echo $filteredCount === 1 ? 'report' : 'reports'; ?> found)
                            </span>
                        </div>

                        <?php if ($hasActiveFilters): ?>
                            <a href="<?php echo myReportsEscape(myReportsClearUrl()); ?>" class="clear-all-filters-btn">
                                Clear all filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- 5. Reports Listing or Empty States -->
                <section class="my-reports-content-section" aria-labelledby="reports-heading">
                    <h2 id="reports-heading" class="sr-only">Your Reports Listing</h2>

                    <?php if (!empty($items)): ?>
                        <div class="my-reports-grid">
                            <?php foreach ($items as $item): ?>
                                <?php
                                $itemTitle = (string) ($item['title'] ?? 'Untitled Item');
                                $imageUrl = myReportsItemImageUrl($item['image_path'] ?? null);
                                $hasCustomImage = ($imageUrl !== (BASE_URL . '/assets/images/default_no_image.png'));
                                $imageCount = (int) ($item['image_count'] ?? 0);
                                $reportType = (string) ($item['report_type'] ?? 'lost');
                                $isLost = ($reportType === 'lost');
                                $status = (string) ($item['status'] ?? 'pending');
                                
                                // Actual event date (Lost date for lost reports, Found date for found reports)
                                $eventDate = !empty($item['event_date']) ? (string) $item['event_date'] : null;
                                $datePrefix = $isLost ? 'Lost' : 'Found';

                                // Claim statistics
                                $pendingClaims = (int) ($item['pending_claims'] ?? 0);
                                $approvedClaims = (int) ($item['approved_claims'] ?? 0);
                                $totalClaims = (int) ($item['total_claims'] ?? 0);

                                $detailsUrl = '?item=' . rawurlencode((string) $item['item_code']);
                                ?>
                                <article class="my-report-card" data-item-id="<?php echo myReportsEscape((string) $item['item_code']); ?>" data-item-context="my_reports" data-item-detail>
                                    <div class="card-image-wrapper">
                                        <a href="<?php echo myReportsEscape($detailsUrl); ?>" class="card-image-link" data-item-id="<?php echo myReportsEscape((string) $item['item_code']); ?>" data-item-context="my_reports" data-item-detail tabindex="-1" aria-hidden="true">
                                            <img src="<?php echo myReportsEscape($imageUrl); ?>"
                                                 data-fallback="<?php echo myReportsEscape(BASE_URL . '/assets/images/default_no_image.png'); ?>"
                                                 alt="<?php echo myReportsEscape($hasCustomImage ? $itemTitle . ' image' : 'Default item image'); ?>"
                                                 loading="lazy">
                                        </a>

                                        <!-- Lost / Found Badge -->
                                        <span class="report-type-badge <?php echo $isLost ? 'is-lost' : 'is-found'; ?>">
                                            <?php echo $isLost ? 'Lost' : 'Found'; ?>
                                        </span>

                                        <!-- Multi-photo count badge -->
                                        <?php if ($imageCount > 1): ?>
                                            <span class="report-photo-count" title="<?php echo $imageCount; ?> images uploaded">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                    <polyline points="21 15 16 10 5 21"></polyline>
                                                </svg>
                                                <?php echo $imageCount; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-body-content">
                                        <!-- Top Status Row -->
                                        <div class="card-status-row">
                                            <span class="my-report-status-badge status-<?php echo myReportsEscape($status); ?>">
                                                <?php if ($status === 'pending'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <polyline points="12 6 12 12 16 14"></polyline>
                                                    </svg>
                                                <?php elseif ($status === 'active'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <path d="m9 12 2 2 4-4"></path>
                                                    </svg>
                                                <?php elseif ($status === 'claimed'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                                        <circle cx="9" cy="7" r="4"></circle>
                                                        <polyline points="16 11 18 13 22 9"></polyline>
                                                    </svg>
                                                <?php elseif ($status === 'returned'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                                    </svg>
                                                <?php endif; ?>
                                                <span><?php echo myReportsEscape(ucfirst($status)); ?></span>
                                            </span>

                                            <span class="card-item-code"><?php echo myReportsEscape((string) $item['item_code']); ?></span>
                                        </div>

                                        <!-- Item Title -->
                                        <h3 class="card-item-title">
                                            <a href="<?php echo myReportsEscape($detailsUrl); ?>">
                                                <?php echo myReportsEscape($itemTitle); ?>
                                            </a>
                                        </h3>

                                        <!-- Category -->
                                        <div class="card-item-category">
                                            <?php echo myReportsEscape(myReportsCategoryLabel((string) ($item['category'] ?? ''))); ?>
                                        </div>

                                        <!-- Location Meta -->
                                        <div class="card-meta-line card-location">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z"></path>
                                                <circle cx="12" cy="10" r="3"></circle>
                                            </svg>
                                            <span><?php echo myReportsEscape((string) ($item['event_location'] ?? 'Location not provided')); ?></span>
                                        </div>

                                        <!-- Actual Lost/Found Date Meta -->
                                        <div class="card-meta-line card-date">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                            </svg>
                                            <span>
                                                <?php if ($eventDate): ?>
                                                    <?php echo $datePrefix; ?>: <time datetime="<?php echo myReportsEscape($eventDate); ?>"><?php echo myReportsEscape(getRelativeTime($eventDate)); ?></time>
                                                <?php else: ?>
                                                    Date unavailable
                                                <?php endif; ?>
                                            </span>
                                        </div>

                                        <!-- Claim Indicator Section -->
                                        <div class="card-claim-indicator">
                                            <?php if ($pendingClaims > 0): ?>
                                                <span class="claim-badge claim-pending">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                                    </svg>
                                                    <?php echo $pendingClaims; ?> <?php echo $pendingClaims === 1 ? 'claim awaiting review' : 'claims awaiting review'; ?>
                                                </span>
                                            <?php elseif ($approvedClaims > 0): ?>
                                                <span class="claim-badge claim-approved">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                    </svg>
                                                    Claim approved
                                                </span>
                                            <?php elseif ($totalClaims > 0): ?>
                                                <span class="claim-badge claim-reviewed">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <polyline points="12 6 12 12 14 14"></polyline>
                                                    </svg>
                                                    <?php echo $totalClaims; ?> <?php echo $totalClaims === 1 ? 'claim processed' : 'claims processed'; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="claim-badge claim-none">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                                    </svg>
                                                    No claims
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Card Action -->
                                        <div class="card-footer-action">
                                            <a href="<?php echo myReportsEscape($detailsUrl); ?>" class="card-action-btn">
                                                <span>View Details</span>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                                    <polyline points="12 5 19 12 12 19"></polyline>
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Empty States -->
                        <div class="my-reports-empty-state">
                            <div class="empty-state-icon-wrap" aria-hidden="true">
                                <?php if ($totalUserReports === 0): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="12" y1="18" x2="12" y2="12"></line>
                                        <line x1="9" y1="15" x2="15" y2="15"></line>
                                    </svg>
                                <?php elseif ($search !== ''): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        <line x1="8" y1="11" x2="14" y2="11"></line>
                                    </svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                        <line x1="9" y1="14" x2="15" y2="14"></line>
                                    </svg>
                                <?php endif; ?>
                            </div>

                            <?php if ($totalUserReports === 0): ?>
                                <!-- CASE 1: No reports created yet -->
                                <h3>No Reports Yet</h3>
                                <p>You haven't reported any lost or found items yet.</p>
                                <a href="<?php echo myReportsEscape(BASE_URL . '/items/report.php'); ?>" class="btn btn-primary empty-action-btn">
                                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    <span>Report an Item</span>
                                </a>
                            <?php elseif ($search !== ''): ?>
                                <!-- CASE 3: Search returns no matching reports -->
                                <h3>No Matching Reports</h3>
                                <p>We couldn't find any of your reports matching your search.</p>
                                <div class="empty-state-buttons">
                                    <a href="<?php echo myReportsEscape(myReportsUrl(['q' => '', 'page' => 1])); ?>" class="btn btn-primary">
                                        Clear Search
                                    </a>
                                    <?php if ($currentStatus !== 'all'): ?>
                                        <a href="<?php echo myReportsEscape(myReportsClearUrl()); ?>" class="empty-text-link">
                                            Reset all filters
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- CASE 2: Status has no reports -->
                                <h3>No reports in this category</h3>
                                <p>You don't have any reports currently marked as <strong><?php echo myReportsEscape(ucfirst($currentStatus)); ?></strong>.</p>
                                <a href="<?php echo myReportsEscape(myReportsUrl(['status' => 'all', 'page' => 1])); ?>" class="btn btn-primary">
                                    View All Reports
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- 6. Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="my-reports-pagination" aria-label="Reports pagination">
                            <?php if ($page > 1): ?>
                                <a class="pagination-btn pagination-prev" href="<?php echo myReportsEscape(myReportsUrl(['page' => $page - 1])); ?>">
                                    ← <span>Previous</span>
                                </a>
                            <?php endif; ?>

                            <div class="pagination-numbers">
                                <?php $prevNum = 0; ?>
                                <?php foreach ($pageNumbers as $num): ?>
                                    <?php if ($num - $prevNum > 1): ?>
                                        <span class="pagination-ellipsis" aria-hidden="true">…</span>
                                    <?php endif; ?>
                                    <?php if ($num === $page): ?>
                                        <span class="pagination-number is-current" aria-current="page"><?php echo $num; ?></span>
                                    <?php else: ?>
                                        <a class="pagination-number" href="<?php echo myReportsEscape(myReportsUrl(['page' => $num])); ?>"><?php echo $num; ?></a>
                                    <?php endif; ?>
                                    <?php $prevNum = $num; ?>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($page < $totalPages): ?>
                                <a class="pagination-btn pagination-next" href="<?php echo myReportsEscape(myReportsUrl(['page' => $page + 1])); ?>">
                                    <span>Next</span> →
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <!-- Shared UI Interactions -->
    <script src="../assets/js/components.js"></script>
    <!-- My Reports Specific Interactions & Contextual Search Adapter -->
    <script src="my_reports.js?v=<?php echo time(); ?>"></script>
    <?php require_once __DIR__ . '/../components/item-details/item-details.php'; ?>
</body>
</html>
