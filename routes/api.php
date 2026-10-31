<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\DiscordOAuthController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\User\SkinController;
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Checkin\CheckinController;
use App\Http\Controllers\Api\Payment\SepayWebhookController;
use App\Http\Controllers\Api\User\GameLinkController;
use App\Http\Controllers\Api\Internal\InternalMinecraftBridgeController;
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
    Route::middleware('throttle:5,1')->post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password/{token}', [PasswordResetController::class, 'resetPassword']);

    // OAuth: Discord
    Route::get('/discord', [DiscordOAuthController::class, 'redirect']);
    Route::get('/discord/callback', [DiscordOAuthController::class, 'callback']);
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
    Route::get('/auth/me', [ProfileController::class, 'me']);
    Route::put('/auth/me', [ProfileController::class, 'updateMe']);
    Route::post('/auth/skin', [SkinController::class, 'updateSkin']);

    // Order user endpoints
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/my', [OrderController::class, 'myOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::put('/orders/{id}/payment-proof', [OrderController::class, 'submitPaymentProof']);

    // Checkin daily
    Route::post('/checkin', [CheckinController::class, 'store']);
    Route::get('/checkin/me', [CheckinController::class, 'me']);

    // Game Account Linking (1-to-1 PIN Handshake)
    Route::post('/user/game-link', [GameLinkController::class, 'linkGame']);
    Route::delete('/user/game-link', [GameLinkController::class, 'unlinkGame']);

    // ─── Admin Only Routes ────────────────────────────────────────────────────
    Route::middleware(AdminMiddleware::class)->group(function () {
        // Products
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::put('/products/{id}', [AdminProductController::class, 'update']);
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);

        // Orders Management & Delivery
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/deliver', [AdminOrderController::class, 'markPaidAndDeliver']);
        Route::post('/admin/orders/{id}/deliver', [AdminOrderController::class, 'markPaidAndDeliver']);

        // Users Management (List, Change Password, Unlink Minecraft)
        Route::get('/admin/users', [AdminUserController::class, 'index']);
        Route::put('/admin/users/{id}/password', [AdminUserController::class, 'changePassword']);
        Route::post('/admin/users/{id}/unlink', [AdminUserController::class, 'unlinkGame']);
        Route::delete('/admin/users/{id}/link', [AdminUserController::class, 'unlinkGame']);
    });
});

// ─── Internal Minecraft Bridge Routes (Protected by Bridge Token) ─────────────
Route::prefix('internal/minecraft')->group(function () {
    Route::post('/link/request-pin', [InternalMinecraftBridgeController::class, 'requestLinkPin']);
    Route::get('/deliveries/pending', [InternalMinecraftBridgeController::class, 'getPendingDeliveries']);
    Route::post('/deliveries/confirm', [InternalMinecraftBridgeController::class, 'confirmDeliveries']);
    Route::get('/leaderboard/streaks', [InternalMinecraftBridgeController::class, 'getStreaksLeaderboard']);
    Route::get('/player/{identifier}/streak', [InternalMinecraftBridgeController::class, 'getPlayerStreak']);
});
