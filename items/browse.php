<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Browse Items
|--------------------------------------------------------------------------
| Read-only discovery page for active lost and found reports.
*/

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../backend/utils/helpers.php';
require_once __DIR__ . '/../backend/auth/auth_check.php';

if (!defined('BROWSE_DEFAULT_IMAGE_URL')) {
    define('BROWSE_DEFAULT_IMAGE_URL', BASE_URL . '/assets/images/default_no_image.png');
}
if (!defined('BROWSE_PER_PAGE')) {
    define('BROWSE_PER_PAGE', 12);
}

if (!function_exists('browseGetString')) {
    /** @return string A bounded scalar GET value. */
    function browseGetString(string $key, int $maxLength = 255): string
    {
        $value = $_GET[$key] ?? '';

        if (!is_string($value)) {
            return '';
        }

        return trim(mb_substr($value, 0, $maxLength));
    }
}

if (!function_exists('browseEscape')) {
    function browseEscape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('browseCategoryLabel')) {
    function browseCategoryLabel(string $category): string
    {
        $category = trim(str_replace(['_', '-'], ' ', $category));

        return $category === '' ? 'Uncategorized' : ucwords($category);
    }
}

if (!function_exists('browseItemImageUrl')) {
    /**
     * Only expose an image when it is a real file stored in the project's item
     * upload directory. This prevents a malformed database value from becoming a
     * browser path and makes the default image the safe fallback.
     */
    function browseItemImageUrl(?string $storedPath): string
    {
        if (!$storedPath) {
            return BROWSE_DEFAULT_IMAGE_URL;
        }

        $relativePath = ltrim($storedPath, '/');
        $requiredPrefix = 'assets/uploads/items/';

        if (
            strpos($relativePath, $requiredPrefix) !== 0 ||
            strpos($relativePath, '..') !== false ||
            strpos($relativePath, '\\') !== false
        ) {
            return BROWSE_DEFAULT_IMAGE_URL;
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
            return BROWSE_DEFAULT_IMAGE_URL;
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $relativePath)));

        return BASE_URL . '/' . $encodedPath;
    }
}

if (!function_exists('browseUrl')) {
    /**
     * Build URL preserving filter state with optional overrides.
     *
     * @param array<string, string|int> $overrides
     */
    function browseUrl(array $overrides = []): string
    {
        $values = [
            'q'        => browseGetString('q', 150),
            'type'     => browseGetString('type', 10),
            'status'   => browseGetString('status', 20),
            'category' => browseGetString('category', 100),
            'location' => browseGetString('location', 255),
            'date'     => browseGetString('date', 10),
            'sort'     => browseGetString('sort', 20),
            'page'     => (int) ($_GET['page'] ?? 1),
        ];

        foreach ($overrides as $key => $value) {
            $values[$key] = $value;
        }

        $values = array_filter(
            $values,
            static function ($value, $key): bool {
                if ($key === 'type' && ($value === '' || $value === 'all')) {
                    return false;
                }
                if ($key === 'status' && ($value === '' || $value === 'all')) {
                    return false;
                }
                if ($key === 'sort' && ($value === '' || $value === 'newest')) {
                    return false;
                }
                if ($key === 'page' && (int) $value <= 1) {
                    return false;
                }
                if (trim((string) $value) === '') {
                    return false;
                }
                return true;
            },
            ARRAY_FILTER_USE_BOTH
        );

        $query = http_build_query($values);

        return BASE_URL . '/items/browse.php' . ($query !== '' ? '?' . $query : '');
    }
}

if (!function_exists('browseClearUrl')) {
    /**
     * Reset URL to clear all filters, search, and sorting.
     */
    function browseClearUrl(): string
    {
        return BASE_URL . '/items/browse.php';
    }
}

// -------------------------------------------------------------------------
// Search & Filter Parameters
// -------------------------------------------------------------------------
$search = browseGetString('q', 150);
$reportType = browseGetString('type', 10);
$status = browseGetString('status', 20);
$category = browseGetString('category', 100);
$location = browseGetString('location', 255);
$date = browseGetString('date', 10);
$sort = browseGetString('sort', 20);

if (!in_array($reportType, ['all', 'lost', 'found'], true)) {
    $reportType = 'all';
}

