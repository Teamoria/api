<?php

use App\Enums\FileCategory;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Enums\UploadVisibility;
use App\Enums\UserRole;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

it('removes stored upload files when a company owner permanently deletes staff', function () {
    Storage::fake('local');
    $owner = User::factory()->create([
        'role' => UserRole::COMPANY_OWNER,
    ]);
    $staff = User::factory()->for($owner->company)->create([
        'role' => UserRole::COMPANY_MEMBER,
    ]);
    $filePath = "uploads/{$owner->company_id}/personal/documents/staff-file.pdf";
    Storage::disk('local')->put($filePath, 'staff file');
    $upload = Upload::query()->create([
        'company_id' => $owner->company_id,
        'user_id' => $staff->id,
        'scope' => UploadScope::PERSONAL,
        'visibility' => UploadVisibility::PRIVATE,
        'file_path' => $filePath,
        'file_name' => 'staff-file.pdf',
        'file_type' => 'application/pdf',
        'category' => FileCategory::DOCUMENT,
        'file_size' => 10,
        'status' => UploadStatus::SUCCESS,
        'upload_date' => now(),
    ]);

    Sanctum::actingAs($owner);

    $this->deleteJson(
        route('api.v1.company.staff.force-delete', $staff),
        [],
        companyRequirementApiHeaders(),
    )->assertOk();

    Storage::disk('local')->assertMissing($filePath);

    expect(User::withTrashed()->find($staff->id))->toBeNull()
        ->and(Upload::query()->find($upload->id))->toBeNull();
});

function companyRequirementApiHeaders(): array
{
    return ['x-api-key' => 'test-api-key'];
}
