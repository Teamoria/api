<?php

use App\Enums\FileCategory;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Enums\UploadVisibility;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Project;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('prevents company users from accessing administration routes', function () {
    $owner = User::factory()->create([
        'role' => UserRole::COMPANY_OWNER,
    ]);

    Sanctum::actingAs($owner);

    $this->getJson(route('api.v1.admin.users.index'), routeSeparationApiHeaders())
        ->assertForbidden();
});

it('allows an administrator without a company to view every project', function () {
    $administrator = User::factory()->create([
        'company_id' => null,
        'role' => UserRole::ADMIN,
    ]);
    $firstCompany = Company::factory()->create();
    $secondCompany = Company::factory()->create();

    Project::query()->create([
        'company_id' => $firstCompany->id,
        'name' => 'First project',
        'description' => 'First project description',
        'status' => ProjectStatus::ACTIVE,
        'progress' => 10,
    ]);
    Project::query()->create([
        'company_id' => $secondCompany->id,
        'name' => 'Second project',
        'description' => 'Second project description',
        'status' => ProjectStatus::PENDING,
        'progress' => 0,
    ]);

    Sanctum::actingAs($administrator);

    $this->getJson(route('api.v1.admin.projects.index'), routeSeparationApiHeaders())
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.projects');
});

it('allows an administrator to update any project without project membership', function () {
    $administrator = User::factory()->create([
        'company_id' => null,
        'role' => UserRole::ADMIN,
    ]);
    $project = Project::query()->create([
        'company_id' => Company::factory()->create()->id,
        'name' => 'Original project',
        'description' => 'Project description',
        'status' => ProjectStatus::ACTIVE,
        'progress' => 0,
    ]);

    Sanctum::actingAs($administrator);

    $this->putJson(route('api.v1.admin.projects.update', $project), [
        'name' => 'Administrator updated project',
    ], routeSeparationApiHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Administrator updated project');
});

it('allows an administrator to create a project and assign its manager', function () {
    $administrator = User::factory()->create([
        'company_id' => null,
        'role' => UserRole::ADMIN,
    ]);
    $company = Company::factory()->create();
    $manager = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);

    Sanctum::actingAs($administrator);

    $projectId = $this->postJson(route('api.v1.admin.projects.store'), [
        'company_id' => $company->id,
        'name' => 'Administrator project',
        'description' => 'Project created by a platform administrator.',
        'status' => ProjectStatus::ACTIVE->value,
        'progress' => 0,
        'start_date' => '2026-07-01',
        'end_date' => '2026-08-01',
    ], routeSeparationApiHeaders())
        ->assertCreated()
        ->json('data.id');

    $this->postJson(route('api.v1.admin.projects.members.add', $projectId), [
        'user_ids' => [$manager->id],
        'role' => ProjectRole::MANAGER->value,
    ], routeSeparationApiHeaders())
        ->assertSuccessful();

    $this->assertDatabaseHas('project_user', [
        'project_id' => $projectId,
        'user_id' => $manager->id,
        'role' => ProjectRole::MANAGER->value,
    ]);
});

it('assigns a company project creator as its manager', function () {
    $owner = User::factory()->create([
        'role' => UserRole::COMPANY_OWNER,
    ]);
    grantActiveSubscription($owner->company);

    Sanctum::actingAs($owner);

    $response = $this->postJson(route('api.v1.company.projects.store'), [
        'name' => 'Owner project',
        'description' => 'Project created by its company owner.',
        'status' => ProjectStatus::ACTIVE->value,
        'progress' => 0,
        'start_date' => '2026-07-01',
        'end_date' => '2026-08-01',
    ], routeSeparationApiHeaders())
        ->assertCreated();

    $this->assertDatabaseHas('project_user', [
        'project_id' => $response->json('data.id'),
        'user_id' => $owner->id,
        'role' => ProjectRole::MANAGER->value,
    ]);
});

it('keeps company project routes scoped to the authenticated company', function () {
    $owner = User::factory()->create([
        'role' => UserRole::COMPANY_OWNER,
    ]);
    $otherCompanyProject = Project::query()->create([
        'company_id' => Company::factory()->create()->id,
        'name' => 'Other company project',
        'description' => 'This project belongs to a different company.',
        'status' => ProjectStatus::ACTIVE,
        'progress' => 0,
    ]);

    Sanctum::actingAs($owner);

    $this->getJson(
        route('api.v1.company.projects.show', $otherCompanyProject),
        routeSeparationApiHeaders(),
    )->assertNotFound();
});

it('removes stored upload files when an administrator permanently deletes a company', function () {
    Storage::fake('local');
    $administrator = User::factory()->create([
        'company_id' => null,
        'role' => UserRole::ADMIN,
    ]);
    $company = Company::factory()->create();
    $uploader = User::factory()->for($company)->create();
    $filePath = "uploads/{$company->id}/company/documents/company-file.pdf";
    Storage::disk('local')->put($filePath, 'company file');
    $upload = Upload::query()->create([
        'company_id' => $company->id,
        'user_id' => $uploader->id,
        'scope' => UploadScope::COMPANY,
        'visibility' => UploadVisibility::PRIVATE,
        'file_path' => $filePath,
        'file_name' => 'company-file.pdf',
        'file_type' => 'application/pdf',
        'category' => FileCategory::DOCUMENT,
        'file_size' => 12,
        'status' => UploadStatus::SUCCESS,
        'upload_date' => now(),
    ]);

    Sanctum::actingAs($administrator);

    $this->deleteJson(
        route('api.v1.admin.companies.force-delete', $company),
        [],
        routeSeparationApiHeaders(),
    )->assertOk();

    Storage::disk('local')->assertMissing($filePath);

    expect(Company::withTrashed()->find($company->id))->toBeNull()
        ->and(Upload::query()->find($upload->id))->toBeNull();
});

function routeSeparationApiHeaders(): array
{
    return ['x-api-key' => 'test-api-key'];
}
