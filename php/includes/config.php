<?php

declare(strict_types=1);

function load_env(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $vars[trim($key)] = trim($value);
    }
    return $vars;
}

foreach (load_env(__DIR__ . '/../.env') as $key => $value) {
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

define('APP_ENV', env('APP_ENV', 'development'));
define('IS_PRODUCTION', APP_ENV === 'production');

error_reporting(E_ALL);
if (IS_PRODUCTION) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../data/php-error.log');
} else {
    ini_set('display_errors', '1');
}

define('DB_PATH', env('DB_PATH', __DIR__ . '/../data/problog.sqlite'));
define('APP_NAME', 'ProBlog');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    // Dedicated session directory instead of the machine-wide default
    // (php.ini's session.save_path is often a shared folder used by every
    // local PHP project, which is unnecessary cross-contamination risk).
    $sessionPath = __DIR__ . '/../data/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    session_save_path($sessionPath);

    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => IS_PRODUCTION,
    ]);
    session_start();
}
