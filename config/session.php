<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Session Configuration
|--------------------------------------------------------------------------
|
| Starts and manages secure user sessions.
| This file should be included on every protected page.
|
*/


/*
|--------------------------------------------------------------------------
| Secure Session Cookie Settings
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}