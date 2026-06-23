<?php

use App\Http\Controllers\Api\v1\Auth\GoogleAuthController;
use App\Http\Controllers\Api\v1\Auth\LoginController;
use App\Http\Controllers\Api\v1\Auth\LogoutController;
use App\Http\Controllers\Api\v1\Auth\RegisterController;
use App\Http\Controllers\Api\v1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\v1\Auth\SendOtpController;
use App\Http\Controllers\Api\v1\Auth\VerifyOtpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy.',
        'data' => [
            'speed' => round((microtime(true) * 1000) - (request()->server->get('REQUEST_TIME_FLOAT') * 1000), 2) . ' ms',
        ],
    ]);
})->name('api.health');

Route::prefix('v1')->middleware('check-api-key')->name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', LoginController::class)->middleware('throttle:5,1')->name('login');
        Route::post('register', RegisterController::class)->middleware('throttle:5,1')->name('register');

        Route::controller(GoogleAuthController::class)->prefix('google')->name('google.')->middleware('throttle:10,1')->group(function () {
            Route::post('/', 'loginWithToken')->name('login');
            Route::get('redirect', 'redirect')->name('redirect');
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('logout', LogoutController::class)->name('logout');
            Route::post('reset-password', ResetPasswordController::class)->name('reset_password');
        });
    });

    Route::prefix('otp')->name('otp.')->group(function () {
        Route::post('send', SendOtpController::class)->middleware('throttle:5,1')->name('send');
        Route::post('verify', VerifyOtpController::class)->middleware('throttle:5,1')->name('verify');
    });

    Route::get('test-if-logged-in', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'You are logged in.',
            'data' => [
                'user' => $request->user(),
            ],
        ]);
    })->middleware('auth:sanctum')->name('auth.check');
});
