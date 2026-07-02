<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('prevents a user without a company from accessing protected endpoints', function () {
    $user = User::factory()->create([
        'company_id' => null,
        'role' => UserRole::COMPANY_OWNER,
    ]);

    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.company.profile.show'), companyRequirementApiHeaders())
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'message' => 'You must be assigned to a company to perform this action.',
            'error_code' => 'FORBIDDEN',
        ]);
});

it('allows a user with a company to access protected endpoints', function () {
    $user = User::factory()->create([
        'role' => UserRole::COMPANY_OWNER,
    ]);

    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.company.profile.show'), companyRequirementApiHeaders())
        ->assertSuccessful();
});

it('allows an administrator without a company to access protected endpoints', function () {
    $admin = User::factory()->create([
        'company_id' => null,
        'role' => UserRole::ADMIN,
    ]);

    Sanctum::actingAs($admin);

    $this->getJson(route('api.v1.admin.companies.index'), companyRequirementApiHeaders())
        ->assertSuccessful();
});

it('allows a company owner to register their first company', function () {
    $owner = User::factory()->create([
        'company_id' => null,
        'role' => UserRole::COMPANY_OWNER,
    ]);

    Sanctum::actingAs($owner);

    $this->postJson(route('api.v1.company.register'), [
        'name' => 'Teamoria',
    ], companyRequirementApiHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Teamoria');

    expect($owner->refresh()->company_id)->not->toBeNull();
});

function companyRequirementApiHeaders(): array
{
    return ['x-api-key' => 'test-api-key'];
}
