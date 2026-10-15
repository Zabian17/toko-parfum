<?php

// Prepare writable directories in /tmp for Vercel Serverless environment
if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || getenv('VERCEL')) {
    $tmpDirs = [
        '/tmp/storage/framework/views',
        '/tmp/storage/framework/cache/data',
        '/tmp/storage/framework/sessions',
        '/tmp/storage/logs',
        '/tmp/storage/app/public',
        '/tmp/bootstrap/cache',
    ];

    foreach ($tmpDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    // Direct Laravel caches and compiled views to /tmp
    putenv('VIEW_COMPILED_PATH=/tmp/storage/framework/views');
    putenv('APP_SERVICES_CACHE=/tmp/bootstrap/cache/services.php');
    putenv('APP_PACKAGES_CACHE=/tmp/bootstrap/cache/packages.php');
    putenv('APP_CONFIG_CACHE=/tmp/bootstrap/cache/config.php');
    putenv('APP_ROUTES_CACHE=/tmp/bootstrap/cache/routes.php');
    putenv('APP_EVENTS_CACHE=/tmp/bootstrap/cache/events.php');

    // SQLite fallback in /tmp if external MySQL is not configured
    $dbConnection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'sqlite');
    if ($dbConnection === 'sqlite') {
        $sqliteTarget = '/tmp/database.sqlite';
        if (!file_exists($sqliteTarget)) {
            $seedDb = __DIR__ . '/../database/database.sqlite';
            if (file_exists($seedDb)) {
                @copy($seedDb, $sqliteTarget);
            } else {
                @touch($sqliteTarget);
            }
        }
        putenv("DB_DATABASE={$sqliteTarget}");
        $_ENV['DB_DATABASE'] = $sqliteTarget;
        $_SERVER['DB_DATABASE'] = $sqliteTarget;
    }
}

require __DIR__ . '/../public/index.php';
