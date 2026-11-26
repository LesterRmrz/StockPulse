<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductMovementController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockLevelController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
 * Everything here is served under /api (e.g. POST /api/login).
 * "throttle:login" uses the rate limiter defined in AppServiceProvider.
 * List them all with: php artisan route:list --path=api
 */

Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/demo-login', [AuthController::class, 'demoLogin']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Catalog. apiResource = index, store, show, update, destroy in one line.
    // Who may write is decided by the policies in app/Policies.
    Route::apiResource('products', ProductController::class);
    Route::get('/products/{product}/movements', ProductMovementController::class);
    // withTrashed(): {product} normally can't find a soft-deleted product (404); here it must
    Route::post('/products/{product}/restore', [ProductController::class, 'restore'])->withTrashed();
    Route::apiResource('categories', CategoryController::class)->except('show');
    Route::apiResource('suppliers', SupplierController::class)->except('show');
    Route::apiResource('warehouses', WarehouseController::class)->only(['index', 'store', 'update']);

    // Inventory. Quantities only change through InventoryService.
    Route::get('/stock-levels', [StockLevelController::class, 'index']);
    Route::patch('/stock-levels/{stockLevel}', [StockLevelController::class, 'update']);
    Route::post('/stock-adjustments', StockAdjustmentController::class);
    Route::apiResource('stock-transfers', StockTransferController::class)->only(['index', 'show', 'store']);
});
