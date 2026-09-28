<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink User Registration
|--------------------------------------------------------------------------
|
| Handles new user registration.
| Accepts POST requests only.
| Validates user input.
| Creates a new student account.
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

    header('Location: ' . BASE_URL . '/auth/login.php?mode=register');

    exit;
}


/**
 * Redirect with a success message.
 */
function redirectWithSuccess(string $message): never
{
    $_SESSION['success'] = $message;

    header('Location: ' . BASE_URL . '/auth/login.php');

    exit;
}


/**
 * Generate a unique public user code.
 *
 * Example:
 * USR-840512937
 */
function generateUserCode(PDO $pdo): string
{
    do {

        $userCode = 'USR-' . random_int(100000000, 999999999);

        $statement = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE user_code = ?"
        );

        $statement->execute([$userCode]);

    } while ($statement->fetch());

    return $userCode;
}


/*
|--------------------------------------------------------------------------
| Main Registration Flow
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

$name = trim($_POST['name'] ?? '');
$studentId = trim($_POST['student_id'] ?? '');
$department = trim($_POST['department'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';


/*
|--------------------------------------------------------------------------
| Validate Required Fields
|--------------------------------------------------------------------------
*/

if ($name === '') {
    redirectWithError('Full name is required.');
}

if ($studentId === '') {
    redirectWithError('Student ID is required.');
}

if ($department === '') {
    redirectWithError('Department is required.');
}

if ($phone === '') {
    redirectWithError('Phone number is required.');
}

if ($email === '') {
    redirectWithError('Email address is required.');
}

if ($password === '') {
    redirectWithError('Password is required.');
}

if ($confirmPassword === '') {
    redirectWithError('Please confirm your password.');
}


/*
|--------------------------------------------------------------------------
| Validate Maximum Lengths
|--------------------------------------------------------------------------
*/

if (mb_strlen($name) > 150) {
    redirectWithError('Full name cannot exceed 150 characters.');
}

if (mb_strlen($studentId) > 20) {
    redirectWithError('Student ID cannot exceed 20 characters.');
}

if (mb_strlen($department) > 100) {
    redirectWithError('Department cannot exceed 100 characters.');
}

if (mb_strlen($phone) > 20) {
    redirectWithError('Phone number cannot exceed 20 characters.');
}

if (mb_strlen($email) > 150) {
    redirectWithError('Email address cannot exceed 150 characters.');
}


/*
|--------------------------------------------------------------------------
| Validate Name
|--------------------------------------------------------------------------
*/

if (!preg_match("/^[a-zA-Z\s.'-]+$/", $name)) {
    redirectWithError('Full name contains invalid characters.');
}


/*
|--------------------------------------------------------------------------
| Validate Email
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithError('Please enter a valid email address.');
}


/*
|--------------------------------------------------------------------------
| Validate Phone Number
|--------------------------------------------------------------------------
*/

if (!preg_match('/^[0-9]{10,20}$/', $phone)) {
    redirectWithError('Please enter a valid phone number.');
}


/*
|--------------------------------------------------------------------------
| Validate Password
|--------------------------------------------------------------------------
*/

if (strlen($password) < 8) {
    redirectWithError('Password must contain at least 8 characters.');
}

if (strlen($password) > 255) {
    redirectWithError('Password is too long.');
}


/*
|--------------------------------------------------------------------------
| Confirm Password
|--------------------------------------------------------------------------
*/

if ($password !== $confirmPassword) {
    redirectWithError('Passwords do not match.');
}

/*
|--------------------------------------------------------------------------
| Check Duplicate Student ID
|--------------------------------------------------------------------------
*/

$statement = $pdo->prepare(
    "SELECT id
     FROM users
     WHERE student_id = ?
     LIMIT 1"
);

$statement->execute([$studentId]);

if ($statement->fetch()) {
    redirectWithError('Student ID is already registered.');
}


/*
|--------------------------------------------------------------------------
| Check Duplicate Email Address
|--------------------------------------------------------------------------
*/

$statement = $pdo->prepare(
    "SELECT id
     FROM users
     WHERE email = ?
     LIMIT 1"
);

$statement->execute([$email]);

if ($statement->fetch()) {
    redirectWithError('Email address is already registered.');
}


/*
|--------------------------------------------------------------------------
| Prepare User Information
|--------------------------------------------------------------------------
*/

$userCode = generateUserCode($pdo);

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);

if ($hashedPassword === false) {
    redirectWithError('Failed to process password.');
}


/*
|--------------------------------------------------------------------------
| Prepare Default Values
|--------------------------------------------------------------------------
*/

$role = ROLE_STUDENT;

$isBanned = 0;

$profileImage = DEFAULT_PROFILE_IMAGE;

/*
|--------------------------------------------------------------------------
| Register User
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    $statement = $pdo->prepare(

        "INSERT INTO users (

            user_code,
            name,
            student_id,
            email,
            password,
            department,
            phone,
            profile_image,
            role,
            is_banned

        ) VALUES (

            :user_code,
            :name,
            :student_id,
            :email,
            :password,
            :department,
            :phone,
            :profile_image,
            :role,
            :is_banned

        )"

    );

    $statement->execute([

        ':user_code'      => $userCode,
        ':name'           => $name,
        ':student_id'     => $studentId,
        ':email'          => $email,
        ':password'       => $hashedPassword,
        ':department'     => $department,
        ':phone'          => $phone,
        ':profile_image'  => $profileImage,
        ':role'           => $role,
        ':is_banned'      => $isBanned

    ]);

    $pdo->commit();

    redirectWithSuccess(
        'Account created successfully. Please log in.'
    );

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($exception->getMessage());

    redirectWithError(
        'Registration failed. Please try again.'
    );

}