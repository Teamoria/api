<?php

use App\Enums\ProjectRole;
use App\Enums\UploadAccessLevel;
use App\Enums\UploadScope;
use App\Enums\UploadVisibility;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
    Storage::fake('local');
});

it('keeps personal files private and lists only the files uploaded by the user', function () {
    $company = Company::factory()->create();
    $uploader = User::factory()->for($company)->create();
    $colleague = User::factory()->for($company)->create();

    Sanctum::actingAs($uploader);

    $uploadResponse = $this->post(route('api.v1.uploads.store'), [
        'files' => [File::create('contract.pdf')->mimeType('application/pdf')],
        'scope' => UploadScope::PERSONAL->value,
    ], accessApiHeaders())
        ->assertCreated()
        ->assertJsonPath('data.files.0.scope', UploadScope::PERSONAL->value)
        ->assertJsonPath('data.files.0.visibility', UploadVisibility::PRIVATE->value);

    $upload = Upload::query()->findOrFail($uploadResponse->json('data.files.0.id'));

    $this->getJson(route('api.v1.uploads.mine'), accessApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data.files')
        ->assertJsonPath('data.files.0.id', $upload->id);

    Sanctum::actingAs($colleague);

    $this->getJson(route('api.v1.uploads.index'), accessApiHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'data.files');

    $this->getJson(route('api.v1.uploads.show', $upload), accessApiHeaders())
        ->assertForbidden();

    $this->get(route('api.v1.uploads.download', $upload), accessApiHeaders())
        ->assertForbidden();
});

it('shows member-visible project files only to project members', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);
    $member = User::factory()->for($company)->create();
    $nonMember = User::factory()->for($company)->create();
    $project = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Project files',
    ]);
    $project->users()->attach([
        $manager->id => ['role' => ProjectRole::MANAGER->value],
        $member->id => ['role' => ProjectRole::MEMBER->value],
    ]);

    Sanctum::actingAs($manager);

    $uploadResponse = $this->post(route('api.v1.uploads.store'), [
        'files' => [File::create('report.pdf')->mimeType('application/pdf')],
        'scope' => UploadScope::PROJECT->value,
        'visibility' => UploadVisibility::MEMBERS->value,
        'project_id' => $project->id,
    ], accessApiHeaders())->assertCreated();

    $uploadId = $uploadResponse->json('data.files.0.id');

    Sanctum::actingAs($member);

    $this->getJson(route('api.v1.uploads.index'), accessApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data.files')
        ->assertJsonPath('data.files.0.id', $uploadId);

    Sanctum::actingAs($nonMember);

    $this->getJson(route('api.v1.uploads.index'), accessApiHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'data.files');

    $this->post(route('api.v1.uploads.store'), [
        'files' => [File::create('outsider.pdf')->mimeType('application/pdf')],
        'scope' => UploadScope::PROJECT->value,
        'project_id' => $project->id,
    ], accessApiHeaders())->assertForbidden();
});

it('allows company managers to grant and revoke access to selected employees', function () {
    $company = Company::factory()->create();
    $owner = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_OWNER,
    ]);
    $employee = User::factory()->for($company)->create();

    Sanctum::actingAs($owner);

    $uploadResponse = $this->post(route('api.v1.uploads.store'), [
        'files' => [File::create('company-contract.pdf')->mimeType('application/pdf')],
        'scope' => UploadScope::COMPANY->value,
    ], accessApiHeaders())->assertCreated();

    $upload = Upload::query()->findOrFail($uploadResponse->json('data.files.0.id'));

    $this->postJson(route('api.v1.uploads.permissions.store', $upload), [
        'user_ids' => [$employee->id],
        'access_level' => UploadAccessLevel::VIEW->value,
    ], accessApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.shared_with.0.user.id', $employee->id)
        ->assertJsonPath(
            'data.shared_with.0.access_level',
            UploadAccessLevel::VIEW->value,
        );

    Sanctum::actingAs($employee);

    $this->getJson(route('api.v1.uploads.index'), accessApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data.files');

    $this->get(route('api.v1.uploads.download', $upload), accessApiHeaders())
        ->assertOk()
        ->assertDownload('company-contract.pdf');

    $this->postJson(route('api.v1.uploads.permissions.store', $upload), [
        'user_ids' => [$employee->id],
        'access_level' => UploadAccessLevel::MANAGE->value,
    ], accessApiHeaders())->assertForbidden();

    Sanctum::actingAs($owner);

    $this->deleteJson(
        route('api.v1.uploads.permissions.destroy', [$upload, $employee]),
        [],
        accessApiHeaders(),
    )->assertOk();

    Sanctum::actingAs($employee);

    $this->getJson(route('api.v1.uploads.show', $upload), accessApiHeaders())
        ->assertForbidden();
});

