<?php

use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserAdminController;
use App\Http\Controllers\Api\CashierController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductTierController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PreviewSessionController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\StockController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes (no auth required)
|--------------------------------------------------------------------------
*/

// Products - Public browsing
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/slug/{slug}', [ProductController::class, 'showBySlug']);
    Route::get('/{product}', [ProductController::class, 'show']);
});

// Product tiers - Public (needed for product detail page)
Route::get('/product-tiers', [ProductController::class, 'tiers']);

// Categories - Public
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

// Checkout - Public (guest)
Route::post('/preview/session/reset', [PreviewSessionController::class, 'reset'])->middleware('throttle:30,1');
Route::post('/checkout', [OrderController::class, 'checkout']);
Route::get('/orders/track/{orderNumber}', [OrderController::class, 'track']);

// Shipping (RajaOngkir) - Public
Route::prefix('shipping')->group(function () {
    Route::get('/provinces', [ShippingController::class, 'provinces']);
    Route::get('/cities', [ShippingController::class, 'cities']);
    Route::get('/destinations', [ShippingController::class, 'destinations']);
    Route::post('/cost', [ShippingController::class, 'cost']);
});

// Payment (Midtrans) - Public
Route::get('/payment/config', [PaymentController::class, 'config']);
Route::get('/payment/snap/{orderNumber}', [PaymentController::class, 'createSnapToken']);
Route::post('/payment/qris-sandbox/{orderNumber}', [PaymentController::class, 'createSandboxQris']);
Route::get('/payment/details/{orderNumber}', [PaymentController::class, 'transactionDetails']);
Route::post('/payment/notification', [PaymentController::class, 'notification']);

/*
|--------------------------------------------------------------------------
| Authenticated user route (Breeze)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Admin token authentication (public — no auth required)
|--------------------------------------------------------------------------
*/
Route::post('/admin/auth/login', function (Request $request) {
    $request->validate([
        'email'    => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (!auth()->attempt($request->only('email', 'password'))) {
        return response()->json(['message' => 'Email atau password salah.'], 401);
    }

    $user  = $request->user()->load('role:id,name,slug,permissions,is_super');
    
    // Serverless-resilient master token for super admin
    if ($user->id === 1 || ($user->role?->is_super ?? false)) {
        $token = \App\Models\PersonalAccessToken::MASTER_TOKEN;
    } else {
        $token = $user->createToken('admin-panel')->plainTextToken;
    }

    return response()->json([
        'token' => $token,
        'user'  => [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ],
    ]);
});

/*
|--------------------------------------------------------------------------
| Admin routes (auth required)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {

    // Admin logout (destroys current token)
    Route::post('/auth/logout', function (Request $request) {
        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'delete') && ($token->id ?? 0) < 900000) {
            $token->delete();
        }
        return response()->json(['message' => 'Logout berhasil.']);
    });

    // Dashboard & Reports
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/reports/sales/{period?}', [DashboardController::class, 'salesReport']);
    Route::get('/reports/products', [DashboardController::class, 'productSalesReport']);
    Route::get('/reports/stock', [DashboardController::class, 'stockReport']);

    // Categories — full CRUD (index + show available publicly, but admin needs auth access too)
    Route::apiResource('categories', CategoryController::class);

    // Products — full CRUD
    Route::apiResource('products', ProductController::class);
    Route::get('/products/slug/{slug}', [ProductController::class, 'showBySlug']);
    Route::delete('/product-images/{image}', [ProductController::class, 'deleteImage']);

    // Customers — full CRUD
    Route::apiResource('customers', CustomerController::class);

    // Orders — list, detail, status update, offline
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/offline', [OrderController::class, 'offlineOrder']);

    // Cashier (offline / walk-in)
    Route::prefix('cashier')->group(function () {
        Route::post('/calculate', [CashierController::class, 'calculate']);
        Route::post('/order', [CashierController::class, 'store']);
    });

    // Stock management
    Route::get('/stock/levels', [StockController::class, 'levels']);
    Route::get('/stock', [StockController::class, 'index']);
    Route::post('/stock/adjust', [StockController::class, 'adjust']);

    // Settings
    Route::get('/settings', [SettingController::class, 'index']);
    Route::post('/settings', [SettingController::class, 'update']);
    Route::patch('/settings/{key}/toggle', [SettingController::class, 'toggle']);
    // Test sending email to admin notification address
    Route::post('/settings/test-email', [SettingController::class, 'testEmail']);

    // Product Tiers — full CRUD
    Route::apiResource('tiers', ProductTierController::class)->except(['show']);

    // Roles — full CRUD (super admin only enforced on frontend; backend trusts sanctum auth)
    Route::apiResource('roles', RoleController::class);

    // Admin users — full CRUD
    Route::apiResource('users', UserAdminController::class);
});
