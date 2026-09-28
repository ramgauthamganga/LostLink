<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink User Logout
|--------------------------------------------------------------------------
|
| Terminates the authenticated user session.
| Removes all session data.
| Deletes the session cookie.
| Redirects the user to the Landing Page.
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
| Destroy Session Data
|--------------------------------------------------------------------------
*/

$_SESSION = [];


/*
|--------------------------------------------------------------------------
| Delete Session Cookie
|--------------------------------------------------------------------------
*/

if (ini_get('session.use_cookies')) {

    $parameters = session_get_cookie_params();

    setcookie(

        session_name(),

        '',

        time() - 3600,

        $parameters['path'],

        $parameters['domain'],

        $parameters['secure'],

        $parameters['httponly']

    );

}


/*
|--------------------------------------------------------------------------
| Destroy Session
|--------------------------------------------------------------------------
*/

session_destroy();


/*
|--------------------------------------------------------------------------
| Redirect User
|--------------------------------------------------------------------------
*/

header('Location: ' . BASE_URL . '/index.html');

exit;