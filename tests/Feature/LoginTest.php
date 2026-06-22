<?php

use App\Models\User;
use App\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('asks a registered user to verify their email before logging in', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'pending@gmail.com',
        'password' => Hash::make('password'),
        'status' => UserStatus::PENDING->value,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ], ['x-api-key' => 'test-api-key'])
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'message' => 'Your account is registered but your email address is not verified. Please verify your email before logging in.',
            'error_code' => 'EMAIL_NOT_VERIFIED',
            'data' => [],
        ]);

    expect($user->tokens)->toHaveCount(0);
});

it('allows an active verified user to log in', function () {
    $user = User::factory()->create([
        'email' => 'active@gmail.com',
        'password' => Hash::make('password'),
        'status' => UserStatus::ACTIVE->value,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ], ['x-api-key' => 'test-api-key'])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logged in successfully.')
        ->assertJsonStructure(['data' => ['token']]);

    expect($user->tokens)->toHaveCount(1);
});
