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

it('authenticates a new user with a Google access token', function () {
    $googleUser = SocialiteUser::fake([
        'id' => 'google-123',
        'name' => 'Google User',
        'email' => 'google@example.com',
    ]);

    mockGoogleProvider('valid-google-token', $googleUser);

    $response = $this->postJson('/api/v1/auth/google', [
        'provider_token' => 'valid-google-token',
    ], apiHeaders());

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Authenticated successfully.')
        ->assertJsonPath('data.user.name', 'Google User')
        ->assertJsonPath('data.user.email', 'google@example.com')
        ->assertJsonPath('data.user.google_id', 'google-123')
        ->assertJsonStructure(['data' => ['user', 'token']]);

    $user = User::query()->where('email', 'google@example.com')->firstOrFail();

    expect($user->google_id)->toBe('google-123')
        ->and(Hash::check('valid-google-token', $user->password))->toBeFalse()
        ->and($user->tokens)->toHaveCount(1);
});

it('links Google to an existing password account', function () {
    $user = User::factory()->create([
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'google_id' => null,
    ]);
    $existingPassword = $user->password;

    $googleUser = SocialiteUser::fake([
        'id' => 'google-456',
        'name' => 'Different Google Name',
        'email' => 'existing@example.com',
    ]);

    mockGoogleProvider('valid-google-token', $googleUser);

    $response = $this->postJson('/api/v1/auth/google', [
        'provider_token' => 'valid-google-token',
    ], apiHeaders());

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.google_id', 'google-456');

    $user->refresh();

    expect($user->name)->toBe('Existing User')
        ->and($user->password)->toBe($existingPassword)
        ->and($user->google_id)->toBe('google-456')
        ->and($user->tokens)->toHaveCount(1);
});

it('requires a provider token', function () {
    $this->postJson('/api/v1/auth/google', [], apiHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.provider_token.0', 'The provider token field is required.');
});

it('rejects an invalid Google access token', function () {
    mockGoogleProviderException('invalid-google-token');

    $this->postJson('/api/v1/auth/google', [
        'provider_token' => 'invalid-google-token',
    ], apiHeaders())
        ->assertUnauthorized()
        ->assertJson([
            'success' => false,
            'message' => 'Google authentication failed. The provided token is invalid or expired.',
            'data' => [],
        ]);
});

it('rejects a Google identity that conflicts with an existing link', function () {
    User::factory()->create([
        'email' => 'existing@example.com',
        'google_id' => 'google-original',
    ]);

    $googleUser = SocialiteUser::fake([
        'id' => 'google-different',
        'email' => 'existing@example.com',
    ]);

    mockGoogleProvider('valid-google-token', $googleUser);

    $this->postJson('/api/v1/auth/google', [
        'provider_token' => 'valid-google-token',
    ], apiHeaders())
        ->assertUnauthorized();

    expect(User::query()->where('email', 'existing@example.com')->value('google_id'))
        ->toBe('google-original');
});

function mockGoogleProvider(string $providerToken, SocialiteUser $googleUser): void
{
    $provider = Mockery::mock();
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('userFromToken')->once()->with($providerToken)->andReturn($googleUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);
}

function mockGoogleProviderException(string $providerToken): void
{
    $provider = Mockery::mock();
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('userFromToken')
        ->once()
        ->with($providerToken)
        ->andThrow(new RuntimeException('Invalid token.'));

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);
}

/** @return array<string, string> */
function apiHeaders(): array
{
    return ['x-api-key' => 'test-api-key'];
}
