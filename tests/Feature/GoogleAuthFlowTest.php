<?php

use App\Enums\UserStatus;
use App\Http\Controllers\Api\V1\Auth\GoogleAuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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

it('authenticates a new active user from the Google callback', function () {
    $googleUser = (new SocialiteUser)
        ->setRaw(['email_verified' => true])
        ->map([
            'id' => 'google-callback-123',
            'name' => 'Callback User',
            'email' => 'callback@example.com',
        ]);

    mockGoogleCallbackProvider($googleUser);

    $response = TestResponse::fromBaseResponse(app(GoogleAuthController::class)->handleCallback());

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logged in successfully via Google.')
        ->assertJsonStructure(['data' => ['token']]);

    $user = User::query()->where('email', 'callback@example.com')->firstOrFail();

    expect($user->google_id)->toBe('google-callback-123')
        ->and($user->status)->toBe(UserStatus::ACTIVE)
        ->and($user->tokens)->toHaveCount(1);
});

it('rejects a conflicting Google identity from the callback', function () {
    $user = User::factory()->create([
        'email' => 'callback-conflict@example.com',
        'google_id' => 'google-original',
    ]);

    $googleUser = (new SocialiteUser)
        ->setRaw(['email_verified' => true])
        ->map([
            'id' => 'google-different',
            'email' => 'callback-conflict@example.com',
        ]);

    mockGoogleCallbackProvider($googleUser);

    $response = TestResponse::fromBaseResponse(app(GoogleAuthController::class)->handleCallback());

    $response->assertUnauthorized();

    $user->refresh();

    expect($user->google_id)->toBe('google-original')
        ->and($user->tokens)->toHaveCount(0);
});

function mockGoogleCallbackProvider(SocialiteUser $googleUser): void
{
    $provider = Mockery::mock();
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('user')->once()->andReturn($googleUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);
}
