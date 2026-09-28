<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Admin Module - Entry Point
|--------------------------------------------------------------------------
| Cleanly redirects to admin/dashboard.php.
|
*/

require_once __DIR__ . '/../config/config.php';

header('Location: ' . BASE_URL . '/admin/dashboard.php');
exit;
