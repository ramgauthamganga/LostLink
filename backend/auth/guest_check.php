<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Guest Guard
|--------------------------------------------------------------------------
|
| Prevents authenticated users from accessing guest-only pages
| such as the Login and Registration page.
|
*/


/*
|--------------------------------------------------------------------------
| Required Configuration Files
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';


/*
|--------------------------------------------------------------------------
| Redirect Authenticated Users
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['logged_in']) &&
    $_SESSION['logged_in'] === true
) {

    header('Location: ' . BASE_URL . '/dashboard/dashboard.php');

    exit;
}