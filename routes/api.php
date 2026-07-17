<?php

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
| Order Taker mobile app (Flutter) uses these JSON endpoints.
|
*/

Route::middleware('sync.token')->prefix('sync')->group(function () {
    Route::get('/ping', [CloudSyncController::class, 'ping']);
    Route::post('/push', [CloudSyncController::class, 'push']);
    Route::get('/pull', [CloudSyncController::class, 'pull']);
    Route::post('/pull-ids', [CloudSyncController::class, 'pullIds']);
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
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
