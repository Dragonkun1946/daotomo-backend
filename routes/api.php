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
use App\Http\Controllers\Api\Admin\AdminSupportController;
use App\Http\Controllers\Api\Checkin\CheckinController;
use App\Http\Controllers\Api\Payment\SepayWebhookController;
use App\Http\Controllers\Api\Support\SupportTicketController;
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
    Route::middleware('throttle:10,1')->post('/register', [AuthController::class, 'register']);
    Route::middleware('throttle:10,1')->post('/login', [AuthController::class, 'login']);
    Route::middleware('throttle:5,1')->post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::middleware('throttle:10,1')->post('/reset-password/{token}', [PasswordResetController::class, 'resetPassword']);

    // OAuth: Discord
    Route::get('/discord', [DiscordOAuthController::class, 'redirect']);
    Route::get('/discord/callback', [DiscordOAuthController::class, 'callback']);
});

// ─── Public Product Routes ────────────────────────────────────────────────────
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

// ─── Order Public Endpoints ───────────────────────────────────────────────────
Route::middleware('throttle:30,1')->get('/orders/check-status/{code}', [OrderController::class, 'showByCode']);
Route::middleware('throttle:30,1')->get('/orders/status/{code}', [OrderController::class, 'showByCode']);
Route::middleware('throttle:10,1')->post('/orders/guest', [OrderController::class, 'storeGuest']);

// ─── Public Support & Discord Ticket Routes ──────────────────────────────────
Route::get('/support/config', [SupportTicketController::class, 'publicConfig']);
Route::middleware('throttle:6,1')->post('/tickets', [SupportTicketController::class, 'store']);
Route::get('/tickets/lookup', [SupportTicketController::class, 'lookup']);
Route::middleware('throttle:15,1')->post('/tickets/{codeOrId}/reply', [SupportTicketController::class, 'reply']);
Route::middleware('throttle:15,1')->post('/tickets/{codeOrId}/close', [SupportTicketController::class, 'close']);

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

    // Support Tickets (Authenticated User History)
    Route::get('/tickets/my', [SupportTicketController::class, 'myTickets']);

    // Game Account Linking (1-to-1 PIN Handshake)
    Route::middleware('throttle:10,1')->post('/user/game-link', [GameLinkController::class, 'linkGame']);
    Route::delete('/user/game-link', [GameLinkController::class, 'unlinkGame']);

    // ─── Admin Only Routes ────────────────────────────────────────────────────
    Route::middleware(AdminMiddleware::class)->group(function () {
        // Products
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::put('/products/{id}', [AdminProductController::class, 'update']);
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);

        // Orders Management & Delivery
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/admin/orders', [AdminOrderController::class, 'index']);
        Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/deliver', [AdminOrderController::class, 'markPaidAndDeliver']);
        Route::post('/admin/orders/{id}/deliver', [AdminOrderController::class, 'markPaidAndDeliver']);

        // Users Management (List, Change Password, Unlink Minecraft, Delete)
        Route::get('/admin/users', [AdminUserController::class, 'index']);
        Route::put('/admin/users/{id}/password', [AdminUserController::class, 'changePassword']);
        Route::post('/admin/users/{id}/unlink', [AdminUserController::class, 'unlinkGame']);
        Route::delete('/admin/users/{id}/link', [AdminUserController::class, 'unlinkGame']);
        Route::delete('/admin/users/{id}', [AdminUserController::class, 'destroy']);

        // Support Tickets & Discord Webhook Management
        Route::get('/admin/tickets', [AdminSupportController::class, 'index']);
        Route::put('/admin/tickets/{id}', [AdminSupportController::class, 'updateStatus']);
        Route::put('/admin/support/settings', [AdminSupportController::class, 'updateSettings']);
        Route::post('/admin/support/test-webhook', [AdminSupportController::class, 'testWebhook']);
    });
});

// ─── Internal Minecraft Bridge Routes (Protected by Bridge Token) ─────────────
Route::middleware('throttle:60,1')->prefix('internal/minecraft')->group(function () {
    Route::post('/link/request-pin', [InternalMinecraftBridgeController::class, 'requestLinkPin']);
    Route::post('/link/unlink-test', [InternalMinecraftBridgeController::class, 'unlinkTestPlayer']);
    Route::get('/deliveries/pending', [InternalMinecraftBridgeController::class, 'getPendingDeliveries']);
    Route::post('/deliveries/confirm', [InternalMinecraftBridgeController::class, 'confirmDeliveries']);
    Route::post('/deliveries/mock-test', [InternalMinecraftBridgeController::class, 'createMockDelivery']);
    Route::get('/checkins/recent', [InternalMinecraftBridgeController::class, 'getRecentCheckins']);
    Route::post('/checkins/reset-today', [InternalMinecraftBridgeController::class, 'resetTodayCheckin']);
    Route::get('/leaderboard/streaks', [InternalMinecraftBridgeController::class, 'getStreaksLeaderboard']);
    Route::get('/player/{identifier}/streak', [InternalMinecraftBridgeController::class, 'getPlayerStreak']);
    Route::post('/sync-coins', [InternalMinecraftBridgeController::class, 'syncCoins']);
});
