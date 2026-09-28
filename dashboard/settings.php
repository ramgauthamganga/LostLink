<?php
/*
|--------------------------------------------------------------------------
| LostLink Settings Page
|--------------------------------------------------------------------------
| Allows authenticated campus users to manage their account details,
| security/password credentials, notification preferences, appearance,
| privacy information, and platform overview in one continuous view.
|
*/

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../backend/utils/helpers.php';
require_once __DIR__ . '/../backend/auth/auth_check.php';

// Set current page for sidebar active link highlight
$current_page = 'settings.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Fetch current user from database
$stmt = $pdo->prepare("
    SELECT id, user_code, name, student_id, email, password, department, phone, profile_image, role, is_banned, last_login, created_at, updated_at 
    FROM users 
    WHERE id = :id 
    LIMIT 1
");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Synchronize session values immediately with freshly loaded database record
$_SESSION['profile_image'] = $user['profile_image'] ?? null;
if (!empty($user['name'])) {
    $_SESSION['name'] = $user['name'];
}

// Minimum CSRF token protection for Settings forms
if (empty($_SESSION['settings_csrf_token'])) {
    $_SESSION['settings_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['settings_csrf_token'];

$success_message = '';
$error_message = '';
$action = '';

/**
 * Safely validate and save an uploaded profile avatar image.
 * 
 * @param array $file $_FILES['...'] array
 * @param int $userId Current user ID
 * @param PDO $pdo Database connection
 * @param string|null $oldImage Existing profile image path
 * @return array ['success' => bool, 'message' => string, 'path' => string|null]
 */
if (!function_exists('handleAvatarUpload')) {
    function handleAvatarUpload(array $file, int $userId, PDO $pdo, ?string $oldImage): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload parameter.', 'path' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form limit.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
        ];
        return ['success' => false, 'message' => $uploadErrors[$file['error']] ?? 'File upload error.', 'path' => null];
    }

    // Check file size (max 5 MB)
    $maxSize = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Profile image must be smaller than 5 MB.', 'path' => null];
    }

    // Validate MIME type securely using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mimeType, $allowedMimes, true)) {
        return ['success' => false, 'message' => 'Invalid image format. Allowed formats: JPG, PNG, and WebP.', 'path' => null];
    }

    // Validate file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['success' => false, 'message' => 'Invalid file extension.', 'path' => null];
    }

    // Ensure upload directory exists
    $uploadDir = defined('PROFILE_UPLOAD_PATH') ? PROFILE_UPLOAD_PATH : __DIR__ . '/../assets/uploads/profiles/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['success' => false, 'message' => 'Failed to create profile image storage folder.', 'path' => null];
        }
    }

    // Generate unique, non-colliding, safe filename
    $uniqueFilename = 'user_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $uniqueFilename;

    $saved = move_uploaded_file($file['tmp_name'], $targetPath);
    if (!$saved && PHP_SAPI === 'cli') {
        $saved = copy($file['tmp_name'], $targetPath);
    }

    if (!$saved) {
        return ['success' => false, 'message' => 'Failed to save the uploaded image.', 'path' => null];
    }

    $relativeDbPath = 'assets/uploads/profiles/' . $uniqueFilename;

    // Update database
    $stmt = $pdo->prepare("UPDATE users SET profile_image = :image, updated_at = NOW() WHERE id = :id");
    $stmt->execute(['image' => $relativeDbPath, 'id' => $userId]);

    // Update session
    $_SESSION['profile_image'] = $relativeDbPath;

    // Delete previous custom avatar if exists and safe
    if (!empty($oldImage) && strpos($oldImage, 'assets/uploads/profiles/') === 0) {
        $oldFilePath = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldImage);
        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
            @unlink($oldFilePath);
        }
    }

    return ['success' => true, 'message' => 'Profile image updated successfully.', 'path' => $relativeDbPath];
    }
}

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submitted_token = $_POST['csrf_token'] ?? '';
    $isAjax = (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );

    if (!hash_equals($_SESSION['settings_csrf_token'] ?? '', $submitted_token)) {
        $error_message = 'Security validation failed. Please refresh the page and try again.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }
    } elseif ($action === 'update_avatar') {
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $avatarResult = handleAvatarUpload($_FILES['profile_image'], $user_id, $pdo, $user['profile_image'] ?? null);
            if ($avatarResult['success']) {
                $user['profile_image'] = $avatarResult['path'];
                $success_message = $avatarResult['message'];
            } else {
                $error_message = $avatarResult['message'];
            }
        } else {
            $error_message = 'Please choose an image file to upload.';
        }
    } elseif ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            $error_message = 'Please provide a valid full name between 2 and 150 characters.';
        } elseif (!empty($phone) && (strlen($phone) < 7 || strlen($phone) > 20)) {
            $error_message = 'Please provide a valid phone number between 7 and 20 characters.';
        } else {
            try {
                // Optional avatar upload during profile update
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $avatarResult = handleAvatarUpload($_FILES['profile_image'], $user_id, $pdo, $user['profile_image'] ?? null);
                    if ($avatarResult['success']) {
                        $user['profile_image'] = $avatarResult['path'];
                    } else {
                        $error_message = $avatarResult['message'];
                    }
                }

                if (empty($error_message)) {
                    $updateStmt = $pdo->prepare("
                        UPDATE users 
                        SET name = :name, phone = :phone, updated_at = NOW() 
                        WHERE id = :id
                    ");
                    $updateStmt->execute([
                        'name'  => $name,
                        'phone' => $phone !== '' ? $phone : null,
                        'id'    => $user_id
                    ]);

                    // Synchronize session name so global navbar reflects it immediately
                    $_SESSION['name'] = $name;

                    // Refresh in-memory user record
                    $user['name'] = $name;
                    $user['phone'] = $phone !== '' ? $phone : null;

                    $success_message = 'Your profile details have been saved successfully.';
                }
            } catch (PDOException $e) {
                $error_message = 'An error occurred while saving your profile. Please try again.';
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            if (!empty($error_message)) {
                echo json_encode(['success' => false, 'message' => $error_message]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => $success_message,
                    'user'    => [
                        'name'          => $user['name'],
                        'department'    => $user['department'] ?? '',
                        'phone'         => $user['phone'] ?? '',
                        'profile_image' => $user['profile_image'] ?? ''
                    ]
                ]);
            }
            exit;
        }
    } elseif ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password)) {
            $error_message = 'Please enter your current password.';
        } elseif (!password_verify($current_password, (string)$user['password'])) {
            $error_message = 'The current password you entered is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $error_message = 'New password must be at least 8 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'The new password and confirmation password do not match.';
        } elseif ($current_password === $new_password) {
            $error_message = 'Your new password cannot be the same as your current password.';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $pwStmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
                $pwStmt->execute([
                    'password' => $hashed_password,
                    'id'       => $user_id
                ]);

                $user['password'] = $hashed_password;
                $success_message = 'Your password has been changed successfully.';
            } catch (PDOException $e) {
                $error_message = 'An error occurred while updating your password. Please try again.';
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            if (!empty($error_message)) {
                echo json_encode(['success' => false, 'message' => $error_message]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => $success_message
                ]);
            }
            exit;
        }
    }
}

