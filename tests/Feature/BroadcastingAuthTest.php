<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'test-app');

    Broadcast::purge('reverb');
    require base_path('routes/channels.php');
});

it('authorizes users to subscribe to their private chat channel using sanctum', function () {
    $user = User::factory()->create();
    $token = $user->createToken('broadcast-test')->plainTextToken;

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-chat.'.$user->id,
    ], [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonStructure(['auth']);
});

it('prevents users from subscribing to another users private chat channel', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('broadcast-test')->plainTextToken;

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-chat.'.$otherUser->id,
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertForbidden();
});
