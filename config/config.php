<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Application Configuration
|--------------------------------------------------------------------------
|
| Stores global application settings used throughout the project.
| Modify this file when changing application-wide configuration.
|
*/


/*
|--------------------------------------------------------------------------
| Application Information
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'LostLink');
define('APP_VERSION', '1.0.0');


/*
|--------------------------------------------------------------------------
| Timezone
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Kolkata');


/*
|--------------------------------------------------------------------------
| Application Filesystem & Browser Root
|--------------------------------------------------------------------------
|
| PROJECT_ROOT: Authoritative filesystem path to the LostLink directory.
| Derived directly from the location of config.php itself.
|
| BASE_URL: Authoritative browser URL for the LostLink project root.
| Dynamically preserves all directory segments between web root and LostLink.
|
*/

define('PROJECT_ROOT', realpath(__DIR__ . '/..') ?: dirname(__DIR__));

$protocol = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1'))
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'https://' : 'http://';

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$basePath = null;

// Determine web path segment dynamically from executing script relative to PROJECT_ROOT
if (isset($_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME'])) {
    $normProject = str_replace('\\', '/', PROJECT_ROOT);
    $normScriptFile = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']) ?: $_SERVER['SCRIPT_FILENAME']);
    $normScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);

    if (stripos($normScriptFile, $normProject) === 0) {
        $relPathInsideProject = substr($normScriptFile, strlen($normProject));
        $relLen = strlen($relPathInsideProject);
        if ($relLen > 0 && substr_compare($normScript, $relPathInsideProject, -$relLen, $relLen, true) === 0) {
            $basePath = substr($normScript, 0, strlen($normScript) - $relLen);
        }
    }
}

// Fallback to DOCUMENT_ROOT comparison if SCRIPT_FILENAME/SCRIPT_NAME derivation was inconclusive
if ($basePath === null && isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '') {
    $normDoc = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']);
    $normProject = str_replace('\\', '/', PROJECT_ROOT);

    if (strcasecmp($normProject, $normDoc) === 0) {
        $basePath = '';
    } elseif (stripos($normProject, $normDoc) === 0) {
        $basePath = substr($normProject, strlen($normDoc));
        if (!empty($_SERVER['SCRIPT_NAME']) && $basePath !== '') {
            $normScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
            if (stripos($normScript, $basePath) === 0) {
                $basePath = substr($normScript, 0, strlen($basePath));
            }
        }
    }
}

// CLI / fallback environment handling
if ($basePath === null) {
    if (PHP_SAPI === 'cli') {
        $basePath = getenv('APP_URL') ? (string) parse_url(getenv('APP_URL'), PHP_URL_PATH) : '';
    } else {
        trigger_error('Unable to determine dynamic BASE_URL from server environment.', E_USER_WARNING);
        $basePath = '';
    }
}

define('BASE_URL', rtrim($protocol . $host . $basePath, '/'));

/*
|--------------------------------------------------------------------------
| Upload Configuration
|--------------------------------------------------------------------------
*/

define('ITEM_UPLOAD_PATH', __DIR__ . '/../assets/uploads/items/');
define('PROFILE_UPLOAD_PATH', __DIR__ . '/../assets/uploads/profiles/');


/*
|--------------------------------------------------------------------------
| Default Files
|--------------------------------------------------------------------------
*/

define('DEFAULT_PROFILE_IMAGE', 'default-profile.png');


/*
|--------------------------------------------------------------------------
| Upload Limits
|--------------------------------------------------------------------------
*/

define('MAX_ITEM_IMAGES', 5);

define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB


/*
|--------------------------------------------------------------------------
| Allowed Image Types
|--------------------------------------------------------------------------
*/

define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/webp'
]);