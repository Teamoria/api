<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('redirects to Google OAuth provider', function () {
    $provider = Mockery::mock();
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('redirect')->once()->andReturnSelf();
    $provider->shouldReceive('getTargetUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/v2/auth?client_id=test-client-id');

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);

    $response = $this->getJson('/api/v1/auth/google/redirect', ['x-api-key' => 'test-api-key']);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Redirect to Google to authenticate.')
        ->assertJsonPath('data.redirect_url', 'https://accounts.google.com/o/oauth2/v2/auth?client_id=test-client-id');
});

it('handles Google callback and creates a new user', function () {
    $googleUser = (new SocialiteUser)->map([
        'id' => 'google-999',
        'name' => 'Callback User',
        'email' => 'callback@example.com',
    ]);

    $provider = Mockery::mock();
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('user')->once()->andReturn($googleUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);

    $response = $this->getJson('/auth/google/callback');

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logged in successfully via Google.')
        ->assertJsonStructure(['data' => ['token']]);

    $user = User::query()->where('email', 'callback@example.com')->firstOrFail();

    expect($user->google_id)->toBe('google-999')
        ->and($user->name)->toBe('Callback User')
        ->and($user->tokens)->toHaveCount(1);
});

it('handles Google callback exception', function () {
    $provider = Mockery::mock();
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('user')->once()->andThrow(new Exception('Invalid state.'));

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);

    $response = $this->getJson('/auth/google/callback');

    $response
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Google authentication failed.')
        ->assertJsonPath('data.error', 'Invalid state.');
});
