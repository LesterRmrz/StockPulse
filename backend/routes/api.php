<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
 * Everything here is served under /api (e.g. POST /api/login).
 * "throttle:login" uses the rate limiter defined in AppServiceProvider.
 */

Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/demo-login', [AuthController::class, 'demoLogin']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
