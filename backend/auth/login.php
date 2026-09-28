<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink User Login
|--------------------------------------------------------------------------
|
| Handles user authentication.
| Accepts POST requests only.
| Verifies user credentials.
| Creates a secure user session.
|
*/


/*
|--------------------------------------------------------------------------
| Required Configuration Files
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/constants.php';


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

/**
 * Redirect with an error message.
 */
function redirectWithError(string $message): never
{
    $_SESSION['error'] = $message;

    header('Location: ' . BASE_URL . '/auth/login.php');

    exit;
}


/**
 * Login authenticated user.
 */
function loginUser(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['logged_in'] = true;

    $_SESSION['user_id'] = $user['id'];

    $_SESSION['user_code'] = $user['user_code'];

    $_SESSION['name'] = $user['name'];

    $_SESSION['role'] = $user['role'];

    $_SESSION['profile_image'] = $user['profile_image'] ?? null;
}


/*
|--------------------------------------------------------------------------
| Main Login Flow
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Accept Only POST Requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithError('Invalid request method.');
}


/*
|--------------------------------------------------------------------------
| Read and Sanitize Form Data
|--------------------------------------------------------------------------
*/

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';


/*
|--------------------------------------------------------------------------
| Validate Required Fields
|--------------------------------------------------------------------------
*/

if ($email === '' || $password === '') {
    redirectWithError('Email and password are required.');
}


/*
|--------------------------------------------------------------------------
| Validate Maximum Lengths
|--------------------------------------------------------------------------
*/

if (mb_strlen($email) > 150) {
    redirectWithError('Email address is too long.');
}

if (mb_strlen($password) > 255) {
    redirectWithError('Password is too long.');
}


/*
|--------------------------------------------------------------------------
| Validate Email Format
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithError('Please enter a valid email address.');
}

/*
|--------------------------------------------------------------------------
| Find User by Email
|--------------------------------------------------------------------------
*/

$statement = $pdo->prepare(

    "SELECT
        id,
        user_code,
        name,
        email,
        password,
        role,
        is_banned,
        profile_image
     FROM users
     WHERE email = ?
     LIMIT 1"

);

$statement->execute([$email]);

$user = $statement->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Verify User Exists
|--------------------------------------------------------------------------
*/

if (!$user) {
    redirectWithError('Invalid email or password.');
}


/*
|--------------------------------------------------------------------------
| Verify Password
|--------------------------------------------------------------------------
*/

if (!password_verify($password, $user['password'])) {
    redirectWithError('Invalid email or password.');
}


/*
|--------------------------------------------------------------------------
| Check Account Status
|--------------------------------------------------------------------------
*/

if ((int)$user['is_banned'] === 1) {
    redirectWithError(
        'Your account has been suspended. Please contact the administrator.'
    );
}

/*
|--------------------------------------------------------------------------
| Login User
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Update Last Login
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(

        "UPDATE users
         SET last_login = CURRENT_TIMESTAMP
         WHERE id = ?"

    );

    $statement->execute([$user['id']]);


    /*
    |--------------------------------------------------------------------------
    | Create User Session
    |--------------------------------------------------------------------------
    */

    loginUser($user);


    /*
    |--------------------------------------------------------------------------
    | Redirect User
    |--------------------------------------------------------------------------
    */

    if ($user['role'] === ROLE_ADMIN) {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/dashboard/dashboard.php');
    }

    exit;

} catch (Throwable $exception) {

    error_log($exception->getMessage());

    redirectWithError(
        'Unable to log in. Please try again.'
    );

}