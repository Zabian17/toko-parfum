<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// Initialize an empty persistent database on Preview only. A shared Postgres
// advisory lock prevents concurrent serverless instances from racing migrations.
if ((getenv('VERCEL_ENV') ?: '') === 'preview' && (getenv('DB_CONNECTION') ?: '') === 'pgsql') {
    $consoleKernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $consoleKernel->bootstrap();

    $connection = Illuminate\Support\Facades\DB::connection();
    $migrationLock = 202610150001;
    $connection->select('SELECT pg_advisory_lock(?)', [$migrationLock]);

    try {
        $schema = $connection->getSchemaBuilder();
        $migrationCount = $schema->hasTable('migrations')
            ? $connection->table('migrations')->count()
            : 0;
        $migrationFiles = glob(__DIR__.'/../database/migrations/*.php') ?: [];

        if ($migrationCount < count($migrationFiles)) {
            if (Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
                throw new RuntimeException('Preview database migration failed.');
            }
        }

        if (!$schema->hasTable('users') || !$connection->table('users')->exists()) {
            if (Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]) !== 0) {
                throw new RuntimeException('Preview database seeding failed.');
            }
        }
    } finally {
        $connection->select('SELECT pg_advisory_unlock(?)', [$migrationLock]);
    }
}

$app->handleRequest(Request::capture());
