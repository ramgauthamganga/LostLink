<?php
/**
 * LostLink - Centralized Reusable Item Details Component
 * Option C — Centered Large Sheet
 * 
 * Supports both:
 * 1. API Data Endpoint (when requested via AJAX / direct HTTP with item query)
 * 2. Component HTML/Skeleton (when included by consumer pages)
 */

declare(strict_types=1);

// Determine project root dynamically without hardcoded paths
$projectRoot = dirname(__DIR__, 2);

// Helper polyfills for environments where specific helpers are not globally loaded
if (!function_exists('e')) {
    function e(?string $str): string {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string {
        $base = defined('BASE_URL') ? BASE_URL : '';
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path = ''): string {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date, string $format = 'M d, Y'): string {
        if (!$date) return '';
        $time = is_numeric($date) ? (int)$date : strtotime((string)$date);
        return $time ? date($format, $time) : (string)$date;
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($date): string {
        if (function_exists('getRelativeTime')) {
            return getRelativeTime((string)$date);
        }
        if (!$date) return '';
        $time = strtotime((string)$date);
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        return date('M d, Y', $time);
    }
}

if (!function_exists('csrfToken')) {
    function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            if (function_exists('random_bytes')) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $_SESSION['csrf_token'] = md5(uniqid((string)mt_rand(), true));
            }
        }
        return (string)$_SESSION['csrf_token'];
    }
}

// Detect if this is an API / Data Request
$isApiRequest = (
    basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'item-details.php' ||
    isset($_GET['ll_item_api']) ||
    (isset($_GET['action']) && $_GET['action'] === 'get_item_details')
);

