<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Database Configuration
|--------------------------------------------------------------------------
|
| Creates a secure PDO connection to the MySQL database.
| This file should be included wherever database access is required.
|
*/

$host = 'localhost';
$dbname = 'lostlink';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {

    $pdo = new PDO($dsn, $username, $password, $options);

} catch (PDOException $exception) {

    die('Database connection failed: ' . $exception->getMessage());

}