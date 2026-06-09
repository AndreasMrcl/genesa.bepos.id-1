<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckerController;
use App\Http\Controllers\Api\V1\HistoryController;
use App\Http\Controllers\Api\V1\OpenBillController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\SettlementController;
use App\Http\Controllers\Api\V1\StoreConfigController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('webhooks/midtrans', [WebhookController::class, 'midtrans']);
    Route::get('history/exports/{file}', [HistoryController::class, 'download'])
        ->middleware('signed')
        ->name('api.history.export.download');

    // Authenticated (Sanctum + store valid)
    Route::middleware(['auth:sanctum', 'ensure.api'])->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('auth/devices', [AuthController::class, 'devices']);
        Route::delete('auth/devices/{id}', [AuthController::class, 'revokeDevice']);

        // Master / Bootstrap
        Route::get('bootstrap', [BootstrapController::class, 'index']);
        Route::get('store-config', [StoreConfigController::class, 'show']);

        // Cart
        Route::get('cart', [CartController::class, 'show']);
        Route::delete('cart', [CartController::class, 'reset']);
        Route::post('cart/items', [CartController::class, 'addItem']);
        Route::patch('cart/items/{id}', [CartController::class, 'updateItem']);
        Route::delete('cart/items/{id}', [CartController::class, 'deleteItem']);

        // Orders
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders/sync-status', [OrderController::class, 'syncStatus']);
        Route::post('orders/checkout', [OrderController::class, 'checkout']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
        Route::post('orders/{id}/confirm-online', [OrderController::class, 'confirmOnline']);
        Route::post('orders/{id}/resume-online', [OrderController::class, 'resumeOnline']);
        Route::post('orders/{id}/archive', [OrderController::class, 'archive']);
        Route::delete('orders/{id}', [OrderController::class, 'destroy']);
        Route::get('orders/{id}/receipt', [ReceiptController::class, 'show']);
        Route::get('orders/{id}/checker', [CheckerController::class, 'show']);

        // Open Bills
        Route::get('open-bills', [OpenBillController::class, 'index']);
        Route::post('open-bills', [OpenBillController::class, 'store']);
        Route::delete('open-bills/{cartId}', [OpenBillController::class, 'destroy']);

        // History
        Route::get('history', [HistoryController::class, 'index']);
        Route::get('history/export', [HistoryController::class, 'export']);
        Route::get('history/{id}', [HistoryController::class, 'show']);

        // Settlement
        Route::get('settlements', [SettlementController::class, 'index']);
        Route::get('settlements/active', [SettlementController::class, 'active']);
        Route::post('settlements/start', [SettlementController::class, 'start']);
        Route::post('settlements/close', [SettlementController::class, 'close']);
        Route::get('settlements/{id}', [SettlementController::class, 'show']);
        Route::delete('settlements/{id}', [SettlementController::class, 'destroy']);
    });
});