if ($isApiRequest) {
    // =========================================================================
    // API / DATA REQUEST HANDLER (Returns JSON only)
    // =========================================================================

    // Clean output buffers to guarantee pure JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    require_once $projectRoot . '/config/config.php';
    require_once $projectRoot . '/config/database.php';
    require_once $projectRoot . '/config/session.php';

    if (function_exists('sessionInit')) {
        sessionInit();
    } elseif (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $itemIdentifier = trim((string)($_GET['item'] ?? $_GET['id'] ?? ''));
    $pageContext = trim(strtolower((string)($_GET['context'] ?? 'browse')));

    if ($itemIdentifier === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Item identifier is required']);
        exit;
    }

    try {
        if (function_exists('currentUserId')) {
            $currentUserId = currentUserId();
        } else {
            $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        }

        if (function_exists('isAdmin')) {
            $userIsAdmin = isAdmin();
        } else {
            $userIsAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
        }

        // Database connection with fallback for database name resilience
        $dbInstance = null;
        if (isset($pdo) && $pdo instanceof PDO) {
            $dbInstance = $pdo;
        } elseif (function_exists('db')) {
            try {
                $dbInstance = db();
            } catch (Throwable $e) {
            }
        }
        if (!$dbInstance) {
            $dbHost = defined('DB_HOST') ? DB_HOST : ($host ?? 'localhost');
            $dbUser = defined('DB_USER') ? DB_USER : ($username ?? 'root');
            $dbPass = defined('DB_PASS') ? DB_PASS : ($password ?? '');
            $dbCharset = defined('DB_CHARSET') ? DB_CHARSET : ($charset ?? 'utf8mb4');
            $targetDb = defined('DB_NAME') ? DB_NAME : ($dbname ?? 'lostlink');
            
            try {
                $dsn = "mysql:host={$dbHost};dbname={$targetDb};charset={$dbCharset}";
                $dbInstance = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (Throwable $e) {
                $altDb = ($targetDb === 'lostlink') ? 'lostlink_db' : 'lostlink';
                $altDsn = "mysql:host={$dbHost};dbname={$altDb};charset={$dbCharset}";
                $dbInstance = new PDO($altDsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            }
        }
        $pdo = $dbInstance;

        // Resilient item query checking item_code OR numeric id OR public_id
        $numericId = ctype_digit($itemIdentifier) ? (int)$itemIdentifier : 0;

        // Check columns of items table dynamically
        $itemColumnsStmt = $pdo->query("SHOW COLUMNS FROM `items`");
        $itemColumns = $itemColumnsStmt ? $itemColumnsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $hasItemCode = in_array('item_code', $itemColumns, true);
        $hasPublicId = in_array('public_id', $itemColumns, true);
        $hasReportType = in_array('report_type', $itemColumns, true);
        $hasEventLocation = in_array('event_location', $itemColumns, true);
        $hasCategory = in_array('category', $itemColumns, true);
        $hasCategoryId = in_array('category_id', $itemColumns, true);

        $whereClauses = [];
        $params = [];

        if ($hasItemCode) {
            $whereClauses[] = "i.`item_code` = :item_code";
            $params['item_code'] = $itemIdentifier;
        }
        if ($hasPublicId) {
            $whereClauses[] = "i.`public_id` = :public_id";
            $params['public_id'] = $itemIdentifier;
        }
        if ($numericId > 0) {
            $whereClauses[] = "i.`id` = :numeric_id";
            $params['numeric_id'] = $numericId;
        }

        if (empty($whereClauses)) {
            $whereClauses[] = "i.`id` = :raw_id";
            $params['raw_id'] = $numericId;
        }

        $whereSql = '(' . implode(' OR ', $whereClauses) . ')';

        // Check users columns
        $userColsStmt = $pdo->query("SHOW COLUMNS FROM `users`");
        $userCols = $userColsStmt ? $userColsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $userAvatarCol = in_array('profile_image', $userCols, true) ? 'profile_image' : 'avatar';

        // Optional category JOIN if categories table exists
        $joinCategorySql = "";
        $categorySelectSql = "";
        $tablesStmt = $pdo->query("SHOW TABLES LIKE 'categories'");
        $hasCategoriesTable = $tablesStmt && $tablesStmt->rowCount() > 0;

        if ($hasCategoriesTable && $hasCategoryId) {
            $joinCategorySql = " LEFT JOIN `categories` c ON i.`category_id` = c.`id`
                                 LEFT JOIN `categories` sc ON i.`subcategory_id` = sc.`id` ";
            $categorySelectSql = ", c.`name` as `cat_table_name`, sc.`name` as `subcat_table_name` ";
        }

        $itemQuery = "
            SELECT i.*,
                   u.`name` AS `reporter_name`,
                   u.`department` AS `reporter_department`,
                   u.`{$userAvatarCol}` AS `reporter_avatar`,
                   u.`email` AS `reporter_email`,
                   u.`student_id` AS `reporter_student_id`
                   {$categorySelectSql}
            FROM `items` i
            LEFT JOIN `users` u ON i.`user_id` = u.`id`
            {$joinCategorySql}
            WHERE {$whereSql}
            LIMIT 1
        ";

        $stmt = $pdo->prepare($itemQuery);
        $stmt->execute($params);
        $item = $stmt->fetch();

        if (!$item) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Item not found']);
            exit;
        }

        // Authorization check:
        $itemStatus = strtolower((string)($item['status'] ?? 'active'));
        $itemOwnerId = (int)($item['user_id'] ?? 0);
        $isOwner = ($currentUserId !== null && $currentUserId === $itemOwnerId);

        // If item status is unrecognized or deleted
        $validStatuses = ['pending', 'active', 'claimed', 'returned', 'closed'];
        if (!in_array($itemStatus, $validStatuses, true) && !$isOwner && !$userIsAdmin) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Item is not currently available']);
            exit;
        }

        // Canonical item code / ID
        $canonicalItemCode = (string)($item['item_code'] ?? ($item['public_id'] ?? (string)$item['id']));
        $numericItemId = (int)$item['id'];

        // Item type
        $itemType = (string)($item['report_type'] ?? ($item['type'] ?? 'lost'));

        // Category & Subcategory resolution
        $categoryName = (string)($item['cat_table_name'] ?? ($item['category'] ?? 'Uncategorized'));
        $subcategoryName = (string)($item['subcat_table_name'] ?? ($item['subcategory'] ?? ''));
        if ($subcategoryName === 'N/A' || $subcategoryName === 'None') {
            $subcategoryName = '';
        }

        // Location & Event date (never substitute created_at)
        $eventLocation = (string)($item['event_location'] ?? ($item['location'] ?? 'Not specified'));
        $eventDateRaw = (string)($item['event_date'] ?? '');
        $eventDateFormatted = $eventDateRaw !== '' ? formatDate($eventDateRaw, 'M d, Y') : 'Unknown';

        $createdAtRaw = (string)($item['created_at'] ?? '');
        $createdAtFormatted = $createdAtRaw !== '' ? formatDate($createdAtRaw, 'M d, Y') : '';
        $timeAgoText = $createdAtRaw !== '' ? timeAgo($createdAtRaw) : '';

        // Retrieve Images from item_images table
        $images = [];
        $imgColsStmt = $pdo->query("SHOW COLUMNS FROM `item_images`");
        $imgCols = $imgColsStmt ? $imgColsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $imgNameCol = in_array('image', $imgCols, true) ? 'image' : 'filename';
        $imgOrderCol = in_array('image_order', $imgCols, true) ? 'image_order' : 'sort_order';

        $imgStmt = $pdo->prepare("SELECT * FROM `item_images` WHERE `item_id` = :item_id ORDER BY `{$imgOrderCol}` ASC, `id` ASC");
        $imgStmt->execute(['item_id' => $numericItemId]);
        $rawImages = $imgStmt->fetchAll();

        $defaultItemImage = url('assets/images/default_no_image.png');

        if (!empty($rawImages)) {
            foreach ($rawImages as $idx => $imgRow) {
                $filename = (string)($imgRow[$imgNameCol] ?? '');
                if ($filename === '') continue;

                // Resolve image URL
                $imgUrl = '';
                if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
                    $imgUrl = $filename;
                } elseif (str_starts_with($filename, 'assets/') || str_starts_with($filename, 'uploads/')) {
                    $imgUrl = url($filename);
                } else {
                    $imgUrl = url('uploads/items/' . $filename);
                }

                $images[] = [
                    'id' => (int)$imgRow['id'],
                    'url' => $imgUrl,
                    'order' => (int)($imgRow[$imgOrderCol] ?? $idx)
                ];
            }
        }

        // If no images, provide default fallback image
        if (empty($images)) {
            $images[] = [
                'id' => 0,
                'url' => $defaultItemImage,
                'is_default' => true,
                'order' => 0
            ];
        }

        // Reporter avatar resolution
        $defaultUserAvatar = url('profile/default_user.png');
        $rawAvatar = (string)($item['reporter_avatar'] ?? '');
        $reporterAvatarUrl = $defaultUserAvatar;

        if ($rawAvatar !== '' && $rawAvatar !== 'default-profile.png' && $rawAvatar !== 'default_user.png') {
            if (str_starts_with($rawAvatar, 'http://') || str_starts_with($rawAvatar, 'https://')) {
                $reporterAvatarUrl = $rawAvatar;
            } elseif (str_starts_with($rawAvatar, 'assets/') || str_starts_with($rawAvatar, 'uploads/')) {
                $reporterAvatarUrl = url($rawAvatar);
            } else {
                $reporterAvatarUrl = url('uploads/profiles/' . $rawAvatar);
            }
        }

        $reporterName = (string)($item['reporter_name'] ?? 'Campus User');
        $reporterDept = (string)($item['reporter_department'] ?? 'Student');

        // Check Claims for this item
        $existingClaim = null;
        $pendingClaimsCount = 0;

        // Check if current user has an existing claim
        if ($currentUserId !== null) {
            $claimStmt = $pdo->prepare("
                SELECT * FROM `claims` 
                WHERE `item_id` = :item_id AND `claimant_id` = :user_id 
                ORDER BY `created_at` DESC 
                LIMIT 1
            ");
            $claimStmt->execute(['item_id' => $numericItemId, 'user_id' => $currentUserId]);
            $userClaim = $claimStmt->fetch();

            if ($userClaim) {
                $existingClaim = [
                    'id' => (int)$userClaim['id'],
                    'claim_code' => (string)($userClaim['claim_code'] ?? ('CLM-' . $userClaim['id'])),
                    'status' => (string)$userClaim['status'],
                    'created_at' => (string)$userClaim['created_at'],
                    'created_at_formatted' => formatDate((string)$userClaim['created_at'], 'M d, Y')
                ];
            }
        }

        // Count pending claims (for owner or admin)
        if ($isOwner || $userIsAdmin) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) as `c` FROM `claims` WHERE `item_id` = :item_id AND `status` = 'pending'");
            $countStmt->execute(['item_id' => $numericItemId]);
            $pendingClaimsCount = (int)($countStmt->fetch()['c'] ?? 0);
        }

        // Context-aware action permission flags
        $canClaim = false;
        if ($currentUserId !== null && !$isOwner && $itemStatus === 'active') {
            // User cannot claim if they already have an active/pending claim
            if (!$existingClaim || in_array($existingClaim['status'], ['rejected', 'cancelled'], true)) {
                $canClaim = true;
            }
        }

        // Contact Reporter permission: Authenticated visitor who does NOT own the item and item is not closed/returned
        $canContact = ($currentUserId !== null && !$isOwner && !in_array($itemStatus, ['closed', 'returned'], true));

        // Check if there is an existing contact request or active conversation for this user and item
        $existingContactRequest = null;
        $existingConversation = null;
        if ($currentUserId !== null && !$isOwner) {
            try {
                $crStmt = $pdo->prepare("SELECT id, request_code, status, initial_message FROM contact_requests WHERE item_id = :item_id AND sender_id = :sender_id ORDER BY id DESC LIMIT 1");
                $crStmt->execute(['item_id' => $numericItemId, 'sender_id' => $currentUserId]);
                $crRow = $crStmt->fetch();
                if ($crRow) {
                    $existingContactRequest = [
                        'id' => (int)$crRow['id'],
                        'request_code' => (string)$crRow['request_code'],
                        'status' => (string)$crRow['status']
                    ];
                }

                $cnvStmt = $pdo->prepare("SELECT id, conversation_code, status FROM conversations WHERE item_id = :item_id AND (participant_one_id = :uid1 OR participant_two_id = :uid2) AND status = 'active' LIMIT 1");
                $cnvStmt->execute(['item_id' => $numericItemId, 'uid1' => $currentUserId, 'uid2' => $currentUserId]);
                $cnvRow = $cnvStmt->fetch();
                if ($cnvRow) {
                    $existingConversation = [
                        'id' => (int)$cnvRow['id'],
                        'conversation_code' => (string)$cnvRow['conversation_code'],
                        'status' => (string)$cnvRow['status']
                    ];
                }

                if ($existingConversation !== null || ($existingContactRequest && $existingContactRequest['status'] === 'pending')) {
                    $canContact = false;
                }
            } catch (Throwable $e) {
                // Ignore if tables not yet available in legacy context
            }
        }

        $canEdit = ($isOwner || $userIsAdmin) && in_array($itemStatus, ['pending', 'active'], true);
        $canClose = ($isOwner || $userIsAdmin) && $itemStatus !== 'closed';
        $canDelete = ($isOwner || $userIsAdmin) && $itemStatus === 'pending';

        // Prepare response
        $response = [
            'success' => true,
            'data' => [
                'id' => $numericItemId,
                'item_code' => $canonicalItemCode,
                'title' => (string)$item['title'],
                'type' => $itemType,
                'status' => $itemStatus,
                'category' => $categoryName,
                'subcategory' => $subcategoryName,
                'location' => $eventLocation,
                'event_date' => $eventDateRaw,
                'event_date_formatted' => $eventDateFormatted,
                'created_at' => $createdAtRaw,
                'created_at_formatted' => $createdAtFormatted,
                'time_ago' => $timeAgoText,
                'description' => (string)($item['description'] ?? ''),
                'additional_note' => (string)($item['additional_note'] ?? ''),
                'images' => $images,
                'reporter' => [
                    'name' => $reporterName,
                    'department' => $reporterDept,
                    'avatar_url' => $reporterAvatarUrl,
                    'initials' => strtoupper(mb_substr($reporterName, 0, 1))
                ],
                'permissions' => [
                    'can_claim' => $canClaim,
                    'can_contact' => $canContact,
                    'can_edit' => $canEdit,
                    'can_close' => $canClose,
                    'can_delete' => $canDelete,
                    'is_owner' => $isOwner,
                    'is_admin' => $userIsAdmin,
                    'is_authenticated' => ($currentUserId !== null)
                ],
                'existing_claim' => $existingClaim,
                'existing_contact_request' => $existingContactRequest,
                'existing_conversation' => $existingConversation,
                'pending_claims_count' => $pendingClaimsCount,
                'urls' => [
                    'edit_url' => url('/items/report.php?edit=' . urlencode($canonicalItemCode)),
                    'claim_submit_url' => url('/backend/claims/create.php'),
                    'claim_cancel_url' => url('/backend/claims/update.php'),
                    'claims_list_url' => url('/admin/claims.php?item_id=' . $numericItemId),
                    'contact_submit_url' => url('/backend/messages/contact_request.php?action=create'),
                    'messages_url' => url('/messages/index.php')
                ],
                'csrf_token' => csrfToken()
            ]
        ];

        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        error_log("Item details API error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Unable to load item details at this time.'
        ]);
        exit;
    }
}

