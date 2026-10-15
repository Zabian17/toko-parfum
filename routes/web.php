<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return response(File::get(public_path('index.html')), 200)
        ->header('Content-Type', 'text/html');
});

require __DIR__.'/auth.php';

/**
 * Dynamic HTML page router.
 * Maps any URL path to public/{path}.html when the file exists.
 * Handles: /product, /cart, /checkout,
 *          /pages/cara-pemesanan, /pages/pengiriman, /pages/kebijakan-retur,
 *          /admin/login, /admin/dashboard, /admin/products, etc.
 *
 * Static assets (css/, js/, images/, storage/) are served directly
 * by the PHP built-in server from the public/ folder automatically.
 */
Route::get('{path}', function (string $path) {
    $clean = trim($path, '/');
    if ($clean === 'admin') {
        $clean = 'admin/dashboard';
    }
    if (str_ends_with(strtolower($clean), '.html')) {
        $clean = substr($clean, 0, -5);
    }
    $file = public_path($clean . '.html');
    if (File::exists($file)) {
        return response(File::get($file), 200)
            ->header('Content-Type', 'text/html');
    }
    $directFile = public_path($clean);
    if (File::isFile($directFile)) {
        return response()->file($directFile);
    }
    abort(404);
})->where('path', '.+');

