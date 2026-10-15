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
    $file = public_path(trim($path, '/') . '.html');
    if (File::exists($file)) {
        return response(File::get($file), 200)
            ->header('Content-Type', 'text/html');
    }
    abort(404);
})->where('path', '.+');
