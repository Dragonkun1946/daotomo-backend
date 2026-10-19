<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CheckinController;
use App\Http\Middleware\AdminMiddleware;

// ─── Health Check ─────────────────────────────────────────────────────────────
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Đảo Tò Mò API is running 🏝️ (Laravel Framework)',
    ]);
});

// ─── Public Auth Routes ───────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password/{token}', [AuthController::class, 'resetPassword']);
});

// ─── Public Product Routes ────────────────────────────────────────────────────
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

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
