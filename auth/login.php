<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../backend/utils/helpers.php';

// Prevent browser caching to ensure back button forces a reload
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// If already logged in, redirect based on role
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['role']) && ($_SESSION['role'] === (defined('ROLE_ADMIN') ? ROLE_ADMIN : 'admin'))) {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/dashboard/dashboard.php');
    }
    exit;
}

$success_msg = '';
$error_msg = '';

if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}

$mode = (isset($_GET['mode']) && $_GET['mode'] === 'register') ? 'register' : 'login';

$alert_html = '';
if ($success_msg || $error_msg) {
    $alert_type = $success_msg ? 'success' : 'error';
    $alert_icon = $success_msg 
        ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>'
        : '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>';
    $alert_text = htmlspecialchars($success_msg ? $success_msg : $error_msg);
    
    $alert_html = '
            <div class="alert alert-' . $alert_type . '" role="alert" aria-live="polite">
              <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                ' . $alert_icon . '
              </svg>
              <span class="alert-message">' . $alert_text . '</span>
            </div>';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LostLink - Authentication</title>
    <meta
      name="description"
      content="Sign in or create your LostLink account to report, search, and recover lost belongings on campus."
    />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <!-- Theme Logic -->
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme.css" />
    <link rel="stylesheet" href="login.css" />
  </head>
  <body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <main id="main-content" class="auth-page">
      <!-- Left Side: Illustration -->
      <section class="auth-illustration" aria-hidden="true">
        <div class="illustration-scene">
          <!-- Clouds -->
          <div class="cloud cloud-1">
            <svg width="120" height="60" viewBox="0 0 120 60" fill="none">
              <path
                d="M20 45C20 32.85 29.85 23 42 23C44.5 23 46.9 23.4 49.1 24.1C52.6 14.2 61.9 7 73 7C87.3 7 99.3 17.5 101.7 31.3C110.2 33.3 116 41.1 116 50C116 60.5 107.5 69 97 69H23C12.5 69 4 60.5 4 50C4 46.7 4.8 43.6 6.2 40.8"
                fill="white"
                fill-opacity="0.9"
              />
            </svg>
          </div>
          <div class="cloud cloud-2">
            <svg width="90" height="45" viewBox="0 0 120 60" fill="none">
              <path
                d="M20 45C20 32.85 29.85 23 42 23C44.5 23 46.9 23.4 49.1 24.1C52.6 14.2 61.9 7 73 7C87.3 7 99.3 17.5 101.7 31.3C110.2 33.3 116 41.1 116 50C116 60.5 107.5 69 97 69H23C12.5 69 4 60.5 4 50C4 46.7 4.8 43.6 6.2 40.8"
                fill="white"
                fill-opacity="0.7"
              />
            </svg>
          </div>

          <!-- University Building -->
          <div class="building">
            <svg width="280" height="320" viewBox="0 0 280 320" fill="none">
              <!-- Main Building -->
              <rect
                x="40"
                y="80"
                width="200"
                height="200"
                rx="4"
                fill="#FFFFFF"
                stroke="#E2E8F0"
                stroke-width="2"
              />
              <!-- Roof -->
              <path
                d="M30 80L140 20L250 80"
                fill="#2563EB"
                stroke="#1D4ED8"
                stroke-width="2"
              />
              <!-- Clock -->
              <circle
                cx="140"
                cy="60"
                r="14"
                fill="#FFFFFF"
                stroke="#2563EB"
                stroke-width="2"
              />
              <line
                x1="140"
                y1="60"
                x2="140"
                y2="52"
                stroke="#0F172A"
                stroke-width="2"
                stroke-linecap="round"
              />
              <line
                x1="140"
                y1="60"
                x2="146"
                y2="60"
                stroke="#0F172A"
                stroke-width="2"
                stroke-linecap="round"
              />
              <!-- Columns -->
              <rect
                x="60"
                y="100"
                width="12"
                height="160"
                fill="#F1F5F9"
                rx="2"
              />
              <rect
                x="100"
                y="100"
                width="12"
                height="160"
                fill="#F1F5F9"
                rx="2"
              />
              <rect
                x="168"
                y="100"
                width="12"
                height="160"
                fill="#F1F5F9"
                rx="2"
              />
              <rect
                x="208"
                y="100"
                width="12"
                height="160"
                fill="#F1F5F9"
                rx="2"
              />
              <!-- Door -->
              <rect
                x="122"
                y="200"
                width="36"
                height="60"
                rx="4"
                fill="#2563EB"
              />
              <circle cx="150" cy="230" r="3" fill="#FFFFFF" />
              <!-- Windows -->
              <rect
                x="75"
                y="115"
                width="18"
                height="24"
                rx="2"
                fill="#DBEAFE"
                stroke="#CBD5E1"
                stroke-width="1"
              />
              <rect
                x="75"
                y="155"
                width="18"
                height="24"
                rx="2"
                fill="#DBEAFE"
                stroke="#CBD5E1"
                stroke-width="1"
              />
              <rect
                x="183"
                y="115"
                width="18"
                height="24"
                rx="2"
                fill="#DBEAFE"
                stroke="#CBD5E1"
                stroke-width="1"
              />
              <rect
                x="183"
                y="155"
                width="18"
                height="24"
                rx="2"
                fill="#DBEAFE"
                stroke="#CBD5E1"
                stroke-width="1"
              />
              <!-- Steps -->
              <rect
                x="30"
                y="280"
                width="220"
                height="12"
                rx="2"
                fill="#E2E8F0"
              />
              <rect
                x="20"
                y="292"
                width="240"
                height="12"
                rx="2"
                fill="#CBD5E1"
              />
            </svg>
          </div>

          <!-- Trees -->
          <div class="tree tree-1">
            <svg width="60" height="100" viewBox="0 0 60 100" fill="none">
              <rect x="26" y="60" width="8" height="40" rx="2" fill="#8B5E3C" />
              <circle
                cx="30"
                cy="40"
                r="24"
                fill="#22C55E"
                fill-opacity="0.8"
              />
              <circle
                cx="18"
                cy="50"
                r="16"
                fill="#22C55E"
                fill-opacity="0.6"
              />
              <circle
                cx="42"
                cy="50"
                r="16"
                fill="#22C55E"
                fill-opacity="0.6"
              />
            </svg>
          </div>
          <div class="tree tree-2">
            <svg width="50" height="85" viewBox="0 0 60 100" fill="none">
              <rect x="26" y="60" width="8" height="40" rx="2" fill="#8B5E3C" />
              <circle
                cx="30"
                cy="40"
                r="24"
                fill="#22C55E"
                fill-opacity="0.8"
              />
              <circle
                cx="18"
                cy="50"
                r="16"
                fill="#22C55E"
                fill-opacity="0.6"
              />
              <circle
                cx="42"
                cy="50"
                r="16"
                fill="#22C55E"
                fill-opacity="0.6"
              />
            </svg>
          </div>

          <!-- Floating Items -->
          <div class="float-item item-backpack">
            <svg width="70" height="70" viewBox="0 0 200 200" fill="none">
              <rect
                x="50"
                y="55"
                width="100"
                height="110"
                rx="20"
                fill="#2563EB"
              />
              <path
                d="M50 75C50 63.9543 58.9543 55 70 55H130C141.046 55 150 63.9543 150 75V85H50V75Z"
                fill="#1D4ED8"
              />
              <rect
                x="70"
                y="95"
                width="60"
                height="50"
                rx="10"
                fill="#1D4ED8"
              />
              <rect
                x="80"
                y="105"
                width="40"
                height="6"
                rx="3"
                fill="#3B82F6"
              />
              <rect
                x="80"
                y="118"
                width="30"
                height="6"
                rx="3"
                fill="#3B82F6"
              />
              <path
                d="M50 75C40 75 35 85 35 100V140"
                stroke="#1E40AF"
                stroke-width="8"
                stroke-linecap="round"
              />
              <path
                d="M150 75C160 75 165 85 165 100V140"
                stroke="#1E40AF"
                stroke-width="8"
                stroke-linecap="round"
              />
              <path
                d="M85 55V45C85 42 88 40 92 40H108C112 40 115 42 115 45V55"
                stroke="#1E40AF"
                stroke-width="6"
                stroke-linecap="round"
                fill="none"
              />
              <rect
                x="65"
                y="90"
                width="10"
                height="14"
                rx="3"
                fill="#F59E0B"
              />
              <rect
                x="125"
                y="90"
                width="10"
                height="14"
                rx="3"
                fill="#F59E0B"
              />
            </svg>
          </div>

          <div class="float-item item-laptop">
            <svg width="55" height="55" viewBox="0 0 48 48" fill="none">
              <rect
                x="6"
                y="8"
                width="36"
                height="26"
                rx="3"
                fill="#F1F5F9"
                stroke="#2563EB"
                stroke-width="1.5"
              />
              <rect
                x="10"
                y="12"
                width="28"
                height="18"
                rx="2"
                fill="#E2E8F0"
              />
              <rect x="4" y="34" width="40" height="4" rx="2" fill="#CBD5E1" />
            </svg>
          </div>

          <div class="float-item item-wallet">
            <svg width="45" height="45" viewBox="0 0 48 48" fill="none">
              <rect
                x="8"
                y="14"
                width="32"
                height="22"
                rx="4"
                fill="#F1F5F9"
                stroke="#2563EB"
                stroke-width="1.5"
              />
              <path
                d="M8 18V12C8 9.79086 9.79086 8 12 8H36C38.2091 8 40 9.79086 40 12V18"
                stroke="#2563EB"
                stroke-width="1.5"
              />
              <circle
                cx="32"
                cy="25"
                r="3"
                stroke="#2563EB"
                stroke-width="1.5"
              />
            </svg>
          </div>

          <div class="float-item item-keys">
            <svg width="40" height="40" viewBox="0 0 48 48" fill="none">
              <circle
                cx="16"
                cy="16"
                r="8"
                fill="#F1F5F9"
                stroke="#2563EB"
                stroke-width="1.5"
              />
              <circle cx="16" cy="16" r="3" fill="#2563EB" />
              <rect
                x="20"
                y="20"
                width="20"
                height="6"
                rx="3"
                fill="#F1F5F9"
                stroke="#2563EB"
                stroke-width="1.5"
              />
              <rect
                x="34"
                y="20"
                width="4"
                height="10"
                rx="2"
                fill="#F1F5F9"
                stroke="#2563EB"
                stroke-width="1.5"
              />
            </svg>
          </div>

          <div class="float-item item-phone">
            <svg width="38" height="38" viewBox="0 0 48 48" fill="none">
              <rect
                x="14"
                y="4"
                width="20"
                height="40"
                rx="4"
                fill="#F1F5F9"
                stroke="#2563EB"
                stroke-width="1.5"
              />
              <rect
                x="18"
                y="10"
                width="12"
                height="24"
                rx="2"
                fill="#E2E8F0"
              />
              <circle cx="24" cy="38" r="2" fill="#2563EB" />
            </svg>
          </div>

          <div class="float-item item-books">
            <svg width="42" height="42" viewBox="0 0 48 48" fill="none">
              <rect
                x="8"
                y="10"
                width="12"
                height="30"
                rx="2"
                fill="#F1F5F9"
                stroke="#2563EB"
                stroke-width="1.5"
              />
              <rect x="12" y="14" width="8" height="4" rx="1" fill="#E2E8F0" />
              <rect
                x="18"
                y="8"
                width="12"
                height="32"
                rx="2"
                fill="#F1F5F9"
                stroke="#2563EB"
                stroke-width="1.5"
              />
              <rect x="22" y="12" width="8" height="4" rx="1" fill="#E2E8F0" />
              <rect
                x="28"
                y="12"
                width="12"
                height="28"
                rx="2"
                fill="#F1F5F9"
                stroke="#2563EB"
                stroke-width="1.5"
              />
              <rect x="32" y="16" width="8" height="4" rx="1" fill="#E2E8F0" />
            </svg>
          </div>
        </div>
      </section>

      <!-- Right Side: Authentication Card -->
      <section class="auth-card-wrapper">
        <div class="auth-card">
          <!-- Login Form -->
          <form id="login-form" class="auth-form <?php echo $mode === 'login' ? 'showing' : 'hidden'; ?>" method="POST" action="<?php echo BASE_URL; ?>/backend/auth/login.php" novalidate <?php echo $mode === 'login' ? '' : 'style="display: none;"'; ?>>
            <div class="form-header">
              <h1>Welcome Back</h1>
              <p>Sign in to access your LostLink account.</p>
            </div>
            <?php if ($mode === 'login') echo $alert_html; ?>

            <div class="form-group">
              <label for="login-email">Email Address</label>
              <div class="input-wrapper">
                <svg
                  class="input-icon"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path
                    d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                  />
                  <polyline points="22,6 12,13 2,6" />
                </svg>
                <input
                  type="email"
                  id="login-email"
                  name="email"
                  placeholder="you@college.edu"
                  autocomplete="email"
                  maxlength="150"
                  required
                />
              </div>
              <span class="validation-msg" id="login-email-error"></span>
            </div>

            <div class="form-group">
              <label for="login-password">Password</label>
              <div class="input-wrapper">
                <svg
                  class="input-icon"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <input
                  type="password"
                  id="login-password"
                  name="password"
                  placeholder="Enter your password"
                  autocomplete="current-password"
                  maxlength="255"
                  required
                />
                <button
                  type="button"
                  class="toggle-password"
                  aria-label="Show password"
                  data-target="login-password"
                >
                  <svg
                    class="eye-icon"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  <svg
                    class="eye-off-icon"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    style="display: none"
                  >
                    <path
                      d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"
                    />
                    <line x1="1" y1="1" x2="23" y2="23" />
                  </svg>
                </button>
              </div>
              <span class="validation-msg" id="login-password-error"></span>
            </div>

            <div class="form-options">
              <label class="checkbox-wrapper">
                <input type="checkbox" id="remember-me" name="remember" />
                <span class="checkmark"></span>
                <span class="checkbox-label">Remember Me</span>
              </label>
              <a href="#forgot" class="form-link">Forgot Password?</a>
            </div>

            <button
              type="submit"
              class="btn btn-primary btn-full"
              id="login-submit"
            >
              <span class="btn-text">Sign In</span>
              <span class="btn-spinner" style="display: none">
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                </svg>
              </span>
            </button>

            <p class="form-footer">
              Don't have an account?
              <button type="button" class="text-link" id="show-register">
                Create Account
              </button>
            </p>
          </form>

          <!-- Registration Form -->
          <form
            id="register-form"
            class="auth-form <?php echo $mode === 'register' ? 'showing' : 'hidden'; ?>"
            method="POST"
            action="<?php echo BASE_URL; ?>/backend/auth/register.php"
            novalidate
            <?php echo $mode === 'register' ? '' : 'style="display: none;"'; ?>
          >
            <div class="form-header">
              <h1>Create Account</h1>
              <p>Join LostLink and help make your campus more connected.</p>
            </div>
            <?php if ($mode === 'register') echo $alert_html; ?>

            <div class="form-group">
              <label for="reg-name">Full Name</label>
              <div class="input-wrapper">
                <svg
                  class="input-icon"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
                <input
                  type="text"
                  id="reg-name"
                  name="name"
                  placeholder="John Doe"
                  autocomplete="name"
                  maxlength="150"
                  required
                />
              </div>
              <span class="validation-msg" id="reg-name-error"></span>
            </div>

            <div class="form-group">
              <label for="reg-email">College Email Address</label>
              <div class="input-wrapper">
                <svg
                  class="input-icon"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path
                    d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                  />
                  <polyline points="22,6 12,13 2,6" />
                </svg>
                <input
                  type="email"
                  id="reg-email"
                  name="email"
                  placeholder="you@college.edu"
                  autocomplete="email"
                  maxlength="150"
                  required
                />
              </div>
              <span class="validation-msg" id="reg-email-error"></span>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="reg-dept">Department</label>
                <div class="input-wrapper">
                  <svg
                    class="input-icon"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
                  </svg>
                  <input
                    type="text"
                    id="reg-dept"
                    name="department"
                    placeholder="e.g. Computer Science"
                    autocomplete="organization"
                    maxlength="100"
                    required
                  />
                </div>
                <span class="validation-msg" id="reg-dept-error"></span>
              </div>

              <div class="form-group">
                <label for="reg-studentid">Student ID</label>
                <div class="input-wrapper">
                  <svg
                    class="input-icon"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                  >
                    <rect x="3" y="4" width="18" height="16" rx="2" />
                    <path
                      d="M8 2v4M16 2v4M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01"
                    />
                  </svg>
                  <input
                    type="text"
                    id="reg-studentid"
                    name="student_id"
                    placeholder="e.g. 2024001"
                    autocomplete="off"
                    maxlength="20"
                    required
                  />
                </div>
                <span class="validation-msg" id="reg-studentid-error"></span>
              </div>
            </div>

            <div class="form-group">
              <label for="reg-phone">Phone Number</label>
              <div class="input-wrapper">
                <svg
                  class="input-icon"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                <input
                  type="tel"
                  id="reg-phone"
                  name="phone"
                  placeholder="Phone Number"
                  autocomplete="tel"
                  maxlength="20"
                  required
                />
              </div>
              <span class="validation-msg" id="reg-phone-error"></span>
            </div>

            <div class="form-group">
              <label for="reg-password">Password</label>
              <div class="input-wrapper">
                <svg
                  class="input-icon"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <input
                  type="password"
                  id="reg-password"
                  name="password"
                  placeholder="Create a password"
                  autocomplete="new-password"
                  maxlength="255"
                  required
                />
                <button
                  type="button"
                  class="toggle-password"
                  aria-label="Show password"
                  data-target="reg-password"
                >
                  <svg
                    class="eye-icon"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  <svg
                    class="eye-off-icon"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    style="display: none"
                  >
                    <path
                      d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"
                    />
                    <line x1="1" y1="1" x2="23" y2="23" />
                  </svg>
                </button>
              </div>
              <span class="validation-msg" id="reg-password-error"></span>
            </div>

            <div class="form-group">
              <label for="reg-confirm">Confirm Password</label>
              <div class="input-wrapper">
                <svg
                  class="input-icon"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <input
                  type="password"
                  id="reg-confirm"
                  name="confirm_password"
                  placeholder="Confirm your password"
                  autocomplete="new-password"
                  maxlength="255"
                  required
                />
                <button
                  type="button"
                  class="toggle-password"
                  aria-label="Show password"
                  data-target="reg-confirm"
                >
                  <svg
                    class="eye-icon"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  <svg
                    class="eye-off-icon"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    style="display: none"
                  >
                    <path
                      d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"
                    />
                    <line x1="1" y1="1" x2="23" y2="23" />
                  </svg>
                </button>
              </div>
              <span class="validation-msg" id="reg-confirm-error"></span>
            </div>

            <div class="form-group">
              <label class="checkbox-wrapper terms-checkbox">
                <input type="checkbox" id="reg-terms" name="terms" required />
                <span class="checkmark"></span>
                <span class="checkbox-label"
                  >I agree to the
                  <a href="#terms" class="form-link inline">Terms</a> &
                  <a href="#privacy" class="form-link inline"
                    >Privacy Policy</a
                  ></span
                >
              </label>
              <span class="validation-msg" id="reg-terms-error"></span>
            </div>

            <button
              type="submit"
              class="btn btn-primary btn-full"
              id="register-submit"
            >
              <span class="btn-text">Create Account</span>
              <span class="btn-spinner" style="display: none">
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                </svg>
              </span>
            </button>

            <p class="form-footer">
              Already have an account?
              <button type="button" class="text-link" id="show-login">
                Sign In
              </button>
            </p>
          </form>
        </div>
      </section>
    </main>

    <script src="login.js"></script>
  </body>
</html>
