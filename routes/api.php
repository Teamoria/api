<?php

use App\Http\Controllers\Api\v1\Auth\LoginController;
use App\Http\Controllers\Api\v1\Auth\LogoutController;
use App\Http\Controllers\api\v1\Auth\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy.',
        'data' => [],
    ]);
});

Route::middleware('check.api.key')->prefix('v1')->group(function () {
    Route::post('auth/login', LoginController::class)->throttle(5, 1);
    Route::post('auth/register', RegisterController::class)->throttle(5, 1);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/logout', LogoutController::class);

        Route::get('test-if-logged-in', function () {
            return response()->json([
                'success' => true,
                'message' => 'You are logged in.',
                'user' => Auth::user(),
            ]);
        });
    });
});
