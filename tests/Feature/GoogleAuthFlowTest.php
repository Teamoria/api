<?php

use Laravel\Socialite\Facades\Socialite;

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
