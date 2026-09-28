<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../backend/utils/helpers.php';
require_once __DIR__ . '/../backend/auth/auth_check.php';

$isEditMode = false;
$editItem = null;
$existingImages = [];
$editFeatures = '';
$editVerifyDetail = '';
$editContact = 'Through LostLink Platform';
$editTime = '';
$editVerifyMethod = 'description';

$initial_type = (isset($_GET['type']) && strtolower($_GET['type']) === 'found') ? 'found' : 'lost';

// Check for Edit Mode
if (isset($_GET['edit']) && trim((string)$_GET['edit']) !== '') {
    $editCode = trim((string)$_GET['edit']);

    $stmt = $pdo->prepare("SELECT * FROM items WHERE item_code = :code OR id = :id LIMIT 1");
    $stmt->execute([
        'code' => $editCode,
        'id'   => ctype_digit($editCode) ? (int)$editCode : 0
    ]);
    $foundItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$foundItem) {
        $_SESSION['error'] = 'The requested item could not be found.';
        header('Location: ' . BASE_URL . '/items/browse.php');
        exit;
    }

    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $isOwner = ((int)$foundItem['user_id'] === $currentUserId);

    if (!$isOwner && !$isAdmin) {
        $_SESSION['error'] = 'You are not authorized to edit this report.';
        header('Location: ' . BASE_URL . '/items/browse.php');
        exit;
    }

    $isEditMode = true;
    $editItem = $foundItem;
    $initial_type = strtolower((string)($editItem['report_type'] ?? 'lost'));

    // Fetch existing images
    $imgStmt = $pdo->prepare("
        SELECT id, image_code, image, image_order 
        FROM item_images 
        WHERE item_id = :item_id 
        ORDER BY image_order ASC, id ASC
    ");
    $imgStmt->execute(['item_id' => $editItem['id']]);
    $rawImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawImages as $img) {
        $existingImages[] = [
            'id'    => (int)$img['id'],
            'code'  => (string)$img['image_code'],
            'url'   => BASE_URL . '/' . ltrim((string)$img['image'], '/'),
            'path'  => (string)$img['image'],
            'order' => (int)$img['image_order']
        ];
    }

    // Parse additional_note
    if (!empty($editItem['additional_note'])) {
        $lines = explode("\n", (string)$editItem['additional_note']);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'Features: ')) {
                $editFeatures = trim(substr($line, 10));
            } elseif (str_starts_with($line, 'Verification Detail: ')) {
                $editVerifyDetail = trim(substr($line, 21));
            } elseif (str_starts_with($line, 'Preferred Contact: ')) {
                $editContact = trim(substr($line, 19));
            } elseif (str_starts_with($line, 'Time: ')) {
                $editTime = trim(substr($line, 6));
            }
        }
    }

    if (($editItem['verification_method'] ?? '') === 'questions') {
        $editVerifyMethod = 'secret_question';
    }
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title><?php echo $isEditMode ? 'Edit Item: ' . htmlspecialchars($editItem['title']) : ($initial_type === 'found' ? 'Report Found Item' : 'Report Lost Item'); ?> | <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="report.css?v=<?php echo time(); ?>">
    <script>
        window.LOSTLINK_IS_EDIT_MODE = <?php echo $isEditMode ? 'true' : 'false'; ?>;
        window.LOSTLINK_EXISTING_IMAGES = <?php echo json_encode($existingImages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
</head>
<body class="app-body">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <div class="app-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main-content">
            <div class="report-content-wrapper animate-fade-in">

                <!-- Page Header -->
                <div class="report-header">
                    <h1 id="report-heading"><?php echo $isEditMode ? 'Edit Item Report' : ($initial_type === 'found' ? 'Report a Found Item' : 'Report a Lost Item'); ?></h1>
                    <p class="text-secondary" id="report-subtitle"><?php echo $isEditMode ? 'Update details for: ' . htmlspecialchars($editItem['title'] ?? '') : ($initial_type === 'found' ? 'Help reunite this item with its owner' : 'Help others by reporting your lost item'); ?></p>
                </div>

                <!-- Report Type Selector -->
                <div class="report-type-selector">
                    <button type="button" class="report-type-btn <?php echo $initial_type === 'lost' ? 'active' : ''; ?>" data-type="lost" id="type-lost" <?php echo $isEditMode ? 'disabled title="Item type cannot be changed once reported"' : ''; ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        Lost Item
                    </button>
                    <button type="button" class="report-type-btn <?php echo $initial_type === 'found' ? 'active' : ''; ?>" data-type="found" id="type-found" <?php echo $isEditMode ? 'disabled title="Item type cannot be changed once reported"' : ''; ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        Found Item
                    </button>
                </div>

                <!-- Stepper -->
                <div class="report-stepper-container">
                    <div class="report-stepper">
                        <div class="step active" data-step="1">
                            <div class="step-circle">1</div>
                            <div class="step-label">Item Details</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step" data-step="2">
                            <div class="step-circle">2</div>
                            <div class="step-label">Location &amp; Time</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step" data-step="3">
                            <div class="step-circle">3</div>
                            <div class="step-label">Additional Info</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step" data-step="4">
                            <div class="step-circle">4</div>
                            <div class="step-label">Review &amp; Submit</div>
                        </div>
                    </div>
                </div>

                <!-- Report Layout -->
                <div class="report-layout">
                    <div class="report-main-area">
                        <div class="report-form-card">
                            <?php if ($isEditMode): ?>
                                <input type="hidden" id="edit_item_code" name="edit_item_code" value="<?php echo htmlspecialchars($editItem['item_code']); ?>">
                                <input type="hidden" id="edit_item_id" name="edit_item_id" value="<?php echo (int)$editItem['id']; ?>">
                            <?php endif; ?>
                            <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                            <!-- Step 1: Item Details -->
                            <div class="form-step active" id="step-1">
                                <div class="step-section-header">
                                    <h2>Item Details</h2>
                                    <p class="text-secondary">Provide basic information about your item</p>
                                </div>
                                <div class="form-row-2">
                                    <div class="form-group">
                                        <label for="category">Category <span class="required">*</span></label>
                                        <div class="input-with-icon select-wrapper">
                                            <div class="icon-left">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                            </div>
                                            <select id="category" name="category" required>
                                                <option value="" disabled <?php echo !$isEditMode ? 'selected' : ''; ?>>Select category</option>
                                                <option value="electronics" <?php echo ($isEditMode && ($editItem['category'] ?? '') === 'electronics') ? 'selected' : ''; ?>>Electronics</option>
                                                <option value="bags_wallets" <?php echo ($isEditMode && ($editItem['category'] ?? '') === 'bags_wallets') ? 'selected' : ''; ?>>Bags &amp; Wallets</option>
                                                <option value="keys" <?php echo ($isEditMode && ($editItem['category'] ?? '') === 'keys') ? 'selected' : ''; ?>>Keys</option>
                                                <option value="documents" <?php echo ($isEditMode && ($editItem['category'] ?? '') === 'documents') ? 'selected' : ''; ?>>Documents</option>
                                                <option value="clothing" <?php echo ($isEditMode && ($editItem['category'] ?? '') === 'clothing') ? 'selected' : ''; ?>>Clothing</option>
                                                <option value="accessories" <?php echo ($isEditMode && ($editItem['category'] ?? '') === 'accessories') ? 'selected' : ''; ?>>Accessories</option>
                                                <option value="other" <?php echo ($isEditMode && ($editItem['category'] ?? '') === 'other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <span class="field-error" id="error-category"></span>
                                    </div>
                                    <div class="form-group">
                                        <label for="item_name">Item Name <span class="required">*</span></label>
                                        <input type="text" id="item_name" name="item_name" placeholder="e.g., Black Backpack" value="<?php echo htmlspecialchars($editItem['title'] ?? ''); ?>" required>
                                        <span class="helper-text">A short, descriptive name for your item</span>
                                        <span class="field-error" id="error-item_name"></span>
                                    </div>
                                </div>
                                <div class="form-group form-group-mt">
                                    <label for="description">Description <span class="required">*</span></label>
                                    <textarea id="description" name="description" rows="4" placeholder="Describe your item in detail (color, brand, features, etc.)" required maxlength="300"><?php echo htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
                                    <div class="textarea-footer">
                                        <span class="helper-text">The more details you provide, the easier it is to find your item.</span>
                                        <span class="char-count" id="desc-char-count"><?php echo mb_strlen($editItem['description'] ?? ''); ?> / 300</span>
                                    </div>
                                    <span class="field-error" id="error-description"></span>
                                </div>
                                <div class="form-group form-group-mt">
                                    <label>Item Photos</label>
                                    <span class="helper-text photo-helper">Add photos of your item (Max 5 photos)</span>
                                    <div class="upload-area" id="upload-trigger" role="button" tabindex="0" aria-label="Upload photos">
                                        <input type="file" id="file-input" multiple accept="image/png, image/jpeg" style="display: none;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--primary-blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        <span class="upload-text">Click to upload or drag and drop</span>
                                        <span class="upload-hint">PNG, JPG up to 5MB</span>
                                    </div>
                                    <div class="upload-notice" id="upload-notice"></div>
                                    <div class="photo-previews" id="photo-previews"></div>
                                </div>
                            </div>

                            <!-- Step 2: Location & Time -->
                            <div class="form-step" id="step-2">
                                <div class="step-section-header">
                                    <h2>Location &amp; Time</h2>
                                    <p class="text-secondary" id="step2-subtitle"><?php echo $initial_type === 'lost' ? 'Where and when did you lose this item?' : 'Where and when did you find this item?'; ?></p>
                                </div>
                                <div class="form-group">
                                    <label for="location">Location <span class="required">*</span></label>
                                    <input type="text" id="location" name="location" placeholder="e.g., Main Library, 2nd Floor" value="<?php echo htmlspecialchars($editItem['event_location'] ?? ''); ?>">
                                    <span class="field-error" id="error-location"></span>
                                </div>
                                <div class="form-row-2 form-group-mt">
                                    <div class="form-group">
                                        <label for="report_date">Date <span class="required">*</span></label>
                                        <input type="date" id="report_date" name="report_date" value="<?php echo htmlspecialchars($editItem['event_date'] ?? ''); ?>">
                                        <span class="field-error" id="error-report_date"></span>
                                    </div>
                                    <div class="form-group">
                                        <label for="report_time">Time</label>
                                        <input type="time" id="report_time" name="report_time" value="<?php echo htmlspecialchars($editTime); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Additional Info -->
                            <div class="form-step" id="step-3">
                                <div class="step-section-header">
                                    <h2>Additional Info</h2>
                                    <p class="text-secondary">Any extra details that might help identify or verify the item?</p>
                                </div>
                                <div class="form-group">
                                    <label for="distinguishing_features">Additional Info / Distinguishing Feature</label>
                                    <textarea id="distinguishing_features" name="distinguishing_features" rows="3" placeholder="Any scratches, stickers, engravings, or unique marks?"><?php echo htmlspecialchars($editFeatures); ?></textarea>
                                    <span class="helper-text">These details help verify ownership</span>
                                </div>
                                <div class="form-row-2 form-group-mt">
                                    <div class="form-group">
                                        <label for="verification_method">Verification Method</label>
                                        <div class="input-with-icon select-wrapper">
                                            <div class="icon-left">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                                            </div>
                                            <select id="verification_method" name="verification_method">
                                                <option value="" disabled <?php echo empty($editVerifyMethod) ? 'selected' : ''; ?>>Select method</option>
                                                <option value="serial_number" <?php echo ($editVerifyMethod === 'serial_number') ? 'selected' : ''; ?>>Serial Number</option>
                                                <option value="receipt" <?php echo ($editVerifyMethod === 'receipt') ? 'selected' : ''; ?>>Receipt / Proof of Purchase</option>
                                                <option value="unique_feature" <?php echo ($editVerifyMethod === 'unique_feature') ? 'selected' : ''; ?>>Unique Marking / Feature</option>
                                                <option value="passcode" <?php echo ($editVerifyMethod === 'passcode') ? 'selected' : ''; ?>>Password / Passcode</option>
                                                <option value="secret_question" <?php echo ($editVerifyMethod === 'secret_question') ? 'selected' : ''; ?>>Secret Question</option>
                                                <option value="other" <?php echo ($editVerifyMethod === 'other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="verification_detail">Verification Detail / Question</label>
                                        <input type="text" id="verification_detail" name="verification_detail" placeholder="e.g., What is the lock code?" value="<?php echo htmlspecialchars($editVerifyDetail); ?>">
                                    </div>
                                </div>
                                <div class="form-group form-group-mt">
                                    <label for="contact_preference">Preferred Contact Method</label>
                                    <div class="input-with-icon select-wrapper">
                                        <div class="icon-left">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                        </div>
                                        <select id="contact_preference" name="contact_preference">
                                            <option value="Through LostLink Platform" <?php echo ($editContact === 'Through LostLink Platform' || empty($editContact)) ? 'selected' : ''; ?>>Through LostLink Platform</option>
                                            <option value="Email" <?php echo ($editContact === 'Email') ? 'selected' : ''; ?>>Email</option>
                                            <option value="Phone" <?php echo ($editContact === 'Phone') ? 'selected' : ''; ?>>Phone</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 4: Review & Submit -->
                            <div class="form-step" id="step-4">
                                <div class="step-section-header">
                                    <h2>Review &amp; Submit</h2>
                                    <p class="text-secondary">Double-check your information before submitting</p>
                                </div>
                                <div class="review-summary" id="review-summary"></div>
                            </div>

                            <!-- Form Footer (Navigation) -->
                            <div class="form-actions-footer">
                                <button type="button" class="btn btn-outline" id="btn-prev">&larr; Back</button>
                                <button type="button" class="btn btn-outline" id="btn-cancel">Cancel</button>
                                <div class="form-actions-spacer"></div>
                                <button type="button" class="btn btn-primary" id="btn-next">Next &rarr;</button>
                                <button type="button" class="btn btn-primary" id="btn-submit"><?php echo $isEditMode ? 'Update Report' : 'Submit Report'; ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Sidebar: Tips Panel -->
                    <aside class="report-tips-panel">
                        <div class="report-card tips-card">
                            <div class="tips-header">
                                <div class="tips-icon-wrap">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.9 1.2 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
                                </div>
                                <h3>Tips for Better Results</h3>
                            </div>

                            <ul class="tips-list">
                                <li>
                                    <div class="tip-icon-circle">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary-blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4.2a1 1 0 0 0-1.4 0l-2.1 2.1a1 1 0 0 0 0 1.4Z"/><path d="m21 21-9.6-9.6"/><path d="m11 12.5-3 3-3-3 3-3"/></svg>
                                    </div>
                                    <div class="tip-content">
                                        <h4>Be Specific</h4>
                                        <p>Add details like brand, color, and unique features.</p>
                                    </div>
                                </li>
                                <li>
                                    <div class="tip-icon-circle">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary-blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    </div>
                                    <div class="tip-content">
                                        <h4>Add Clear Photos</h4>
                                        <p>Use clear photos from different angles if possible.</p>
                                    </div>
                                </li>
                                <li>
                                    <div class="tip-icon-circle">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary-blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                    </div>
                                    <div class="tip-content">
                                        <h4>Exact Location</h4>
                                        <p>Mention the exact place where you lost your item.</p>
                                    </div>
                                </li>
                                <li>
                                    <div class="tip-icon-circle">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary-blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                                    </div>
                                    <div class="tip-content">
                                        <h4>Check Your Info</h4>
                                        <p>Double-check all details before submitting your report.</p>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div class="report-card privacy-card">
                            <div class="privacy-header">
                                <div class="privacy-icon-wrap">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <h3>Your Privacy Matters</h3>
                            </div>
                            <p>Your personal information is safe with us and will only be used to help recover your item.</p>
                            <a href="#" class="privacy-link">Read our Privacy Policy &rarr;</a>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/components.js"></script>
    <script src="report.js?v=<?php echo time(); ?>"></script>
    <script>
        // Fix navigation links without modifying shared components
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Cancel Button
            const cancelBtn = document.getElementById('btn-cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = '../dashboard/dashboard.php';
                });
            }

            // 2. Sidebar Link Behavior
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
</body>
</html>
