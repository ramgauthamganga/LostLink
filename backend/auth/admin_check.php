<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Admin Guard
|--------------------------------------------------------------------------
|
| Restricts access to administrator-only pages.
| Requires the user to be authenticated first.
|
*/


/*
|--------------------------------------------------------------------------
| Authentication Check
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/auth_check.php';


/*
|--------------------------------------------------------------------------
| Verify Administrator Role
|--------------------------------------------------------------------------
*/

if ($_SESSION['role'] !== ROLE_ADMIN) {

    $_SESSION['error'] = 'You do not have permission to access that page.';

    header('Location: ' . BASE_URL . '/dashboard/dashboard.php');

    exit;
}