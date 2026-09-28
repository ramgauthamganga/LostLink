<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * LOSTLINK MESSAGES & CONVERSATIONS
 * ==============================================================================
 * Dedicated interface for private communications regarding reported items.
 * Allows viewing conversations, exchanging messages, reviewing contact requests,
 * and accepting/rejecting requests.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../backend/utils/helpers.php';
require_once __DIR__ . '/../backend/auth/auth_check.php';

$currentUserId = (int)$_SESSION['user_id'];
$currentUserName = (string)($_SESSION['name'] ?? 'User');

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

// Check initial query parameters
$initialConvCode = trim((string)($_GET['c'] ?? $_GET['conversation'] ?? ''));
$initialReqCode = trim((string)($_GET['r'] ?? $_GET['request'] ?? ''));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Messages - <?php echo APP_NAME; ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Theme logic before CSS -->
    <script src="../assets/js/theme.js"></script>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="messages.css?v=<?php echo time(); ?>">
</head>
<body class="app-body">

    <!-- Global Top Navigation -->
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Messages Interface -->
        <main class="app-main-content messages-main-wrapper" 
              id="messages-app"
              data-base-url="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>"
              data-user-id="<?php echo $currentUserId; ?>"
              data-initial-conv="<?php echo htmlspecialchars($initialConvCode, ENT_QUOTES, 'UTF-8'); ?>"
              data-initial-req="<?php echo htmlspecialchars($initialReqCode, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="messages-layout-card" id="messages-layout">

                <!-- ============================================================== -->
                <!-- LEFT COLUMN: CONVERSATIONS & REQUESTS LIST                     -->
                <!-- ============================================================== -->
                <aside class="messages-list-pane" id="messages-list-pane">
                    
                    <!-- Pane Header -->
                    <div class="messages-pane-header">
                        <div class="messages-title-row">
                            <h1 class="messages-title">Messages</h1>
                            <span class="messages-unread-badge" id="messages-total-unread" hidden>0 unread</span>
                        </div>

                        <!-- Segmented Navigation Tabs -->
                        <div class="messages-tabs-bar" role="tablist" aria-label="Messages Navigation">
                            <button type="button" 
                                    class="messages-tab-btn active" 
                                    id="tab-conversations" 
                                    role="tab" 
                                    aria-selected="true" 
                                    aria-controls="panel-conversations">
                                <span>Conversations</span>
                                <span class="tab-badge" id="tab-conv-badge" hidden>0</span>
                            </button>
                            <button type="button" 
                                    class="messages-tab-btn" 
                                    id="tab-requests" 
                                    role="tab" 
                                    aria-selected="false" 
                                    aria-controls="panel-requests">
                                <span>Requests</span>
                                <span class="tab-badge warning" id="tab-req-badge" hidden>0</span>
                            </button>
                        </div>

                        <!-- Search Filter -->
                        <div class="messages-search-box">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" 
                                   id="messages-search-input" 
                                   class="messages-search-input" 
                                   placeholder="Filter items or names..." 
                                   aria-label="Filter conversations">
                        </div>
                    </div>

                    <!-- Panel 1: Conversations List -->
                    <div class="messages-items-scroll" id="panel-conversations" role="tabpanel" aria-labelledby="tab-conversations">
                        <div class="messages-loading-state" id="conv-list-loading">
                            <div class="msg-spinner" aria-hidden="true"></div>
                            <span>Loading conversations...</span>
                        </div>
                        <div class="messages-empty-state" id="conv-list-empty" hidden>
                            <div class="empty-icon-wrap" aria-hidden="true">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </div>
                            <h3>No conversations yet</h3>
                            <p>When an item reporter accepts a contact request, your private conversation will appear here.</p>
                            <a href="<?php echo BASE_URL; ?>/items/browse.php" class="btn btn-outline btn-sm">Browse Items</a>
                        </div>
                        <ul class="conversations-ul" id="conversations-ul"></ul>
                    </div>

                    <!-- Panel 2: Contact Requests List -->
                    <div class="messages-items-scroll" id="panel-requests" role="tabpanel" aria-labelledby="tab-requests" hidden>
                        <div class="requests-subfilters">
                            <button type="button" class="subfilter-btn active" data-subfilter="incoming">Received (<span id="count-incoming">0</span>)</button>
                            <button type="button" class="subfilter-btn" data-subfilter="outgoing">Sent (<span id="count-outgoing">0</span>)</button>
                        </div>
                        <div class="messages-loading-state" id="req-list-loading" hidden>
                            <div class="msg-spinner" aria-hidden="true"></div>
                            <span>Loading requests...</span>
                        </div>
                        <div class="messages-empty-state" id="req-list-empty" hidden>
                            <div class="empty-icon-wrap" aria-hidden="true">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline>
                                    <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
                                </svg>
                            </div>
                            <h3>No contact requests</h3>
                            <p>Contact requests sent to you or by you will appear here.</p>
                        </div>
                        <ul class="requests-ul" id="requests-ul"></ul>
                    </div>

                </aside>

                <!-- ============================================================== -->
                <!-- RIGHT COLUMN: ACTIVE VIEW (CONVERSATION OR REQUEST)            -->
                <!-- ============================================================== -->
                <section class="messages-view-pane" id="messages-view-pane">

                    <!-- State A: Empty Selection State -->
                    <div class="messages-empty-selection" id="view-empty-selection">
                        <div class="selection-prompt-card">
                            <div class="prompt-icon-bubble" aria-hidden="true">
                                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    <line x1="9" y1="10" x2="15" y2="10"></line>
                                    <line x1="12" y1="7" x2="12" y2="13"></line>
                                </svg>
                            </div>
                            <h2>Select a Conversation</h2>
                            <p>Choose an item conversation or pending contact request from the list to start messaging.</p>
                        </div>
                    </div>

                    <!-- State B: Active Conversation View -->
                    <div class="conversation-active-view" id="view-conversation" hidden>
                        <!-- Conversation Header -->
                        <header class="conv-view-header">
                            <!-- Top Row (Mobile) / Left Side (Desktop) -->
                            <div class="conv-header-top-row">
                                <div class="conv-header-identity">
                                    <button type="button" class="conv-mobile-back-btn" id="conv-mobile-back-btn" aria-label="Back to conversations list">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="19" y1="12" x2="5" y2="12"></line>
                                            <polyline points="12 19 5 12 12 5"></polyline>
                                        </svg>
                                    </button>
                                    
                                    <img src="<?php echo BASE_URL; ?>/assets/images/default_no_image.png" alt="Item thumbnail" class="conv-item-thumb" id="conv-item-thumb" onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default_no_image.png'">
                                    
                                    <div class="conv-header-meta">
                                        <div class="conv-item-title-row">
                                            <h2 class="conv-item-title" id="conv-item-title">Item Title</h2>
                                            <span class="conv-badge-type" id="conv-badge-type">Found</span>
                                            <span class="conv-item-code" id="conv-item-code">ITM-000000000</span>
                                        </div>
                                        <div class="conv-participant-info conv-desktop-participant">
                                            <span>with</span>
                                            <strong id="conv-other-name">User Name</strong>
                                            <span class="conv-dept-text" id="conv-other-dept">Student</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Row (Mobile) / Right Side (Desktop) -->
                            <div class="conv-header-bottom-row">
                                <div class="conv-participant-info conv-mobile-participant">
                                    <span>with</span>
                                    <strong id="conv-other-name-m">User Name</strong>
                                    <span class="conv-dept-text" id="conv-other-dept-m">Student</span>
                                </div>

                                <div class="conv-header-actions">
                                    <button type="button" class="btn btn-outline btn-sm conv-desktop-view-btn" id="conv-view-item-btn">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <span>View Item</span>
                                    </button>
                                    <span class="conv-status-pill" id="conv-status-pill">Active</span>

                                    <!-- 3-dot dropdown menu -->
                                    <div class="conv-options-dropdown-wrap">
                                        <button type="button" class="conv-options-btn" id="conv-options-btn" aria-label="Conversation options" aria-haspopup="true" aria-expanded="false" title="More options">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <circle cx="12" cy="5" r="2"></circle>
                                                <circle cx="12" cy="12" r="2"></circle>
                                                <circle cx="12" cy="19" r="2"></circle>
                                            </svg>
                                        </button>
                                        <div class="conv-options-menu" id="conv-options-menu" hidden>
                                            <button type="button" class="conv-menu-item conv-mobile-only-item" id="conv-menu-view-item">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                <span>View Item Details</span>
                                            </button>
                                            <button type="button" class="conv-menu-item is-danger" id="conv-menu-block-btn">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                                </svg>
                                                <span id="conv-menu-block-text">Block User</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </header>

                        <!-- Messages Thread Scroll Area -->
                        <div class="conv-thread-scroll" id="conv-thread-scroll">
                            <div class="conv-thread-start-banner" id="conv-thread-banner">
                                <div class="banner-badge">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                    <span>Private & Secure LostLink Conversation</span>
                                </div>
                                <p>All messages regarding this item are strictly private between participants.</p>
                            </div>

                            <div class="conv-messages-container" id="conv-messages-container"></div>
                        </div>

                        <!-- Closed Conversation Notice -->
                        <div class="conv-closed-alert" id="conv-closed-alert" hidden>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span id="conv-closed-text">This conversation is closed. The item has been marked returned or resolved.</span>
                        </div>

                        <!-- Blocked Conversation Notice -->
                        <div class="conv-blocked-alert" id="conv-blocked-alert" hidden>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                            </svg>
                            <span id="conv-blocked-text">You have blocked this user. Messaging is disabled.</span>
                            <button type="button" class="btn btn-xs btn-outline" id="conv-unblock-btn" hidden style="margin-left: 8px;">Unblock</button>
                        </div>

                        <!-- Message Input Composer -->
                        <footer class="conv-composer-box" id="conv-composer-box">
                            <form id="conv-message-form" class="conv-message-form">
                                <textarea id="conv-message-input" 
                                          class="conv-message-textarea" 
                                          rows="1" 
                                          placeholder="Type a message..." 
                                          maxlength="2000" 
                                          required></textarea>
                                <button type="submit" class="btn btn-primary conv-send-btn" id="conv-send-btn" aria-label="Send message">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="22" y1="2" x2="11" y2="13"></line>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                    </svg>
                                    <span class="send-btn-label">Send</span>
                                </button>
                            </form>
                        </footer>
                    </div>

                    <!-- State C: Contact Request Review View -->
                    <div class="contact-request-active-view" id="view-request" hidden>
                        <header class="req-view-header">
                            <div class="req-header-left">
                                <button type="button" class="conv-mobile-back-btn" id="req-mobile-back-btn" aria-label="Back to list">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="19" y1="12" x2="5" y2="12"></line>
                                        <polyline points="12 19 5 12 12 5"></polyline>
                                    </svg>
                                    <span>Back</span>
                                </button>
                                <h2>Contact Request Details</h2>
                            </div>
                            <span class="badge" id="req-status-badge">Pending</span>
                        </header>

                        <div class="req-view-body">
                            <!-- Item Card Summary -->
                            <div class="req-item-preview-card">
                                <img src="<?php echo BASE_URL; ?>/assets/images/default_no_image.png" alt="Item image" class="req-item-img" id="req-item-img" onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default_no_image.png'">
                                <div class="req-item-details">
                                    <div class="req-item-top">
                                        <span class="req-badge-type" id="req-item-type">Found</span>
                                        <span class="req-item-code" id="req-item-code">ITM-000000000</span>
                                    </div>
                                    <h3 class="req-item-title" id="req-item-title">Item Title</h3>
                                    <p class="req-item-location" id="req-item-location">Location</p>
                                    <button type="button" class="btn btn-outline btn-xs" id="req-view-item-link">View Full Report</button>
                                </div>
                            </div>

                            <!-- Requester / Sender Profile -->
                            <div class="req-user-card">
                                <div class="req-user-avatar-wrap">
                                    <img src="" alt="Avatar" id="req-user-avatar" class="req-user-avatar">
                                </div>
                                <div class="req-user-meta">
                                    <span class="req-user-role-label" id="req-user-role-label">Requester</span>
                                    <h4 class="req-user-name" id="req-user-name">User Name</h4>
                                    <span class="req-user-dept" id="req-user-dept">Department</span>
                                </div>
                                <div class="req-timestamp-wrap">
                                    <span class="req-time-label">Received</span>
                                    <span class="req-time-val" id="req-created-time">Just now</span>
                                </div>
                            </div>

                            <!-- Inquiry Message Block -->
                            <div class="req-message-box">
                                <h4 class="req-message-label">Inquiry Message:</h4>
                                <blockquote class="req-message-content" id="req-message-content">
                                    "Message text"
                                </blockquote>
                            </div>

                            <!-- Actions Block (Dynamic based on status and role) -->
                            <div class="req-actions-panel" id="req-actions-panel">
                                <!-- Populated dynamically by messages.js -->
                            </div>
                        </div>
                    </div>

                </section>
            </div>
        </main>
    </div>

    <!-- Block User Confirmation Modal -->
    <div class="modal-overlay" id="block-confirm-modal" hidden>
        <div class="modal-content modal-block-dialog" role="dialog" aria-modal="true" aria-labelledby="block-modal-title">
            <div class="modal-dialog-header">
                <div class="block-icon-badge" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                    </svg>
                </div>
                <h3 class="modal-dialog-title" id="block-modal-title">Block this user?</h3>
                <button type="button" class="modal-dialog-close" id="block-modal-close-btn" aria-label="Close dialog">&times;</button>
            </div>
            <div class="modal-dialog-body">
                <p>Are you sure you want to block <strong id="block-modal-user-name">this user</strong>?</p>
                <p class="modal-dialog-subtext">You will no longer be able to send or receive messages with each other. Previous conversation history will be preserved.</p>
            </div>
            <div class="modal-dialog-footer">
                <button type="button" class="btn btn-outline" id="block-modal-cancel-btn">Cancel</button>
                <button type="button" class="btn btn-danger" id="block-modal-confirm-btn">Block User</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/components.js"></script>
    <script src="messages.js?v=<?php echo time(); ?>" defer></script>

    <!-- Shared Item Details Component for previewing item cards without navigation -->
    <?php require_once __DIR__ . '/../components/item-details/item-details.php'; ?>
</body>
</html>