// =============================================================================
// COMPONENT HTML OUTPUT (Rendered once per page inclusion)
// =============================================================================
?>
<!-- LostLink Centralized Item Details Component (Option C — Centered Large Sheet) -->
<link rel="stylesheet" href="<?= url('components/item-details/item-details.css') ?>">

<!-- Dimmed Backdrop Overlay -->
<div class="lostlink-item-details-backdrop" id="ll-item-details-backdrop" aria-hidden="true"></div>

<!-- Centered Large Sheet Dialog -->
<section class="lostlink-item-details-sheet" 
         id="ll-item-details-sheet" 
         role="dialog" 
         aria-modal="true" 
         aria-labelledby="ll-item-details-title" 
         tabindex="-1" 
         hidden
         data-base-url="<?= e(BASE_URL) ?>"
         data-default-image="<?= e(url('assets/images/default_no_image.png')) ?>"
         data-default-avatar="<?= e(url('profile/default_user.png')) ?>">

  <!-- Accessible Header Bar -->
  <header class="ll-item-sheet-header">
    <div class="ll-item-header-meta">
      <span class="ll-item-code-badge" id="ll-item-code-badge" title="Canonical Item Code">ITM-000000000</span>
      <span class="badge ll-badge-type" id="ll-badge-type">Lost</span>
      <span class="badge ll-item-context-badge" id="ll-item-context-badge" style="display: none;"></span>
    </div>
    <button type="button" class="ll-item-close-btn" id="ll-item-close-btn" aria-label="Close item details">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </button>
  </header>

  <!-- Sheet Scrollable Body -->
  <div class="ll-item-sheet-body" id="ll-item-sheet-body">

    <!-- Loading State View -->
    <div class="ll-item-state-view ll-item-state-loading" id="ll-item-loading">
      <div class="ll-spinner" aria-hidden="true"></div>
      <p class="ll-loading-text">Loading item details...</p>
    </div>

    <!-- Error State View -->
    <div class="ll-item-state-view ll-item-state-error" id="ll-item-error" hidden>
      <div class="ll-error-icon-wrap" aria-hidden="true">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
      </div>
      <h3 class="ll-error-title" id="ll-error-title">Item Not Available</h3>
      <p class="ll-error-message" id="ll-error-message">The item could not be found or may have been removed.</p>
      <button type="button" class="btn btn-secondary ll-error-close-btn" data-ll-close-sheet>Close</button>
    </div>

    <!-- Active Content Area (2-Column Desktop Grid) -->
    <div class="ll-item-content-grid" id="ll-item-content" hidden>

      <!-- Column Left: Image & Gallery -->
      <div class="ll-item-gallery-col">
        <div class="ll-gallery-viewport">
          <img id="ll-gallery-main-img" 
               class="ll-gallery-main-img" 
               src="<?= e(url('assets/images/default_no_image.png')) ?>" 
               alt="Item image" 
               loading="eager">

          <!-- Gallery Navigation Buttons (Hidden when single image) -->
          <button type="button" class="ll-gallery-nav ll-gallery-prev" id="ll-gallery-prev" aria-label="Previous image" hidden>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
          </button>
          <button type="button" class="ll-gallery-nav ll-gallery-next" id="ll-gallery-next" aria-label="Next image" hidden>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>

          <!-- Gallery Counter Badge (Positioned at top-right) -->
          <div class="ll-gallery-counter" id="ll-gallery-counter" hidden>
            <span id="ll-gallery-current">1</span> / <span id="ll-gallery-total">1</span>
          </div>
        </div>

        <!-- Thumbnail Strip Track (Centered underneath) -->
        <div class="ll-gallery-thumbs" id="ll-gallery-thumbs" hidden aria-label="Image thumbnails"></div>
      </div>

      <!-- Column Right: Information & Metadata -->
      <div class="ll-item-info-col">

        <!-- Title & Status Row -->
        <div class="ll-item-title-row">
          <h2 class="ll-item-title" id="ll-item-details-title">Item Title</h2>
          <span class="badge ll-badge-status" id="ll-badge-status">Active</span>
        </div>

        <!-- Description (Clean intro paragraph directly beneath title) -->
        <p class="ll-description-intro" id="ll-val-description">No description provided.</p>

        <!-- Key Metadata Grid (2x2 with circular icon bubbles) -->
        <div class="ll-metadata-grid">
          <!-- Event Date -->
          <div class="ll-meta-item">
            <div class="ll-meta-icon-wrap" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
            </div>
            <div class="ll-meta-text-wrap">
              <span class="ll-meta-label">Event Date</span>
              <span class="ll-meta-val" id="ll-val-event-date">Unknown</span>
            </div>
          </div>

          <!-- Location -->
          <div class="ll-meta-item">
            <div class="ll-meta-icon-wrap" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
            </div>
            <div class="ll-meta-text-wrap">
              <span class="ll-meta-label">Location</span>
              <span class="ll-meta-val" id="ll-val-location">Not specified</span>
            </div>
          </div>

          <!-- Category -->
          <div class="ll-meta-item">
            <div class="ll-meta-icon-wrap" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                <line x1="7" y1="7" x2="7.01" y2="7" />
              </svg>
            </div>
            <div class="ll-meta-text-wrap">
              <span class="ll-meta-label">Category</span>
              <span class="ll-meta-val" id="ll-val-category">Uncategorized</span>
            </div>
          </div>

          <!-- Reported Time -->
          <div class="ll-meta-item">
            <div class="ll-meta-icon-wrap" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
            </div>
            <div class="ll-meta-text-wrap">
              <span class="ll-meta-label">Reported</span>
              <span class="ll-meta-val" id="ll-val-reported">Just now</span>
            </div>
          </div>
        </div>

        <!-- Additional Notes (Optional) -->
        <div class="ll-section-box" id="ll-additional-notes-box" hidden>
          <h3 class="ll-section-heading">Additional Information</h3>
          <p class="ll-description-text" id="ll-val-additional-note"></p>
        </div>

        <!-- Reporter Section -->
        <div class="ll-reporter-card">
          <div class="ll-reporter-left">
            <div class="ll-reporter-avatar-wrap">
              <img id="ll-reporter-avatar" class="ll-reporter-avatar" src="<?= e(url('profile/default_user.png')) ?>" alt="Reporter avatar">
            </div>
            <div class="ll-reporter-details">
              <span class="ll-reporter-label">Reported by</span>
              <div class="ll-reporter-name" id="ll-reporter-name">Campus User</div>
              <div class="ll-reporter-sub" id="ll-reporter-dept">Student</div>
            </div>
          </div>
          <button type="button" class="btn ll-contact-reporter-btn" id="ll-contact-reporter-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span id="ll-contact-btn-text">Contact Reporter</span>
          </button>
        </div>

        <!-- Claims Notice (if applicable for reporter or claimant) -->
        <div class="ll-claims-notice" id="ll-claims-notice" hidden></div>

        <!-- Inline Claim Form (shown when claiming) -->
        <div class="ll-claim-box" id="ll-claim-box" hidden>
          <h4 class="ll-claim-title">Submit Ownership Claim</h4>
          <p class="ll-claim-desc">Please provide details or distinguishing marks proving this item belongs to you.</p>
          <form id="ll-claim-form" class="ll-claim-form" novalidate>
            <textarea id="ll-claim-message" 
                      class="ll-claim-textarea" 
                      rows="3" 
                      placeholder="Describe unique features, stickers, scratches, contents, or serial numbers..." 
                      required></textarea>
            <div class="ll-claim-form-actions">
              <button type="button" class="btn btn-secondary btn-sm" id="ll-claim-cancel-btn">Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm" id="ll-claim-submit-btn">Submit Claim</button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>

  <!-- Sticky / Elevated Action Footer -->
  <footer class="ll-item-sheet-footer" id="ll-item-sheet-footer">
    <div class="ll-action-left" id="ll-action-left"></div>
    <div class="ll-action-right" id="ll-action-right">
      <button type="button" class="btn btn-secondary" data-ll-close-sheet>Close</button>
    </div>
  </footer>

  <!-- Contact Reporter Dialog Overlay -->
  <div class="ll-contact-dialog-backdrop" id="ll-contact-dialog-backdrop" hidden>
    <div class="ll-contact-dialog" id="ll-contact-dialog" role="dialog" aria-modal="true" aria-labelledby="ll-contact-dialog-title" tabindex="-1">
      <div class="ll-contact-dialog-header">
        <h3 class="ll-contact-dialog-title" id="ll-contact-dialog-title">Contact Reporter</h3>
        <button type="button" class="ll-contact-dialog-close" id="ll-contact-dialog-close" aria-label="Close dialog">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      
      <div class="ll-contact-dialog-body">
        <!-- Item & Recipient summary banner -->
        <div class="ll-contact-item-summary">
          <div class="ll-contact-summary-row">
            <div class="ll-contact-item-info">
              <h4 class="ll-contact-item-title" id="ll-contact-item-title">Item Title</h4>
              <span class="ll-contact-item-meta" id="ll-contact-item-meta">Found • Hall</span>
            </div>
            <div class="ll-contact-recipient-info">
              <span class="ll-contact-to-label">To:</span>
              <strong class="ll-contact-recipient-name" id="ll-contact-recipient-name">Reporter Name</strong>
            </div>
          </div>
          <div class="ll-contact-request-badge">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            <span>Creates a Contact Request. Private conversation opens upon reporter acceptance.</span>
          </div>
        </div>

        <!-- Contact Form -->
        <form id="ll-contact-form" class="ll-contact-form" novalidate>
          <div class="ll-contact-field">
            <label for="ll-contact-message" class="ll-contact-label">Initial Message <span class="required" aria-hidden="true">*</span></label>
            <textarea id="ll-contact-message" 
                      name="message" 
                      class="ll-contact-textarea" 
                      rows="4" 
                      maxlength="1000"
                      placeholder="Hi, I think this might be my item..." 
                      required></textarea>
            <div class="ll-contact-field-footer">
              <span class="ll-contact-hint">Be specific and descriptive to help verify your inquiry.</span>
              <div class="ll-contact-char-count"><span id="ll-contact-chars">0</span>/1000</div>
            </div>
          </div>

          <div class="ll-contact-feedback" id="ll-contact-feedback" hidden></div>

          <div class="ll-contact-dialog-actions">
            <button type="button" class="btn btn-secondary" id="ll-contact-cancel-btn">Cancel</button>
            <button type="submit" class="btn btn-primary" id="ll-contact-submit-btn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="22" y1="2" x2="11" y2="13"></line>
                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
              </svg>
              <span>Send Message</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

</section>

<!-- Centralized Item Details JavaScript -->
<script src="<?= url('components/item-details/item-details.js') ?>" defer></script>

