<?php

use App\Http\Controllers\Api\v1\Auth\GoogleAuthController;
use App\Http\Controllers\Api\v1\Auth\LoginController;
use App\Http\Controllers\Api\v1\Auth\LogoutController;
use App\Http\Controllers\Api\v1\Auth\RegisterController;
use App\Http\Controllers\Api\v1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\v1\Auth\SendOtpController;
use App\Http\Controllers\Api\v1\Auth\VerifyOtpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy.',
        'data' => [
            'speed' => round((microtime(true) * 1000) - (request()->server->get('REQUEST_TIME_FLOAT') * 1000), 2).' ms',
        ],
    ]);
});

Route::middleware('check-api-key')->prefix('v1')->group(function () {
    Route::post('auth/login', LoginController::class)->middleware('throttle:5,1');
    Route::post('auth/register', RegisterController::class)->middleware('throttle:5,1');
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:10,1');
    Route::post('otp/send', SendOtpController::class)->middleware('throttle:5,1');
    Route::post('otp/verify', VerifyOtpController::class)->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/logout', LogoutController::class);
        Route::post('auth/reset-password', ResetPasswordController::class);

        Route::get('test-if-logged-in', function () {
            return response()->json([
                'success' => true,
                'message' => 'You are logged in.',
                'user' => Auth::user(),
            ]);
        });
    });
});
