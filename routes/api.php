<?php

use App\Http\Controllers\Api\v1\Auth\GoogleAuthController;
use App\Http\Controllers\Api\v1\Auth\LoginController;
use App\Http\Controllers\Api\v1\Auth\LogoutController;
use App\Http\Controllers\Api\v1\Auth\RegisterController;
use App\Http\Controllers\Api\v1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\v1\Auth\SendOtpController;
use App\Http\Controllers\Api\v1\Auth\VerifyOtpController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\UserController;
use App\UserRole;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy.',
        'data' => [
            'speed' => round((microtime(true) * 1000) - (request()->server->get('REQUEST_TIME_FLOAT') * 1000), 2).' ms',
        ],
    ]);
})->name('api.health');

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->middleware('check-api-key')->name('api.v1.')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Authentication
    |----------------------------------------------------------------------
    */

    Route::prefix('auth')->name('auth.')->group(function () {

        // Guest auth routes (throttled)
        Route::post('login', LoginController::class)->middleware('throttle:5,1')->name('login');
        Route::post('register', RegisterController::class)->middleware('throttle:5,1')->name('register');

        // Google OAuth
        Route::controller(GoogleAuthController::class)
            ->prefix('google')
            ->name('google.')
            ->middleware('throttle:10,1')
            ->group(function () {
                Route::post('/', 'loginWithToken')->name('login');
                Route::get('redirect', 'redirect')->name('redirect');
            });

        // Authenticated auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('logout', LogoutController::class)->name('logout');
            Route::post('reset-password', ResetPasswordController::class)->name('reset_password');
        });
    });

    /*
    |----------------------------------------------------------------------
    | OTP (One-Time Password)
    |----------------------------------------------------------------------
    */

    Route::prefix('otp')->name('otp.')->group(function () {
        Route::post('send', SendOtpController::class)->middleware('throttle:5,1')->name('send');
        Route::post('verify', VerifyOtpController::class)->middleware('throttle:5,1')->name('verify');
    });

    /*
    |----------------------------------------------------------------------
    | Authenticated Routes
    |----------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        /*
        |------------------------------------------------------------------
        | Admin — User Management
        |------------------------------------------------------------------
        */

        Route::prefix('users')
            ->name('users.')
            ->controller(UserController::class)
            ->middleware(['checkRole:'.UserRole::ADMIN->value])
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{User}', 'show')->name('show');
                Route::put('/{user}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
                Route::patch('/{id}/restore', 'restore')->name('restore');
                Route::delete('/{id}/force-delete', 'forceDelete')->name('force-delete');
            });

        /*
        |------------------------------------------------------------------
        | Admin — Company Management
        |------------------------------------------------------------------
        */

        Route::prefix('companies')
            ->name('companies.')
            ->controller(CompanyController::class)
            ->middleware(['checkRole:'.UserRole::ADMIN->value])
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{Company}', 'show')->name('show');
                Route::put('/{id}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
                Route::patch('/{id}/restore', 'restore')->name('restore');
                Route::delete('/{id}/force-delete', 'forceDelete')->name('force-delete');
            });

        /*
        |------------------------------------------------------------------
        | Company Owner — Staff Management (Managers & Members)
        |------------------------------------------------------------------
        */

        Route::prefix('staff')
            ->name('staff.')
            ->middleware(['checkRole:'.UserRole::COMPANY_OWNER->value])
            ->controller(StaffController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}', 'show')->name('show');
                Route::put('/{id}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
                Route::patch('/{id}/restore', 'restore')->name('restore');
                Route::delete('/{id}/force-delete', 'forceDelete')->name('force-delete');
            });

        /*
        |------------------------------------------------------------------
        | Profile
        |------------------------------------------------------------------
        */

        Route::controller(ProfileController::class)
            ->prefix('profile')
            ->name('profile.')
            ->group(function () {
                Route::get('/', 'show')->name('show');
                Route::patch('/', 'update')->name('update');
            });
    });
});
