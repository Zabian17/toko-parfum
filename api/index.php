<?php

// Force SCRIPT_NAME to /index.php so Laravel root baseUrl is empty
// This ensures /api/products matches /api/products, /katalog matches /katalog, etc.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

// Prepare writable /tmp directories on serverless environments
$tmpStorage = '/tmp/storage';
$tmpDirs = [
    $tmpStorage . '/framework/views',
    $tmpStorage . '/framework/cache/data',
    $tmpStorage . '/framework/sessions',
    $tmpStorage . '/logs',
    $tmpStorage . '/app/public',
    '/tmp/bootstrap/cache',
];

foreach ($tmpDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Set storage and cache redirects
putenv("LARAVEL_STORAGE_PATH={$tmpStorage}");
$_ENV['LARAVEL_STORAGE_PATH'] = $tmpStorage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $tmpStorage;

putenv("VIEW_COMPILED_PATH={$tmpStorage}/framework/views");
putenv('APP_SERVICES_CACHE=/tmp/bootstrap/cache/services.php');

putenv('APP_PACKAGES_CACHE=/tmp/bootstrap/cache/packages.php');
putenv('APP_CONFIG_CACHE=/tmp/bootstrap/cache/config.php');
putenv('APP_ROUTES_CACHE=/tmp/bootstrap/cache/routes.php');
putenv('APP_EVENTS_CACHE=/tmp/bootstrap/cache/events.php');

// Default serverless runtime settings if not configured
if (!getenv('CACHE_STORE')) {
    putenv('CACHE_STORE=array');
    $_ENV['CACHE_STORE'] = 'array';
}
if (!getenv('SESSION_DRIVER')) {
    putenv('SESSION_DRIVER=cookie');
    $_ENV['SESSION_DRIVER'] = 'cookie';
}
if (!getenv('LOG_CHANNEL')) {
    putenv('LOG_CHANNEL=stderr');
    $_ENV['LOG_CHANNEL'] = 'stderr';
}

// SQLite database setup in /tmp
$dbConn = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? null);
if (!$dbConn || $dbConn === 'sqlite') {
    $targetDb = '/tmp/database.sqlite';
    $sourceDb = __DIR__ . '/../database/database.sqlite';

    if (file_exists($sourceDb) && filesize($sourceDb) > 0) {
        $needCopy = !file_exists($targetDb)
            || filesize($targetDb) < filesize($sourceDb)
            || filemtime($sourceDb) > filemtime($targetDb);

        if (!$needCopy) {
            try {
                $sqlite = new \SQLite3($targetDb);
                $pCount = @$sqlite->querySingle('SELECT count(*) FROM products');
                $sqlite->close();
                if ($pCount === 0 || $pCount === false) {
                    $needCopy = true;
                }
            } catch (\Throwable $e) {
                $needCopy = true;
            }
        }

        if ($needCopy) {
            @copy($sourceDb, $targetDb);
            @chmod($targetDb, 0666);
        }
    } elseif (!file_exists($targetDb)) {
        @touch($targetDb);
        @chmod($targetDb, 0666);
    }

    putenv('DB_CONNECTION=sqlite');
    putenv("DB_DATABASE={$targetDb}");
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['DB_DATABASE'] = $targetDb;
    $_SERVER['DB_CONNECTION'] = 'sqlite';
    $_SERVER['DB_DATABASE'] = $targetDb;
}

require __DIR__ . '/../public/index.php';
