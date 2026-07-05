<?php

use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\Auth\GoogleAuthController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SendOtpController;
use App\Http\Controllers\Api\V1\Auth\VerifyOtpController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\UserController;
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
            Route::post('logout', LogoutController::class)->name('logout');
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('uploads')
            ->name('uploads.')
            ->controller(UploadController::class)
            ->group(function () {
                Route::post('/', 'upload')->middleware('throttle:10,1')->name('store');
                Route::get('/', 'index')->name('index');
                Route::get('/list', 'index')->name('list');
                Route::get('/mine', 'mine')->name('mine');
                Route::get('/{project}/list', 'listUploadedFiles')->name('list.company');
                Route::get('/{upload}/download', 'download')->name('download');
                Route::post('/{upload}/permissions', 'grantAccess')->name('permissions.store');
                Route::delete('/{upload}/permissions/{user}', 'revokeAccess')->name('permissions.destroy');
                Route::get('/{upload}', 'show')->name('show');
                Route::delete('/{upload}', 'destroy')->name('destroy');
            });

        /*
        |------------------------------------------------------------------
        | Platform Administration
        |------------------------------------------------------------------
        */

        Route::prefix('admin')
            ->name('admin.')
            ->middleware('role:'.UserRole::ADMIN->value)
            ->group(function () {
                Route::prefix('users')->name('users.')->controller(UserController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{id}', 'show')->name('show');
                    Route::put('/{id}', 'update')->name('update');
                    Route::delete('/{id}', 'destroy')->name('destroy');
                    Route::patch('/{id}/restore', 'restore')->name('restore');
                    Route::delete('/{id}/force-delete', 'forceDelete')->name('force-delete');
                });

                Route::prefix('companies')->name('companies.')->controller(CompanyController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{id}', 'show')->name('show');
                    Route::put('/{id}', 'update')->name('update');
                    Route::delete('/{id}', 'destroy')->name('destroy');
                    Route::patch('/{id}/restore', 'restore')->name('restore');
                    Route::delete('/{id}/force-delete', 'forceDelete')->name('force-delete');
                });

                Route::prefix('projects')->name('projects.')->controller(ProjectController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{id}', 'show')->name('show');
                    Route::put('/{id}', 'update')->name('update');
                    Route::delete('/{id}', 'destroy')->name('destroy');
                    Route::patch('/{id}/restore', 'restore')->name('restore');
                    Route::delete('/{id}/force-delete', 'forceDelete')->name('force-delete');
                    Route::post('/{id}/members', 'addMembers')->name('members.add');
                    Route::delete('/{id}/members/{userId}', 'removeMember')->name('members.remove');
                });

                Route::prefix('tasks')->name('tasks.')->controller(TaskController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{id}', 'show')->name('show');
                    Route::put('/{id}', 'update')->name('update');
                    Route::delete('/{id}', 'destroy')->name('destroy');
                    Route::patch('/{id}/restore', 'restore')->name('restore');
                    Route::delete('/{id}/force-delete', 'forceDelete')->name('force-delete');
                    Route::post('/{id}/assignees', 'addAssignees')->name('assignees.add');
                    Route::delete('/{id}/assignees/{userId}', 'removeAssignee')->name('assignees.remove');
                    Route::post('/{id}/dependencies', 'addDependencies')->name('dependencies.add');
                    Route::delete('/{id}/dependencies/{dependencyId}', 'removeDependency')->name('dependencies.remove');
                    Route::post('/{id}/notes', 'addNote')->name('notes.add');
                    Route::delete('/{id}/notes/{noteId}', 'removeNote')->name('notes.remove');
                });

                Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
                    Route::get('/', 'show')->name('show');
                    Route::patch('/', 'update')->name('update');
                });
            });

        /*
        |------------------------------------------------------------------
        | Company Workspace
        |------------------------------------------------------------------
        */

        Route::prefix('company')->name('company.')->group(function () {
            Route::post('register', [RegisterController::class, 'registerCompany'])
                ->middleware('role:'.UserRole::COMPANY_OWNER->value)
                ->name('register');

            Route::middleware('check-company')->group(function () {
                Route::prefix('staff')
                    ->name('staff.')
                    ->middleware('role:'.UserRole::COMPANY_OWNER->value)
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

                Route::prefix('projects')->name('projects.')->controller(ProjectController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{id}', 'show')->name('show');
                    Route::put('/{id}', 'update')->name('update');
                    Route::delete('/{id}', 'destroy')->name('destroy');
                    Route::patch('/{id}/restore', 'restore')->name('restore');
                    Route::delete('/{id}/force-delete', 'forceDelete')->name('force-delete');
                    Route::post('/{id}/members', 'addMembers')->name('members.add');
                    Route::delete('/{id}/members/{userId}', 'removeMember')->name('members.remove');
                });

                Route::prefix('tasks')->name('tasks.')->controller(TaskController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{id}', 'show')->name('show');
                    Route::put('/{id}', 'update')->name('update');
                    Route::delete('/{id}', 'destroy')->name('destroy');
                    Route::patch('/{id}/restore', 'restore')->name('restore');
                    Route::delete('/{id}/force-delete', 'forceDelete')->name('force-delete');
                    Route::post('/{id}/assignees', 'addAssignees')->name('assignees.add');
                    Route::delete('/{id}/assignees/{userId}', 'removeAssignee')->name('assignees.remove');
                    Route::post('/{id}/dependencies', 'addDependencies')->name('dependencies.add');
                    Route::delete('/{id}/dependencies/{dependencyId}', 'removeDependency')->name('dependencies.remove');
                    Route::post('/{id}/notes', 'addNote')->name('notes.add');
                    Route::delete('/{id}/notes/{noteId}', 'removeNote')->name('notes.remove');
                });

                Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
                    Route::get('/', 'show')->name('show');
                    Route::patch('/', 'update')->name('update');
                });
            });
        });
    });
});
