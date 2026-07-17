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

// Oturum baslatma includes/db.php'nin sonunda yapiliyor - veritabani
// destekli oturum handler'i icin once db() baglantisinin hazir olmasi
// gerekiyor (bkz. includes/session_handler.php).
