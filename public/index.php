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

// Run schema setup only on the explicit Preview path.
if ((getenv('VERCEL_ENV') ?: '') === 'preview'
    && (getenv('DB_CONNECTION') ?: '') === 'pgsql'
    && parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/__preview/database-setup') {
    $consoleKernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $consoleKernel->bootstrap();

    $connection = Illuminate\Support\Facades\DB::connection();
    $setupMessage = '';
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

        $setupMessage = 'Preview database schema and demo data are ready.';
    } catch (Throwable $error) {
        http_response_code(500);
        Illuminate\Support\Facades\Log::error('Preview database bootstrap failed.', ['exception' => $error]);
        $setupMessage = 'Preview database setup failed. Check Preview runtime logs.';
    } finally {
        $connection->disconnect();
    }

    header('Content-Type: text/plain; charset=UTF-8');
    exit($setupMessage);
}

$app->handleRequest(Request::capture());
