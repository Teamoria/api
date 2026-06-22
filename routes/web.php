<?php

use App\Http\Controllers\Api\v1\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleCallback']);
