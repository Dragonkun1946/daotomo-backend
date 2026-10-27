<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\SepayWebhookController;
use App\Http\Middleware\AdminMiddleware;

// ─── Health Check ─────────────────────────────────────────────────────────────
Route::get('/health', function () {
    $dbStatus = 'OK';
    $dbError = null;
    $productCount = 0;
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $productCount = \Illuminate\Support\Facades\DB::table('products')->count();
    } catch (\Throwable $e) {
        $dbStatus = 'ERROR';
        $dbError = $e->getMessage();
    }

    return response()->json([
        'status' => $dbStatus === 'OK' ? 'OK' : 'DEGRADED',
        'message' => 'Đảo Tò Mò API is running 🏝️ (Laravel Framework)',
        'database' => [
            'status' => $dbStatus,
            'driver' => config('database.default'),
            'product_count' => $productCount,
            'error' => $dbError,
        ],
    ]);
});

// ─── Public Auth Routes ───────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password/{token}', [AuthController::class, 'resetPassword']);

    // OAuth: Discord
    Route::get('/discord', [AuthController::class, 'redirectToDiscord']);
    Route::get('/discord/callback', [AuthController::class, 'handleDiscordCallback']);
});

// ─── Public Product Routes ────────────────────────────────────────────────────
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

// ─── Order Public Endpoints ───────────────────────────────────────────────────
Route::get('/orders/check-status/{code}', [OrderController::class, 'showByCode']);
Route::get('/orders/status/{code}', [OrderController::class, 'showByCode']);
Route::post('/orders/guest', [OrderController::class, 'storeGuest']);

// ─── SePay Webhook Endpoints ──────────────────────────────────────────────────
Route::match(['GET', 'POST'], '/webhooks/sepay', [SepayWebhookController::class, 'handleWebhook']);
Route::match(['GET', 'POST'], '/sepay/webhook', [SepayWebhookController::class, 'handleWebhook']);

// ─── Protected Routes (Requires Bearer Token) ─────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth profile & Skin management
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/me', [AuthController::class, 'updateMe']);
    Route::post('/auth/skin', [AuthController::class, 'updateSkin']);

    // Order user endpoints
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/my', [OrderController::class, 'myOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::put('/orders/{id}/payment-proof', [OrderController::class, 'submitPaymentProof']);

    // Checkin daily
    Route::post('/checkin', [CheckinController::class, 'store']);
    Route::get('/checkin/me', [CheckinController::class, 'me']);

    // ─── Admin Only Routes ────────────────────────────────────────────────────
    Route::middleware(AdminMiddleware::class)->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        Route::get('/orders', [OrderController::class, 'adminIndex']);
        Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    });
});
