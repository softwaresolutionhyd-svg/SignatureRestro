<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CloudSyncController;
use App\Http\Controllers\Api\OrderTakerApiController;
use App\Http\Controllers\Api\ServerConfigController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Order Taker + Admin mobile apps (Flutter) use these JSON endpoints.
|
*/

Route::middleware('sync.token')->prefix('sync')->group(function () {
    Route::get('/ping', [CloudSyncController::class, 'ping']);
    Route::post('/push', [CloudSyncController::class, 'push']);
    Route::get('/pull', [CloudSyncController::class, 'pull']);
    Route::post('/pull-multi', [CloudSyncController::class, 'pullMulti']);
    Route::post('/pull-ids', [CloudSyncController::class, 'pullIds']);
    Route::post('/mirror', [CloudSyncController::class, 'mirror']);
    Route::post('/push-notify', [CloudSyncController::class, 'pushNotify']);
});

Route::get('/server-config', [ServerConfigController::class, 'show']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'tenant', 'apiCompany', 'companyTenantReady'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('order-taker')
        ->middleware('apiOrderTaker')
        ->group(function () {
            Route::get('/bootstrap', [OrderTakerApiController::class, 'bootstrap']);
            Route::get('/pending', [OrderTakerApiController::class, 'pending']);
            Route::get('/orders/{order}', [OrderTakerApiController::class, 'show']);
            Route::post('/orders', [OrderTakerApiController::class, 'store']);
            Route::put('/orders/{order}', [OrderTakerApiController::class, 'update']);
        });

    Route::prefix('admin')
        ->middleware('apiAdmin')
        ->group(function () {
            Route::get('/dashboard', [AdminApiController::class, 'dashboard']);
            Route::get('/orders/pending', [AdminApiController::class, 'pendingOrders']);
            Route::get('/orders/paid', [AdminApiController::class, 'paidOrders']);
            Route::get('/kitchen-voids', [AdminApiController::class, 'kitchenVoids']);
            Route::get('/expenses', [AdminApiController::class, 'expenses']);
            Route::get('/low-stock', [AdminApiController::class, 'lowStock']);
            Route::get('/attendance', [AdminApiController::class, 'attendanceToday']);
        });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