it('limits member-visible task files to assignees and project managers', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);
    $assignee = User::factory()->for($company)->create();
    $projectMember = User::factory()->for($company)->create();
    $project = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Task project',
    ]);
    $project->users()->attach([
        $manager->id => ['role' => ProjectRole::MANAGER->value],
        $assignee->id => ['role' => ProjectRole::MEMBER->value],
        $projectMember->id => ['role' => ProjectRole::MEMBER->value],
    ]);
    $task = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Prepare report',
    ]);
    $task->assignees()->attach($assignee);

    Sanctum::actingAs($manager);

    $uploadResponse = $this->post(route('api.v1.uploads.store'), [
        'files' => [File::create('task-report.pdf')->mimeType('application/pdf')],
        'scope' => UploadScope::TASK->value,
        'visibility' => UploadVisibility::MEMBERS->value,
        'project_id' => $project->id,
        'task_id' => $task->id,
    ], accessApiHeaders())->assertCreated();

    $uploadId = $uploadResponse->json('data.files.0.id');

    Sanctum::actingAs($assignee);

    $this->getJson(route('api.v1.uploads.index'), accessApiHeaders())
        ->assertJsonCount(1, 'data.files')
        ->assertJsonPath('data.files.0.id', $uploadId);

    Sanctum::actingAs($projectMember);

    $this->getJson(route('api.v1.uploads.index'), accessApiHeaders())
        ->assertJsonCount(0, 'data.files');

    Sanctum::actingAs($manager);

    $this->getJson(route('api.v1.uploads.index'), accessApiHeaders())
        ->assertJsonCount(1, 'data.files');
});

it('never exposes files across company boundaries even with a stale permission', function () {
    $firstCompany = Company::factory()->create();
    $secondCompany = Company::factory()->create();
    $owner = User::factory()->for($firstCompany)->create([
        'role' => UserRole::COMPANY_OWNER,
    ]);
    $externalUser = User::factory()->for($secondCompany)->create();

    Sanctum::actingAs($owner);

    $uploadResponse = $this->post(route('api.v1.uploads.store'), [
        'files' => [File::create('confidential.pdf')->mimeType('application/pdf')],
        'scope' => UploadScope::COMPANY->value,
        'visibility' => UploadVisibility::MEMBERS->value,
    ], accessApiHeaders())->assertCreated();

    $upload = Upload::query()->findOrFail($uploadResponse->json('data.files.0.id'));
    $upload->sharedUsers()->attach($externalUser, [
        'access_level' => UploadAccessLevel::VIEW->value,
        'granted_by' => $owner->id,
    ]);

    Sanctum::actingAs($externalUser);

    $this->getJson(route('api.v1.uploads.index'), accessApiHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'data.files');

    $this->getJson(route('api.v1.uploads.show', $upload), accessApiHeaders())
        ->assertForbidden();
});

it('rejects a task that does not belong to the selected project', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);
    $firstProject = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'First project',
    ]);
    $secondProject = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Second project',
    ]);
    $firstProject->users()->attach($manager, [
        'role' => ProjectRole::MANAGER->value,
    ]);
    $secondProject->users()->attach($manager, [
        'role' => ProjectRole::MANAGER->value,
    ]);
    $task = Task::query()->create([
        'project_id' => $secondProject->id,
        'title' => 'Wrong task',
    ]);

    Sanctum::actingAs($manager);

    $this->post(route('api.v1.uploads.store'), [
        'files' => [File::create('report.pdf')->mimeType('application/pdf')],
        'scope' => UploadScope::TASK->value,
        'project_id' => $firstProject->id,
        'task_id' => $task->id,
    ], accessApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('task_id', 'data');
});

function accessApiHeaders(): array
{
    return [
        'Accept' => 'application/json',
        'x-api-key' => 'test-api-key',
    ];
}
