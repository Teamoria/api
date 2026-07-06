<?php

use App\Enums\FileCategory;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Enums\UploadVisibility;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

it('removes stored upload files when an administrator permanently deletes a user', function () {
    Storage::fake('local');
    $administrator = User::factory()->create([
        'company_id' => null,
        'role' => UserRole::ADMIN,
    ]);
    $user = User::factory()->create();
    $filePath = "uploads/{$user->company_id}/personal/documents/user-file.pdf";
    Storage::disk('local')->put($filePath, 'user file');
    $upload = Upload::query()->create([
        'company_id' => $user->company_id,
        'user_id' => $user->id,
        'scope' => UploadScope::PERSONAL,
        'visibility' => UploadVisibility::PRIVATE,
        'file_path' => $filePath,
        'file_name' => 'user-file.pdf',
        'file_type' => 'application/pdf',
        'category' => FileCategory::DOCUMENT,
        'file_size' => 9,
        'status' => UploadStatus::SUCCESS,
        'upload_date' => now(),
    ]);

    Sanctum::actingAs($administrator);

    $this->deleteJson(
        route('api.v1.admin.users.force-delete', $user),
        [],
        ['x-api-key' => 'test-api-key'],
    )->assertOk();

    Storage::disk('local')->assertMissing($filePath);

    expect(User::withTrashed()->find($user->id))->toBeNull()
        ->and(Upload::query()->find($upload->id))->toBeNull();
});