$validStatuses = ['all', 'active', 'pending', 'claimed', 'returned', 'closed'];
if (!in_array($status, $validStatuses, true)) {
    $status = 'all';
}

if (!in_array($sort, ['newest', 'oldest', 'updated'], true)) {
    $sort = 'newest';
}

$dateObject = DateTime::createFromFormat('!Y-m-d', $date);
if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
    $date = '';
}

$rawPage = $_GET['page'] ?? '1';
$page = is_string($rawPage) && ctype_digit($rawPage) ? max(1, (int) $rawPage) : 1;

// -------------------------------------------------------------------------
// Live Real Database Counts for Community Items Statuses
// -------------------------------------------------------------------------
$statusCountsStatement = $pdo->prepare('
    SELECT status, COUNT(*) AS count
    FROM items
    GROUP BY status
');
$statusCountsStatement->execute();
$rawStatusCounts = $statusCountsStatement->fetchAll(PDO::FETCH_KEY_PAIR);

$statusCounts = [
    'active'   => (int) ($rawStatusCounts['active'] ?? 0),
    'pending'  => (int) ($rawStatusCounts['pending'] ?? 0),
    'claimed'  => (int) ($rawStatusCounts['claimed'] ?? 0),
    'returned' => (int) ($rawStatusCounts['returned'] ?? 0),
    'closed'   => (int) ($rawStatusCounts['closed'] ?? 0),
];
$totalCommunityItems = array_sum($statusCounts);

// -------------------------------------------------------------------------
// Dynamic Categories from Database
// -------------------------------------------------------------------------
$categoryStatement = $pdo->prepare("
    SELECT DISTINCT category
    FROM items
    WHERE category IS NOT NULL
      AND category <> ''
    ORDER BY category ASC
");
$categoryStatement->execute();
$availableCategories = $categoryStatement->fetchAll(PDO::FETCH_COLUMN);

// -------------------------------------------------------------------------
// Construct Dynamic Query with Strict Parameter Binding (HY093 Safe)
// -------------------------------------------------------------------------
$whereClauses = [];
$queryParameters = [];

// Status filter: if not 'all', filter by status
if ($status !== 'all') {
    $whereClauses[] = 'i.status = :status_filter';
    $queryParameters['status_filter'] = $status;
}

// Report type filter: if not 'all', filter by report_type
if ($reportType !== 'all') {
    $whereClauses[] = 'i.report_type = :report_type_filter';
    $queryParameters['report_type_filter'] = $reportType;
}

// Contextual search query
if ($search !== '') {
    $searchValue = '%' . $search . '%';
    $searchColumns = [
        'title'          => 'search_title',
        'description'    => 'search_desc',
        'category'       => 'search_cat',
        'subcategory'    => 'search_subcat',
        'event_location' => 'search_loc',
    ];
    $searchConditions = [];

    foreach ($searchColumns as $column => $paramName) {
        $searchConditions[] = 'i.' . $column . ' LIKE :' . $paramName;
        $queryParameters[$paramName] = $searchValue;
    }

    $whereClauses[] = '(' . implode(' OR ', $searchConditions) . ')';
}

// Category filter
if ($category !== '') {
    $whereClauses[] = 'i.category = :category_filter';
    $queryParameters['category_filter'] = $category;
}

// Location filter (free-text match)
if ($location !== '') {
    $whereClauses[] = 'i.event_location LIKE :location_filter';
    $queryParameters['location_filter'] = '%' . $location . '%';
}

// Date filter: MUST use event_date (item's lost/found date, never created_at)
if ($date !== '') {
    $whereClauses[] = 'i.event_date = :event_date_filter';
    $queryParameters['event_date_filter'] = $date;
}

$whereSql = !empty($whereClauses) ? implode(' AND ', $whereClauses) : '1=1';

// Sorting options based on event_date and updated_at
$orderBy = [
    'newest'  => 'i.event_date DESC, i.id DESC',
    'oldest'  => 'i.event_date ASC, i.id ASC',
    'updated' => 'i.updated_at DESC, i.id DESC',
][$sort];

// Count matching items
$countStatement = $pdo->prepare("SELECT COUNT(*) FROM items i WHERE {$whereSql}");
$countStatement->execute($queryParameters);
$filteredCount = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($filteredCount / BROWSE_PER_PAGE));
$page = min($page, $totalPages);
$offset = ($page - 1) * BROWSE_PER_PAGE;

// Fetch items for the current page
$itemsStatement = $pdo->prepare("
    SELECT
        i.id,
        i.item_code,
        i.title,
        i.category,
        i.report_type,
        i.event_location,
        i.event_date,
        i.status,
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
        ) AS image_count
    FROM items i
    WHERE {$whereSql}
    ORDER BY {$orderBy}
    LIMIT :limit OFFSET :offset
");

foreach ($queryParameters as $parameter => $value) {
    $itemsStatement->bindValue(':' . $parameter, $value, PDO::PARAM_STR);
}
$itemsStatement->bindValue(':limit', BROWSE_PER_PAGE, PDO::PARAM_INT);
$itemsStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$itemsStatement->execute();
$items = $itemsStatement->fetchAll(PDO::FETCH_ASSOC);

$hasActiveFilters = (
    $search !== '' ||
    $reportType !== 'all' ||
    $status !== 'all' ||
    $category !== '' ||
    $location !== '' ||
    $date !== '' ||
    $sort !== 'newest'
);
$shownFrom = $filteredCount === 0 ? 0 : $offset + 1;
$shownTo = $filteredCount === 0 ? 0 : min($offset + BROWSE_PER_PAGE, $filteredCount);
$pageNumbers = [];

if ($totalPages > 1) {
    $pageNumbers[] = 1;
    for ($number = max(2, $page - 1); $number <= min($totalPages - 1, $page + 1); $number++) {
        $pageNumbers[] = $number;
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
    <title>Browse Items - <?php echo browseEscape(APP_NAME); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="browse.css?v=<?php echo time(); ?>">
</head>
<body class="app-body browse-page">

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="app-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="app-main-content" id="main-content">
            <div class="browse-content-wrapper animate-fade-in"
                 id="browse-page"
                 data-search-context="browse"
                 data-search-target="<?php echo browseEscape(BASE_URL . '/items/browse.php'); ?>"
                 data-current-query="<?php echo browseEscape($search); ?>"
                 data-current-type="<?php echo browseEscape($reportType); ?>"
                 data-current-status="<?php echo browseEscape($status); ?>"
                 data-current-category="<?php echo browseEscape($category); ?>"
                 data-current-location="<?php echo browseEscape($location); ?>"
                 data-current-date="<?php echo browseEscape($date); ?>"
                 data-current-sort="<?php echo browseEscape($sort); ?>">

                <!-- 1. Page Header -->
                <header class="browse-page-header">
                    <div class="browse-header-text">
                        <p class="browse-eyebrow">LostLink community</p>
                        <h1 class="browse-title">Browse Items</h1>
                        <p class="browse-subtitle">Find lost and found items reported by the LostLink community.</p>
                    </div>
                    <div class="browse-report-count" aria-label="<?php echo $totalCommunityItems; ?> total items">
                        <span class="browse-report-count-value"><?php echo $totalCommunityItems; ?></span>
                        <span>community <?php echo $totalCommunityItems === 1 ? 'item' : 'items'; ?></span>
                    </div>
                </header>

                <?php
                $statusTabs = [
                    'all'      => ['label' => 'All', 'count' => $totalCommunityItems],
                    'active'   => ['label' => 'Active', 'count' => $statusCounts['active']],
                    'pending'  => ['label' => 'Pending', 'count' => $statusCounts['pending']],
                    'claimed'  => ['label' => 'Claimed', 'count' => $statusCounts['claimed']],
                    'returned' => ['label' => 'Returned', 'count' => $statusCounts['returned']],
                    'closed'   => ['label' => 'Closed', 'count' => $statusCounts['closed']],
                ];
                $activeSecondaryCount = ($category !== '' ? 1 : 0) + ($location !== '' ? 1 : 0) + ($date !== '' ? 1 : 0);
                ?>

                <!-- 2. Primary Filter Toolbar (Desktop Only: Type & Status Pills) -->
                <div class="browse-toolbar browse-desktop-toolbar">
                    <div class="browse-pills-group" role="group" aria-label="Filter items by type and status">
                        <!-- Report Type Pills -->
                        <div class="browse-type-filters" role="group" aria-label="Filter by report type">
                            <a href="<?php echo browseEscape(browseUrl(['type' => 'all', 'page' => 1])); ?>"
                                class="status-filter-tab <?php echo $reportType === 'all' ? 'is-selected' : ''; ?>"
                                aria-pressed="<?php echo $reportType === 'all' ? 'true' : 'false'; ?>">
                                <span>All Items</span>
                            </a>
                            <a href="<?php echo browseEscape(browseUrl(['type' => 'lost', 'page' => 1])); ?>"
                                class="status-filter-tab <?php echo $reportType === 'lost' ? 'is-selected' : ''; ?>"
                                aria-pressed="<?php echo $reportType === 'lost' ? 'true' : 'false'; ?>">
                                <span>Lost</span>
                            </a>
                            <a href="<?php echo browseEscape(browseUrl(['type' => 'found', 'page' => 1])); ?>"
                                class="status-filter-tab <?php echo $reportType === 'found' ? 'is-selected' : ''; ?>"
                                aria-pressed="<?php echo $reportType === 'found' ? 'true' : 'false'; ?>">
                                <span>Found</span>
                            </a>
                        </div>

                        <span class="browse-filter-divider" aria-hidden="true"></span>

                        <!-- Status Filter Pills (Real Community Counts) -->
                        <div class="browse-status-filters" role="group" aria-label="Filter by item status">
                            <?php
                            foreach ($statusTabs as $tabKey => $tab):
                                $isSelected = ($status === $tabKey);
                            ?>
                                <a href="<?php echo browseEscape(browseUrl(['status' => $tabKey, 'page' => 1])); ?>"
                                   class="status-filter-tab <?php echo $isSelected ? 'is-selected' : ''; ?>"
                                   aria-pressed="<?php echo $isSelected ? 'true' : 'false'; ?>">
                                    <span><?php echo $tab['label']; ?></span>
                                    <span class="tab-count"><?php echo $tab['count']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 3. Compact Secondary Filters Row (Desktop Only: Category, Location, Date, Sort by) -->
                <div class="browse-secondary-filters browse-desktop-secondary-filters" id="browse-secondary-filters" role="region" aria-label="Additional filters">
                    <div class="browse-secondary-field browse-category-field">
                        <label for="browse-category" class="browse-field-label">Category</label>
                        <div class="browse-select-wrapper">
                            <select id="browse-category" name="category" aria-label="Filter by category">
                                <option value="">All categories</option>
                                <?php foreach ($availableCategories as $availableCategory): ?>
                                    <option value="<?php echo browseEscape((string) $availableCategory); ?>" <?php echo $category === $availableCategory ? 'selected' : ''; ?>>
                                        <?php echo browseEscape(browseCategoryLabel((string) $availableCategory)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="browse-select-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                    </div>

                    <div class="browse-secondary-field browse-location-field">
                        <label for="browse-location" class="browse-field-label">Location</label>
                        <div class="browse-input-wrapper">
                            <svg class="browse-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <input id="browse-location" type="search" name="location" value="<?php echo browseEscape($location); ?>" maxlength="255" placeholder="Any location" aria-label="Filter by location" autocomplete="off">
                        </div>
                    </div>

                    <div class="browse-secondary-field browse-date-field">
                        <label for="browse-date" class="browse-field-label">Item Date</label>
                        <div class="browse-input-wrapper">
                            <input id="browse-date" type="date" name="date" value="<?php echo browseEscape($date); ?>" aria-label="Filter by item date">
                        </div>
                    </div>

                    <div class="browse-secondary-field browse-sort-field">
                        <label for="browse-sort" class="browse-field-label">Sort by</label>
                        <div class="browse-select-wrapper">
                            <select id="browse-sort" name="sort" aria-label="Sort items">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest first</option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest first</option>
                                <option value="updated" <?php echo $sort === 'updated' ? 'selected' : ''; ?>>Recently updated</option>
                            </select>
                            <svg class="browse-select-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- 3b. Mobile Filter Toolbar (Mobile Only: Type, Status, Filters, Sort) -->
                <div class="browse-mobile-toolbar" id="browse-mobile-toolbar" role="region" aria-label="Mobile filter toolbar">
                    <!-- Mobile Type Dropdown -->
                    <div class="browse-mobile-dropdown" data-dropdown="type">
                        <button type="button" class="browse-mobile-trigger <?php echo $reportType !== 'all' ? 'is-active' : ''; ?>" aria-haspopup="true" aria-expanded="false" id="mobile-type-trigger">
                            <span class="mobile-trigger-label">Type</span>
                            <?php if ($reportType !== 'all'): ?>
                                <span class="mobile-trigger-indicator">• <?php echo browseEscape(ucfirst($reportType)); ?></span>
                            <?php endif; ?>
                            <svg class="mobile-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="browse-mobile-menu" id="mobile-type-menu" role="menu" aria-labelledby="mobile-type-trigger">
                            <a href="<?php echo browseEscape(browseUrl(['type' => 'all', 'page' => 1])); ?>" class="mobile-menu-item <?php echo $reportType === 'all' ? 'is-selected' : ''; ?>" role="menuitem">
                                <span>All Items</span>
                                <?php if ($reportType === 'all'): ?>
                                    <svg class="mobile-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo browseEscape(browseUrl(['type' => 'lost', 'page' => 1])); ?>" class="mobile-menu-item <?php echo $reportType === 'lost' ? 'is-selected' : ''; ?>" role="menuitem">
                                <span>Lost</span>
                                <?php if ($reportType === 'lost'): ?>
                                    <svg class="mobile-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo browseEscape(browseUrl(['type' => 'found', 'page' => 1])); ?>" class="mobile-menu-item <?php echo $reportType === 'found' ? 'is-selected' : ''; ?>" role="menuitem">
                                <span>Found</span>
                                <?php if ($reportType === 'found'): ?>
                                    <svg class="mobile-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>

                    <!-- Mobile Status Dropdown -->
                    <div class="browse-mobile-dropdown" data-dropdown="status">
                        <button type="button" class="browse-mobile-trigger <?php echo $status !== 'all' ? 'is-active' : ''; ?>" aria-haspopup="true" aria-expanded="false" id="mobile-status-trigger">
                            <span class="mobile-trigger-label">Status</span>
                            <?php if ($status !== 'all'): ?>
                                <span class="mobile-trigger-indicator">• <?php echo browseEscape(ucfirst($status)); ?></span>
                            <?php endif; ?>
                            <svg class="mobile-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="browse-mobile-menu" id="mobile-status-menu" role="menu" aria-labelledby="mobile-status-trigger">
                            <?php foreach ($statusTabs as $tabKey => $tab):
                                $isSelected = ($status === $tabKey);
                            ?>
                                <a href="<?php echo browseEscape(browseUrl(['status' => $tabKey, 'page' => 1])); ?>" class="mobile-menu-item <?php echo $isSelected ? 'is-selected' : ''; ?>" role="menuitem">
                                    <span class="mobile-menu-text"><?php echo $tab['label']; ?></span>
                                    <span class="mobile-menu-right">
                                        <span class="mobile-count-badge"><?php echo $tab['count']; ?></span>
                                        <?php if ($isSelected): ?>
                                            <svg class="mobile-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Mobile Filters Dropdown (Category, Location, Item Date) -->
                    <div class="browse-mobile-dropdown" data-dropdown="filters">
                        <button type="button" class="browse-mobile-trigger <?php echo $activeSecondaryCount > 0 ? 'is-active' : ''; ?>" aria-haspopup="true" aria-expanded="false" id="mobile-filters-trigger">
                            <span class="mobile-trigger-label">Filters</span>
                            <?php if ($activeSecondaryCount > 0): ?>
                                <span class="mobile-trigger-indicator">• <?php echo $activeSecondaryCount; ?></span>
                            <?php endif; ?>
                            <svg class="mobile-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="browse-mobile-menu browse-mobile-filters-panel" id="mobile-filters-menu" role="region" aria-labelledby="mobile-filters-trigger">
                            <div class="mobile-filter-group">
                                <label for="mobile-category" class="mobile-group-label">Category</label>
                                <div class="browse-select-wrapper">
                                    <select id="mobile-category" name="category" aria-label="Filter by category">
                                        <option value="">All categories</option>
                                        <?php foreach ($availableCategories as $availableCategory): ?>
                                            <option value="<?php echo browseEscape((string) $availableCategory); ?>" <?php echo $category === $availableCategory ? 'selected' : ''; ?>>
                                                <?php echo browseEscape(browseCategoryLabel((string) $availableCategory)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <svg class="browse-select-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </div>
                            </div>

                            <div class="mobile-filter-group browse-location-field">
                                <label for="mobile-location" class="mobile-group-label">Location</label>
                                <div class="browse-input-wrapper">
                                    <svg class="browse-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <input id="mobile-location" type="search" name="location" value="<?php echo browseEscape($location); ?>" maxlength="255" placeholder="Any location" aria-label="Filter by location" autocomplete="off">
                                </div>
                            </div>

                            <div class="mobile-filter-group">
                                <label for="mobile-date" class="mobile-group-label">Item Date</label>
                                <div class="browse-input-wrapper">
                                    <input id="mobile-date" type="date" name="date" value="<?php echo browseEscape($date); ?>" aria-label="Filter by item date">
                                </div>
                            </div>

                            <div class="mobile-filters-actions">
                                <button type="button" class="mobile-apply-btn" id="mobile-apply-filters">Apply Filters</button>
                                <?php if ($activeSecondaryCount > 0): ?>
                                    <button type="button" class="mobile-reset-btn" id="mobile-reset-filters">Reset</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Sort Dropdown -->
                    <div class="browse-mobile-dropdown" data-dropdown="sort">
                        <button type="button" class="browse-mobile-trigger <?php echo $sort !== 'newest' ? 'is-active' : ''; ?>" aria-haspopup="true" aria-expanded="false" id="mobile-sort-trigger">
                            <span class="mobile-trigger-label">Sort</span>
                            <?php if ($sort !== 'newest'): ?>
                                <span class="mobile-trigger-indicator">• <?php echo $sort === 'oldest' ? 'Oldest' : 'Updated'; ?></span>
                            <?php endif; ?>
                            <svg class="mobile-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="browse-mobile-menu" id="mobile-sort-menu" role="menu" aria-labelledby="mobile-sort-trigger">
                            <a href="<?php echo browseEscape(browseUrl(['sort' => 'newest', 'page' => 1])); ?>" class="mobile-menu-item <?php echo $sort === 'newest' ? 'is-selected' : ''; ?>" role="menuitem">
                                <span>Newest first</span>
                                <?php if ($sort === 'newest'): ?>
                                    <svg class="mobile-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo browseEscape(browseUrl(['sort' => 'oldest', 'page' => 1])); ?>" class="mobile-menu-item <?php echo $sort === 'oldest' ? 'is-selected' : ''; ?>" role="menuitem">
                                <span>Oldest first</span>
                                <?php if ($sort === 'oldest'): ?>
                                    <svg class="mobile-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo browseEscape(browseUrl(['sort' => 'updated', 'page' => 1])); ?>" class="mobile-menu-item <?php echo $sort === 'updated' ? 'is-selected' : ''; ?>" role="menuitem">
                                <span>Recently updated</span>
                                <?php if ($sort === 'updated'): ?>
                                    <svg class="mobile-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 4. Contextual State / Active Filter Feedback Bar -->
                <?php if ($hasActiveFilters): ?>
                    <div class="browse-state-bar">
                        <div class="state-bar-left">
                            <?php if ($search !== ''): ?>
                                <span class="state-indicator search-indicator">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                    Searching: <strong>"<?php echo browseEscape($search); ?>"</strong>
                                    <a href="<?php echo browseEscape(browseUrl(['q' => '', 'page' => 1])); ?>" class="remove-chip-btn" aria-label="Remove search query" title="Clear search query">×</a>
                                </span>
                            <?php endif; ?>

                            <?php if ($reportType !== 'all'): ?>
                                <span class="state-indicator type-indicator">
                                    Type: <strong><?php echo browseEscape(ucfirst($reportType)); ?></strong>
                                    <a href="<?php echo browseEscape(browseUrl(['type' => 'all', 'page' => 1])); ?>" class="remove-chip-btn" aria-label="Remove type filter" title="Clear type filter">×</a>
                                </span>
                            <?php endif; ?>

                            <?php if ($status !== 'all'): ?>
                                <span class="state-indicator status-indicator">
                                    Status: <strong><?php echo browseEscape(ucfirst($status)); ?></strong>
                                    <a href="<?php echo browseEscape(browseUrl(['status' => 'all', 'page' => 1])); ?>" class="remove-chip-btn" aria-label="Remove status filter" title="Clear status filter">×</a>
                                </span>
                            <?php endif; ?>

                            <?php if ($category !== ''): ?>
                                <span class="state-indicator category-indicator">
                                    Category: <strong><?php echo browseEscape(browseCategoryLabel($category)); ?></strong>
                                    <a href="<?php echo browseEscape(browseUrl(['category' => '', 'page' => 1])); ?>" class="remove-chip-btn" aria-label="Remove category filter" title="Clear category filter">×</a>
                                </span>
                            <?php endif; ?>

                            <?php if ($location !== ''): ?>
                                <span class="state-indicator location-indicator">
                                    Location: <strong>"<?php echo browseEscape($location); ?>"</strong>
                                    <a href="<?php echo browseEscape(browseUrl(['location' => '', 'page' => 1])); ?>" class="remove-chip-btn" aria-label="Remove location filter" title="Clear location filter">×</a>
                                </span>
                            <?php endif; ?>

                            <?php if ($date !== ''): ?>
                                <span class="state-indicator date-indicator">
                                    Date: <strong><?php echo browseEscape($date); ?></strong>
                                    <a href="<?php echo browseEscape(browseUrl(['date' => '', 'page' => 1])); ?>" class="remove-chip-btn" aria-label="Remove date filter" title="Clear date filter">×</a>
                                </span>
                            <?php endif; ?>

                            <span class="state-results-count">
                                (<?php echo $filteredCount; ?> <?php echo $filteredCount === 1 ? 'item' : 'items'; ?> found)
                            </span>
                        </div>

                        <a href="<?php echo browseEscape(browseClearUrl()); ?>" class="clear-all-filters-btn">
                            Clear all filters
                        </a>
                    </div>
                <?php endif; ?>

                <section class="browse-results-section" aria-labelledby="browse-results-heading">
                    <div class="browse-results-header">
                        <div>
                            <h2 id="browse-results-heading">Browse reports</h2>
                            <p>Showing <?php echo $shownFrom; ?><?php echo $shownTo > $shownFrom ? '–' . $shownTo : ''; ?> of <?php echo $filteredCount; ?> <?php echo $filteredCount === 1 ? 'item' : 'items'; ?></p>
                        </div>
                        <?php if ($hasActiveFilters): ?>
                            <a class="browse-clear-filters browse-clear-filters-inline" href="<?php echo browseEscape(browseClearUrl()); ?>">Clear Filters</a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($items)): ?>
                        <div class="browse-items-grid">
                            <?php foreach ($items as $item): ?>
                                <?php
                                $itemTitle = (string) ($item['title'] ?? 'Untitled item');
                                $imageUrl = browseItemImageUrl($item['image_path'] ?? null);
                                $hasItemImage = $imageUrl !== BROWSE_DEFAULT_IMAGE_URL;
                                $photoCount = (int) ($item['image_count'] ?? 0);
                                $itemReportType = (string) ($item['report_type'] ?? '');
                                $isLost = $itemReportType === 'lost';
                                // event_date stores the lost date for lost reports and the found date for found reports.
                                $reportDate = in_array($itemReportType, ['lost', 'found'], true) ? ($item['event_date'] ?? null) : null;
                                $detailsUrl = '?item=' . rawurlencode((string) $item['item_code']);
                                ?>
                                <article class="browse-item-card" data-item-id="<?php echo browseEscape((string) $item['item_code']); ?>" data-item-context="browse" data-item-detail>
                                    <a class="browse-item-card-link" href="<?php echo browseEscape($detailsUrl); ?>" data-item-id="<?php echo browseEscape((string) $item['item_code']); ?>" data-item-context="browse" data-item-detail aria-label="View details for <?php echo browseEscape($itemTitle); ?>">
                                        <div class="browse-item-image-wrap">
                                            <img
                                                src="<?php echo browseEscape($imageUrl); ?>"
                                                data-fallback="<?php echo BROWSE_DEFAULT_IMAGE_URL; ?>"
                                                alt="<?php echo browseEscape($hasItemImage ? $itemTitle . ' report image' : 'No image available'); ?>"
                                                loading="lazy"
                                            >
                                            <span class="browse-item-badge <?php echo $isLost ? 'is-lost' : 'is-found'; ?>">
                                                <?php echo $isLost ? 'Lost' : 'Found'; ?>
                                            </span>
                                            <?php if ($photoCount > 0): ?>
                                                <span class="browse-photo-count">
                                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                        <polyline points="21 15 16 10 5 21"></polyline>
                                                    </svg>
                                                    <?php echo $photoCount; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="browse-item-content">
                                            <h3><?php echo browseEscape($itemTitle); ?></h3>
                                            <p class="browse-item-category"><?php echo browseEscape(browseCategoryLabel((string) ($item['category'] ?? ''))); ?></p>
                                            <p class="browse-item-location">
                                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z"></path>
                                                    <circle cx="12" cy="10" r="3"></circle>
                                                </svg>
                                                <span><?php echo browseEscape((string) ($item['event_location'] ?? 'Location not provided')); ?></span>
                                            </p>
                                            <div class="browse-item-footer">
                                                <?php if ($reportDate): ?>
                                                    <time datetime="<?php echo browseEscape((string) $reportDate); ?>">
                                                        <?php echo browseEscape(getRelativeTime((string) $reportDate)); ?>
                                                    </time>
                                                <?php else: ?>
                                                    <span>Date unavailable</span>
                                                <?php endif; ?>
                                                <span class="browse-view-details">View Details <span aria-hidden="true">→</span></span>
                                            </div>
                                        </div>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="browse-empty-state">
                            <div class="browse-empty-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="7"></circle>
                                    <line x1="20" y1="20" x2="16.2" y2="16.2"></line>
                                </svg>
                            </div>
                            <?php if ($totalCommunityItems === 0): ?>
                                <h3>No items reported yet</h3>
                                <p>There are no Lost or Found reports available in the community at the moment.</p>
                            <?php else: ?>
                                <h3>No matching items</h3>
                                <p>We couldn't find any items matching your active search or filters.</p>
                                <?php if ($hasActiveFilters): ?>
                                    <a class="browse-empty-clear" href="<?php echo browseEscape(browseClearUrl()); ?>">Clear Filters</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalPages > 1): ?>
                        <nav class="browse-pagination" aria-label="Browse item pages">
                            <?php if ($page > 1): ?>
                                <a class="browse-page-button browse-page-previous" href="<?php echo browseEscape(browseUrl(['page' => $page - 1])); ?>">← <span>Previous</span></a>
                            <?php endif; ?>

                            <div class="browse-page-numbers">
                                <?php $previousNumber = 0; ?>
                                <?php foreach ($pageNumbers as $number): ?>
                                    <?php if ($number - $previousNumber > 1): ?><span class="browse-page-ellipsis" aria-hidden="true">…</span><?php endif; ?>
                                    <?php if ($number === $page): ?>
                                        <span class="browse-page-button is-current" aria-current="page"><?php echo $number; ?></span>
                                    <?php else: ?>
                                        <a class="browse-page-button" href="<?php echo browseEscape(browseUrl(['page' => $number])); ?>"><?php echo $number; ?></a>
                                    <?php endif; ?>
                                    <?php $previousNumber = $number; ?>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($page < $totalPages): ?>
                                <a class="browse-page-button browse-page-next" href="<?php echo browseEscape(browseUrl(['page' => $page + 1])); ?>"><span>Next</span> →</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script src="../assets/js/components.js"></script>
    <script src="browse.js?v=<?php echo time(); ?>"></script>
    <?php require_once __DIR__ . '/../components/item-details/item-details.php'; ?>
</body>
</html>
