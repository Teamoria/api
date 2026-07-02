<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('allows an administrator to move a user to another existing company', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);
    $user = User::factory()->create();
    $newCompany = Company::factory()->create();

    Sanctum::actingAs($administrator);

    $this->putJson(route('api.v1.admin.users.update', $user), [
        'email' => $user->email,
        'company_id' => (string) $newCompany->id,
    ], ['x-api-key' => 'test-api-key'])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id);

    expect($user->refresh()->company_id)->toBe($newCompany->id);
});