// Active edit mode flags for initial server-rendered state
$isProfileEditActive = ($action === 'update_profile' && !empty($error_message));
$isPasswordEditActive = ($action === 'update_password' && !empty($error_message));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo htmlspecialchars(APP_NAME); ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Theme Logic (Loaded first to prevent FOUC) -->
    <script src="../assets/js/theme.js"></script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="settings.css?v=<?php echo time(); ?>">
</head>
<body class="app-body settings-page">

    <!-- Global Top Navigation -->
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content Area (Scroll Container) -->
        <main class="app-main-content">
            <div class="settings-content-wrapper animate-fade-in">
                
                <!-- 
                |--------------------------------------------------------------------------
                | SETTINGS HEADER & COMFORTABLE GAP
                |--------------------------------------------------------------------------
                -->
                <div class="settings-header">
                    <h1 class="settings-title">Settings</h1>
                    <p class="settings-subtitle">Manage your account and preferences.</p>
                </div>

                <!-- 
                |--------------------------------------------------------------------------
                | STICKY SCROLL-SPY NAVIGATION (6 PILL ANCHORS)
                |--------------------------------------------------------------------------
                -->
                <div class="settings-tabs-wrapper" id="settings-sticky-nav">
                    <nav class="settings-tabs" role="navigation" aria-label="Settings sections navigation">
                        <a href="#account" class="settings-tab-btn active" data-tab="account">
                            <svg class="settings-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span>Account</span>
                        </a>
                        <a href="#security" class="settings-tab-btn" data-tab="security">
                            <svg class="settings-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <span>Security</span>
                        </a>
                        <a href="#notifications" class="settings-tab-btn" data-tab="notifications">
                            <svg class="settings-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span>Notifications</span>
                        </a>
                        <a href="#appearance" class="settings-tab-btn" data-tab="appearance">
                            <svg class="settings-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
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
                            <span>Appearance</span>
                        </a>
                        <a href="#privacy" class="settings-tab-btn" data-tab="privacy">
                            <svg class="settings-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                            <span>Privacy</span>
                        </a>
                        <a href="#about" class="settings-tab-btn" data-tab="about">
                            <svg class="settings-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            <span>About</span>
                        </a>
                    </nav>
                </div>

                <!-- 
                |--------------------------------------------------------------------------
                | STATUS ALERTS FEEDBACK
                |--------------------------------------------------------------------------
                -->
                <?php if (!empty($success_message)): ?>
                    <div class="settings-alert settings-alert-success" role="alert">
                        <div class="settings-alert-content">
                            <svg class="settings-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                        <button type="button" class="settings-alert-close" aria-label="Dismiss alert">&times;</button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="settings-alert settings-alert-error" role="alert">
                        <div class="settings-alert-content">
                            <svg class="settings-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                        <button type="button" class="settings-alert-close" aria-label="Dismiss alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- 
                |--------------------------------------------------------------------------
                | SECTION 1: ACCOUNT (READ-ONLY BY DEFAULT → EDIT PROFILE)
                |--------------------------------------------------------------------------
                -->
                <section class="settings-section" id="account">
                    <div class="settings-card">
                        
                        <!-- 1A: READ-ONLY VIEW (DEFAULT) -->
                        <div class="account-view-mode" id="account-view-container" <?php if ($isProfileEditActive) echo 'style="display: none;"'; ?>>
                            <div class="settings-card-header">
                                <div class="settings-card-header-left">
                                    <h2>Account Information</h2>
                                    <p>Manage your campus profile details and contact information.</p>
                                </div>
                                <div class="settings-card-header-action">
                                    <button type="button" class="btn btn-outline btn-sm" id="btn-edit-profile">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        Edit Profile
                                    </button>
                                </div>
                            </div>

                            <!-- Profile Header Badge with Editable Avatar Affordance -->
                            <div class="settings-profile-badge">
                                <div class="settings-avatar-container" id="avatar-container" title="Click to change profile photo">
                                    <?php
                                    $settingsAvatarUrl = getProfileImageUrl($user['profile_image'] ?? null);
                                    if (!empty($user['profile_image']) && $settingsAvatarUrl !== BASE_URL . '/profile/default_user.png') {
                                        $avatarDiskFile = __DIR__ . '/../' . ltrim($user['profile_image'], '/');
                                        $avatarVer = file_exists($avatarDiskFile) ? filemtime($avatarDiskFile) : time();
                                        $settingsAvatarUrl .= (strpos($settingsAvatarUrl, '?') === false ? '?' : '&') . 'v=' . $avatarVer;
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($settingsAvatarUrl); ?>" 
                                         alt="Profile Avatar" 
                                         class="settings-avatar"
                                         id="profile-avatar-preview"
                                         data-default-src="<?php echo htmlspecialchars($settingsAvatarUrl); ?>"
                                         onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>/profile/default_user.png'">
                                    
                                    <button type="button" class="settings-avatar-edit-badge" id="btn-avatar-edit" aria-label="Change profile photo">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                            <circle cx="12" cy="13" r="4"></circle>
                                        </svg>
                                    </button>
                                </div>

                                <div class="settings-user-meta">
                                    <div class="settings-user-name-row">
                                        <h3 class="settings-user-name" id="view-user-name"><?php echo htmlspecialchars($user['name']); ?></h3>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="settings-pill-badge settings-pill-role-admin">Administrator</span>
                                        <?php else: ?>
                                            <span class="settings-pill-badge settings-pill-role-student">Student</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="settings-user-code-text"><?php echo htmlspecialchars($user['user_code']); ?> &bull; <?php echo htmlspecialchars($user['email']); ?></span>

                                    <!-- Inline Avatar Save/Discard Action Strip -->
                                    <form action="<?php echo BASE_URL; ?>/dashboard/settings.php#account" method="POST" enctype="multipart/form-data" id="form-avatar-direct">
                                        <input type="hidden" name="action" value="update_avatar">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="file" id="direct-avatar-input" name="profile_image" accept="image/jpeg,image/png,image/webp" style="display: none;">
                                        
                                        <div class="settings-avatar-actions" id="avatar-actions-bar">
                                            <button type="submit" class="btn btn-primary btn-sm">Save Photo</button>
                                            <button type="button" class="btn btn-secondary btn-sm" id="btn-discard-avatar">Discard</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Structured Read-Only Details (NOT disabled input boxes) -->
                            <div class="settings-profile-details-grid">
                                <div class="settings-profile-detail-item">
                                    <span class="detail-label">Full Name</span>
                                    <span class="detail-value" id="view-field-name"><?php echo htmlspecialchars($user['name']); ?></span>
                                </div>
                                <div class="settings-profile-detail-item">
                                    <span class="detail-label">Department</span>
                                    <span class="detail-value" id="view-field-department"><?php echo htmlspecialchars(!empty($user['department']) ? $user['department'] : 'Not specified'); ?></span>
                                </div>
                                <div class="settings-profile-detail-item">
                                    <span class="detail-label">Mobile Phone</span>
                                    <span class="detail-value" id="view-field-phone"><?php echo htmlspecialchars(!empty($user['phone']) ? $user['phone'] : 'Not provided'); ?></span>
                                </div>
                                <div class="settings-profile-detail-item">
                                    <span class="detail-label">Student ID</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($user['student_id'] ?? 'Not Assigned'); ?></span>
                                </div>
                                <div class="settings-profile-detail-item full-width">
                                    <span class="detail-label">Campus Email</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- 1B: EDIT MODE (HIDDEN BY DEFAULT) -->
                        <div class="account-edit-mode" id="account-edit-container" <?php if (!$isProfileEditActive) echo 'style="display: none;"'; ?>>
                            <div class="settings-card-header">
                                <div class="settings-card-header-left">
                                    <h2>Edit Profile</h2>
                                    <p>Update your personal details. Changes will be reflected across LostLink.</p>
                                </div>
                            </div>

                            <form action="<?php echo BASE_URL; ?>/dashboard/settings.php#account" method="POST" enctype="multipart/form-data" class="settings-form" id="form-edit-profile">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                                <div class="settings-form-grid">
                                    <!-- Full Name -->
                                    <div class="settings-form-group">
                                        <label for="edit_profile_name" class="settings-form-label">Full Name <span class="text-danger">*</span></label>
                                        <div class="settings-input-wrapper">
                                            <input type="text" id="edit_profile_name" name="name" class="settings-input" required 
                                                   value="<?php echo htmlspecialchars($user['name']); ?>" 
                                                   placeholder="Enter your full name" maxlength="150">
                                        </div>
                                    </div>

                                    <!-- Department (Permanent Readonly) -->
                                    <div class="settings-form-group">
                                        <label for="edit_profile_department" class="settings-form-label">Department</label>
                                        <div class="settings-input-wrapper">
                                            <input type="text" id="edit_profile_department" class="settings-input" readonly 
                                                   value="<?php echo htmlspecialchars(!empty($user['department']) ? $user['department'] : 'Not specified'); ?>">
                                        </div>
                                        <span class="settings-input-help">Academic faculty or department (cannot be modified).</span>
                                    </div>

                                    <!-- Mobile Phone -->
                                    <div class="settings-form-group">
                                        <label for="edit_profile_phone" class="settings-form-label">Mobile Phone</label>
                                        <div class="settings-input-wrapper">
                                            <input type="tel" id="edit_profile_phone" name="phone" class="settings-input" 
                                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                                   placeholder="+91 XXXXX XXXXX" maxlength="20">
                                        </div>
                                        <span class="settings-input-help">Used for claim verification and handover contact.</span>
                                    </div>

                                    <!-- Student ID (Permanent Readonly) -->
                                    <div class="settings-form-group">
                                        <label for="edit_profile_student_id" class="settings-form-label">Student ID</label>
                                        <div class="settings-input-wrapper">
                                            <input type="text" id="edit_profile_student_id" class="settings-input" readonly 
                                                   value="<?php echo htmlspecialchars($user['student_id'] ?? 'Not Assigned'); ?>">
                                        </div>
                                        <span class="settings-input-help">Permanent campus identifier.</span>
                                    </div>

                                    <!-- Campus Email (Permanent Readonly) -->
                                    <div class="settings-form-group full-width">
                                        <label for="edit_profile_email" class="settings-form-label">Campus Email</label>
                                        <div class="settings-input-wrapper">
                                            <input type="email" id="edit_profile_email" class="settings-input" readonly 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                                        </div>
                                        <span class="settings-input-help">Official campus email address cannot be modified directly.</span>
                                    </div>
                                </div>

                                <div class="settings-form-actions">
                                    <button type="button" class="btn btn-secondary" id="btn-cancel-edit-profile">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                            <polyline points="7 3 7 8 15 8"></polyline>
                                        </svg>
                                        Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </section>

                <!-- 
                |--------------------------------------------------------------------------
                | SECTION 2: SECURITY (READ-ONLY OVERVIEW → CHANGE PASSWORD)
                |--------------------------------------------------------------------------
                -->
                <section class="settings-section" id="security">
                    <div class="settings-card">
                        
                        <!-- 2A: SECURITY OVERVIEW (READ-ONLY BY DEFAULT) -->
                        <div class="security-overview-mode" id="security-overview-container">
                            <div class="settings-card-header">
                                <div class="settings-card-header-left">
                                    <h2>Security Overview</h2>
                                    <p>Manage your password and review account security parameters.</p>
                                </div>
                                <div class="settings-card-header-action">
                                    <button type="button" class="btn btn-outline btn-sm" id="btn-open-change-password">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                        Change Password
                                    </button>
                                </div>
                            </div>

                            <div class="settings-security-grid">
                                <div class="settings-security-item">
                                    <div class="settings-security-label">Account Status</div>
                                    <div class="settings-security-value">
                                        <?php echo $user['is_banned'] ? '<span class="text-danger">Suspended</span>' : '<span style="color: var(--success);">&#x25CF; Active / Verified</span>'; ?>
                                    </div>
                                </div>
                                <div class="settings-security-item">
                                    <div class="settings-security-label">User Identifier</div>
                                    <div class="settings-security-value"><?php echo htmlspecialchars($user['user_code']); ?></div>
                                </div>
                                <div class="settings-security-item">
                                    <div class="settings-security-label">Last Login</div>
                                    <div class="settings-security-value">
                                        <?php echo !empty($user['last_login']) ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Current Session'; ?>
                                    </div>
                                </div>
                                <div class="settings-security-item">
                                    <div class="settings-security-label">Password</div>
                                    <div class="settings-security-value" style="letter-spacing: 2px;">
                                        &bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2B: CHANGE PASSWORD FORM (REVEALED UPON CLICK) -->
                        <div class="security-edit-mode" id="security-edit-container" <?php if (!$isPasswordEditActive) echo 'style="display: none;"'; ?>>
                            <div class="settings-card-header" style="padding-top: 0;">
                                <div class="settings-card-header-left">
                                    <h2>Change Password</h2>
                                    <p>Enter your current password and choose a strong new password.</p>
                                </div>
                            </div>

                            <form action="<?php echo BASE_URL; ?>/dashboard/settings.php#security" method="POST" class="settings-form" id="form-update-password">
                                <input type="hidden" name="action" value="update_password">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                                <div class="settings-form-grid">
                                    <!-- Current Password -->
                                    <div class="settings-form-group full-width">
                                        <label for="current_password" class="settings-form-label">Current Password <span class="text-danger">*</span></label>
                                        <div class="settings-input-wrapper settings-input-password-wrapper">
                                            <input type="password" id="current_password" name="current_password" class="settings-input" required placeholder="Enter current password">
                                            <button type="button" class="settings-pw-toggle-btn" data-target="current_password" aria-label="Toggle current password visibility">
                                                <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- New Password -->
                                    <div class="settings-form-group">
                                        <label for="new_password" class="settings-form-label">New Password <span class="text-danger">*</span></label>
                                        <div class="settings-input-wrapper settings-input-password-wrapper">
                                            <input type="password" id="new_password" name="new_password" class="settings-input" required minlength="8" placeholder="At least 8 characters">
                                            <button type="button" class="settings-pw-toggle-btn" data-target="new_password" aria-label="Toggle new password visibility">
                                                <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                                </svg>
                                            </button>
                                        </div>
                                        <span class="settings-input-help">Minimum 8 characters.</span>
                                    </div>

                                    <!-- Confirm Password -->
                                    <div class="settings-form-group">
                                        <label for="confirm_password" class="settings-form-label">Confirm New Password <span class="text-danger">*</span></label>
                                        <div class="settings-input-wrapper settings-input-password-wrapper">
                                            <input type="password" id="confirm_password" name="confirm_password" class="settings-input" required minlength="8" placeholder="Re-type new password">
                                            <button type="button" class="settings-pw-toggle-btn" data-target="confirm_password" aria-label="Toggle confirm password visibility">
                                                <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                                </svg>
                                            </button>
                                        </div>
                                        <span class="settings-input-help" id="password-match-hint">Must match the new password.</span>
                                    </div>
                                </div>

                                <div class="settings-form-actions">
                                    <button type="button" class="btn btn-secondary" id="btn-cancel-password">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                        Update Password
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </section>

                <!-- 
                |--------------------------------------------------------------------------
                | SECTION 3: NOTIFICATIONS
                |--------------------------------------------------------------------------
                -->
                <section class="settings-section" id="notifications">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-card-header-left">
                                <h2>Notification Channels</h2>
                                <p>Manage in-app alerts and updates for your campus reports and claims.</p>
                            </div>
                        </div>

                        <div class="settings-toggle-list">
                            <!-- In-App Report Alerts -->
                            <div class="settings-toggle-item">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">
                                        <span>Report Status Updates</span>
                                        <span class="settings-badge-active">Active</span>
                                    </div>
                                    <p class="settings-toggle-desc">Receive immediate notifications whenever the review, claim, or returned status of an item you reported changes.</p>
                                </div>
                                <label class="settings-switch" aria-label="Toggle Report Status Updates">
                                    <input type="checkbox" checked disabled>
                                    <span class="settings-slider"></span>
                                </label>
                            </div>

                            <!-- Claim Verification Alerts -->
                            <div class="settings-toggle-item">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">
                                        <span>Claim Verification Alerts</span>
                                        <span class="settings-badge-active">Active</span>
                                    </div>
                                    <p class="settings-toggle-desc">Get notified when a student submits a proof-of-ownership claim on an item you found, or when your claim is reviewed.</p>
                                </div>
                                <label class="settings-switch" aria-label="Toggle Claim Verification Alerts">
                                    <input type="checkbox" checked disabled>
                                    <span class="settings-slider"></span>
                                </label>
                            </div>

                            <!-- Campus Broadcasts -->
                            <div class="settings-toggle-item">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">
                                        <span>Administrative &amp; Safety Announcements</span>
                                        <span class="settings-badge-active">Active</span>
                                    </div>
                                    <p class="settings-toggle-desc">Broadcasts from campus administrators regarding item pickup drives, lost-and-found deadlines, and policy notices.</p>
                                </div>
                                <label class="settings-switch" aria-label="Toggle Campus Announcements">
                                    <input type="checkbox" checked disabled>
                                    <span class="settings-slider"></span>
                                </label>
                            </div>

                            <!-- Email Digest Option -->
                            <div class="settings-toggle-item">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">
                                        <span>Campus Email Notifications</span>
                                    </div>
                                    <p class="settings-toggle-desc">Send immediate email alerts to your registered campus email when potential item matches are identified.</p>
                                </div>
                                <label class="settings-switch" aria-label="Toggle Email Notifications">
                                    <input type="checkbox" checked>
                                    <span class="settings-slider"></span>
                                </label>
                            </div>
                        </div>

                        <p class="text-secondary" style="font-size: var(--font-xs); margin-top: var(--space-4); margin-bottom: 0;">
                            * Essential security and claim status notifications are dispatched automatically in accordance with campus property recovery policies.
                        </p>
                    </div>
                </section>

                <!-- 
                |--------------------------------------------------------------------------
                | SECTION 4: APPEARANCE
                |--------------------------------------------------------------------------
                -->
                <section class="settings-section" id="appearance">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-card-header-left">
                                <h2>Appearance &amp; Theme</h2>
                                <p>Customize your visual display preferences across LostLink.</p>
                            </div>
                        </div>

                        <div class="settings-theme-grid">
                            <!-- Light Mode -->
                            <div class="settings-theme-card" data-theme-choice="light" role="button" tabindex="0" aria-label="Select Light Theme">
                                <div class="settings-theme-check">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </div>
                                <div class="settings-theme-icon-box">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="5" r="5"></circle>
                                        <line x1="12" y1="1" x2="12" y2="3"></line>
                                        <line x1="12" y1="21" x2="12" y2="23"></line>
                                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                        <line x1="1" y1="12" x2="3" y2="12"></line>
                                        <line x1="21" y1="12" x2="23" y2="12"></line>
                                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                                    </svg>
                                </div>
                                <div class="settings-theme-title">Light Mode</div>
                                <p class="settings-theme-desc">Crisp, high-contrast bright appearance optimized for day.</p>
                            </div>

                            <!-- Dark Mode -->
                            <div class="settings-theme-card" data-theme-choice="dark" role="button" tabindex="0" aria-label="Select Dark Theme">
                                <div class="settings-theme-check">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </div>
                                <div class="settings-theme-icon-box">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                                    </svg>
                                </div>
                                <div class="settings-theme-title">Dark Mode</div>
                                <p class="settings-theme-desc">Low-light palette that reduces glare and eye strain.</p>
                            </div>

                            <!-- System Default -->
                            <div class="settings-theme-card" data-theme-choice="system" role="button" tabindex="0" aria-label="Select System Default Theme">
                                <div class="settings-theme-check">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </div>
                                <div class="settings-theme-icon-box">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                        <line x1="8" y1="21" x2="16" y2="21"></line>
                                        <line x1="12" y1="17" x2="12" y2="21"></line>
                                    </svg>
                                </div>
                                <div class="settings-theme-title">System Preference</div>
                                <p class="settings-theme-desc">Matches your computer or smartphone operating system theme.</p>
                            </div>
                        </div>

                        <p class="text-secondary" style="font-size: var(--font-xs); margin: 0;">
                            Theme changes take effect instantly across all LostLink sections and persist in your browser.
                        </p>
                    </div>
                </section>

                <!-- 
                |--------------------------------------------------------------------------
                | SECTION 5: PRIVACY
                |--------------------------------------------------------------------------
                -->
                <section class="settings-section" id="privacy">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-card-header-left">
                                <h2>Privacy &amp; Data Protection</h2>
                                <p>Control how your information is safeguarded during report investigations.</p>
                            </div>
                        </div>

                        <div class="settings-toggle-list">
                            <!-- Contact Protection -->
                            <div class="settings-toggle-item">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">
                                        <span>Verified Claimant Contact Protection</span>
                                        <span class="settings-badge-active">Protected</span>
                                    </div>
                                    <p class="settings-toggle-desc">Your direct telephone number and personal identifiers are kept private from general public browsing. Handover details are only revealed once an item claim has been confirmed.</p>
                                </div>
                            </div>

                            <!-- Campus Identification Audit -->
                            <div class="settings-toggle-item">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">
                                        <span>Campus Student ID Accountability</span>
                                        <span class="settings-badge-active">Enforced</span>
                                    </div>
                                    <p class="settings-toggle-desc">All item postings are tied to authenticated student accounts to prevent fraudulent claims and ensure transparent community accountability.</p>
                                </div>
                            </div>

                            <!-- Activity Ledger Link -->
                            <div class="settings-toggle-item">
                                <div class="settings-toggle-info">
                                    <div class="settings-toggle-title">
                                        <span>My Reported Items &amp; Claims History</span>
                                    </div>
                                    <p class="settings-toggle-desc">You can review, edit, or close your existing lost and found reports at any time via the My Reports dashboard.</p>
                                </div>
                                <div style="flex-shrink: 0;">
                                    <a href="<?php echo BASE_URL; ?>/items/my_reports.php" class="btn btn-primary" style="font-size: var(--font-xs); padding: 8px 14px;">
                                        View My Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 
                |--------------------------------------------------------------------------
                | SECTION 6: ABOUT
                |--------------------------------------------------------------------------
                -->
                <section class="settings-section" id="about">
                    <div class="settings-card">
                        <!-- About Hero -->
                        <div class="settings-about-hero">
                            <div class="settings-about-logo">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                </svg>
                            </div>
                            <div class="settings-about-meta">
                                <h3>
                                    <span><?php echo htmlspecialchars(APP_NAME); ?></span>
                                    <span class="settings-about-version-badge">v<?php echo htmlspecialchars(APP_VERSION); ?></span>
                                </h3>
                                <p class="settings-about-desc">
                                    A campus lost-and-found platform designed to help students and the campus community report, discover, claim, and return lost items.
                                </p>
                            </div>
                        </div>

                        <!-- System Information Details -->
                        <div class="settings-info-grid">
                            <div class="settings-info-item">
                                <div class="settings-info-label">Application Version</div>
                                <div class="settings-info-value">v<?php echo htmlspecialchars(APP_VERSION); ?> (Stable)</div>
                            </div>
                            <div class="settings-info-item">
                                <div class="settings-info-label">Platform Purpose</div>
                                <div class="settings-info-value">Campus Property Recovery &amp; Claims</div>
                            </div>
                            <div class="settings-info-item">
                                <div class="settings-info-label">Property Holding Policy</div>
                                <div class="settings-info-value">90-Day Campus Retention Period</div>
                            </div>
                            <div class="settings-info-item">
                                <div class="settings-info-label">Verification System</div>
                                <div class="settings-info-value">Two-Tier Proof of Ownership</div>
                            </div>
                        </div>

                        <!-- Campus Support & Helpdesk Card -->
                        <div class="settings-card-header" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                            <div class="settings-card-header-left">
                                <h2>Campus Helpdesk &amp; Support</h2>
                                <p>For high-value property, government documents, student identity cards, or physical drop-offs, visit the Campus Security Helpdesk at the Student Center during standard campus hours.</p>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Interactive UI Components Logic -->
    <script src="../assets/js/components.js"></script>
    <!-- Settings Specific Logic -->
    <script src="settings.js?v=<?php echo time(); ?>"></script>
</body>
</html>
