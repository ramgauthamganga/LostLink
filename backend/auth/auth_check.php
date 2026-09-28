<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Authentication Guard
|--------------------------------------------------------------------------
|
| Protects authenticated pages.
| Redirects guests to the login page.
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
| Check Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true
) {

    $_SESSION['error'] = 'Please log in to continue.';

    header('Location: ' . BASE_URL . '/auth/login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Required Session Data
|--------------------------------------------------------------------------
*/

$requiredSessionKeys = [
    'user_id',
    'user_code',
    'name',
    'role'
];

foreach ($requiredSessionKeys as $key) {

    if (!isset($_SESSION[$key])) {

        session_unset();
        session_destroy();

        header('Location: ' . BASE_URL . '/auth/login.php');

        exit;
    }
}