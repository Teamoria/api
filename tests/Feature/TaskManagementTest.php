<?php

use App\Enums\FileCategory;
use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Enums\UploadVisibility;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('allows a project manager to create a task with assignees and dependencies', function () {
    [$project, $manager, $member] = taskWorkspace();
    $dependency = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Prepare requirements',
    ]);

    Sanctum::actingAs($manager);

    $response = $this->postJson(route('api.v1.company.tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Build dashboard',
        'description' => 'Implement the main dashboard.',
        'priority' => TaskPriority::HIGH->value,
        'assignee_ids' => [$member->id],
        'dependency_ids' => [$dependency->id],
    ], taskApiHeaders())
        ->assertCreated()
        ->assertJsonPath('data.title', 'Build dashboard')
        ->assertJsonPath('data.status', TaskStatus::TODO->value)
        ->assertJsonPath('data.priority', TaskPriority::HIGH->value)
        ->assertJsonPath('data.assignees.0.id', $member->id)
        ->assertJsonPath('data.dependencies.0.id', $dependency->id);

    $task = Task::query()->findOrFail($response->json('data.id'));

    expect($task->assignees()->whereKey($member->id)->exists())->toBeTrue()
        ->and($task->dependencies()->whereKey($dependency->id)->exists())->toBeTrue();
});

it('lists only tasks from projects available to the authenticated member', function () {
    $company = Company::factory()->create();
    $member = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MEMBER,
    ]);
    $visibleProject = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Visible project',
    ]);
    $hiddenProject = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Hidden project',
    ]);
    $visibleProject->users()->attach($member, [
        'role' => ProjectRole::MEMBER->value,
    ]);
    $visibleTask = Task::query()->create([
        'project_id' => $visibleProject->id,
        'title' => 'Visible task',
        'status' => TaskStatus::IN_PROGRESS,
    ]);
    Task::query()->create([
        'project_id' => $hiddenProject->id,
        'title' => 'Hidden task',
        'status' => TaskStatus::IN_PROGRESS,
    ]);

    Sanctum::actingAs($member);

    $this->getJson(route('api.v1.company.tasks.index', [
        'statuses' => [TaskStatus::IN_PROGRESS->value],
    ]), taskApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data.tasks')
        ->assertJsonPath('data.tasks.0.id', $visibleTask->id);
});

it('prevents members from opening tasks outside their projects', function () {
    $company = Company::factory()->create();
    $member = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MEMBER,
    ]);
    $visibleProject = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Visible project',
    ]);
    $hiddenProject = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Hidden project',
    ]);
    $visibleProject->users()->attach($member, [
        'role' => ProjectRole::MEMBER->value,
    ]);
    $hiddenTask = Task::query()->create([
        'project_id' => $hiddenProject->id,
        'title' => 'Hidden task',
    ]);

    Sanctum::actingAs($member);

    $this->getJson(
        route('api.v1.company.tasks.show', $hiddenTask),
        taskApiHeaders(),
    )->assertNotFound();
});

it('prevents a regular project member from managing tasks', function () {
    [$project, , $member] = taskWorkspace();
    $task = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Protected task',
    ]);

    Sanctum::actingAs($member);

    $this->putJson(
        route('api.v1.company.tasks.update', $task),
        ['title' => 'Unauthorized change'],
        taskApiHeaders(),
    )
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to manage this task.');

    expect($task->refresh()->title)->toBe('Protected task');
});

it('allows assigned members to update task status', function () {
    [$project, , $member] = taskWorkspace();
    $task = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Assigned task',
    ]);
    $task->assignees()->attach($member);

    Sanctum::actingAs($member);

    $this->patchJson(
        route('api.v1.company.tasks.status.update', $task),
        ['status' => TaskStatus::IN_PROGRESS->value],
        taskApiHeaders(),
    )
        ->assertOk()
        ->assertJsonPath('data.status', TaskStatus::IN_PROGRESS->value);

    expect($task->refresh()->status)->toBe(TaskStatus::IN_PROGRESS);
});

it('allows assigned members to update their task progress', function () {
    [$project, , $member] = taskWorkspace();
    $task = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Progress task',
    ]);
    $task->assignees()->attach($member);

    Sanctum::actingAs($member);

    $this->patchJson(
        route('api.v1.company.tasks.progress.update', $task),
        ['completed' => true],
        taskApiHeaders(),
    )
        ->assertOk()
        ->assertJsonPath('data.assignees.0.task_progress.is_seen', true)
        ->assertJsonPath('data.assignees.0.task_progress.is_completed', true);

    $assignee = $task->assignees()->whereKey($member->id)->firstOrFail();

    expect($assignee->pivot->seen_at)->not->toBeNull()
        ->and($assignee->pivot->completed_at)->not->toBeNull();
});

it('prevents unassigned members from updating task status or progress', function () {
    [$project, , $member] = taskWorkspace();
    $task = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Unassigned task',
    ]);

    Sanctum::actingAs($member);

    $this->patchJson(
        route('api.v1.company.tasks.status.update', $task),
        ['status' => TaskStatus::IN_PROGRESS->value],
        taskApiHeaders(),
    )->assertForbidden();

    $this->patchJson(
        route('api.v1.company.tasks.progress.update', $task),
        ['seen' => true],
        taskApiHeaders(),
    )->assertForbidden();
});

it('rejects assignees who are not members of the task project', function () {
    [$project, $manager] = taskWorkspace();
    $outsider = User::factory()->create();

    Sanctum::actingAs($manager);

    $this->postJson(route('api.v1.company.tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Invalid assignment',
        'assignee_ids' => [$outsider->id],
    ], taskApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assignee_ids', 'data');
});

it('filters tasks by assignee and priority', function () {
    [$project, $manager, $member] = taskWorkspace();
    $matchingTask = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Urgent task',
        'priority' => TaskPriority::EMERGENCY,
    ]);
    $matchingTask->assignees()->attach($member);
    Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Normal task',
        'priority' => TaskPriority::LOW,
    ]);

    Sanctum::actingAs($manager);

    $this->getJson(route('api.v1.company.tasks.index', [
        'assignee_id' => $member->id,
        'priorities' => [TaskPriority::EMERGENCY->value],
    ]), taskApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data.tasks')
        ->assertJsonPath('data.tasks.0.id', $matchingTask->id);
});

it('prevents circular task dependencies', function () {
    [$project, $manager] = taskWorkspace();
    $firstTask = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'First task',
    ]);
    $secondTask = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Second task',
    ]);
    $firstTask->dependencies()->attach($secondTask);

    Sanctum::actingAs($manager);

    $this->postJson(
        route('api.v1.company.tasks.dependencies.add', $secondTask),
        ['dependency_ids' => [$firstTask->id]],
        taskApiHeaders(),
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('dependency_ids', 'data');
});

it('allows project members to add notes and protects notes from other members', function () {
    [$project, $manager, $member] = taskWorkspace();
    $otherMember = User::factory()->for($project->company)->create([
        'role' => UserRole::COMPANY_MEMBER,
    ]);
    $project->users()->attach($otherMember, [
        'role' => ProjectRole::MEMBER->value,
    ]);
    $task = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Discuss task',
    ]);

    Sanctum::actingAs($member);

    $noteId = $this->postJson(
        route('api.v1.company.tasks.notes.add', $task),
        ['content' => 'I have started working on this task.'],
        taskApiHeaders(),
    )
        ->assertCreated()
        ->assertJsonPath('data.user.id', $member->id)
        ->json('data.id');

    Sanctum::actingAs($otherMember);

    $this->deleteJson(
        route('api.v1.company.tasks.notes.remove', [$task, $noteId]),
        [],
        taskApiHeaders(),
    )->assertForbidden();

    Sanctum::actingAs($manager);

    $this->deleteJson(
        route('api.v1.company.tasks.notes.remove', [$task, $noteId]),
        [],
        taskApiHeaders(),
    )->assertOk();
});

it('soft deletes and restores tasks', function () {
    [$project, $manager] = taskWorkspace();
    $task = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Temporary task',
    ]);

    Sanctum::actingAs($manager);

    $this->deleteJson(
        route('api.v1.company.tasks.destroy', $task),
        [],
        taskApiHeaders(),
    )->assertOk();

    $this->assertSoftDeleted($task);

    $this->patchJson(
        route('api.v1.company.tasks.restore', $task),
        [],
        taskApiHeaders(),
    )
        ->assertOk()
        ->assertJsonPath('data.id', $task->id);

    expect($task->refresh()->trashed())->toBeFalse();
});

it('allows an administrator to manage tasks across companies', function () {
    $company = Company::factory()->create();
    $project = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'External company project',
    ]);
    $admin = User::factory()->create([
        'company_id' => null,
        'role' => UserRole::ADMIN,
    ]);

    Sanctum::actingAs($admin);

    $this->postJson(route('api.v1.admin.tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Administrator task',
    ], taskApiHeaders())
        ->assertCreated()
        ->assertJsonPath('data.project.id', $project->id);
});

it('removes stored upload files when permanently deleting a task', function () {
    Storage::fake('local');
    [$project, $manager] = taskWorkspace();
    $task = Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Disposable task',
    ]);
    $filePath = "uploads/{$project->company_id}/task/documents/task-file.pdf";
    Storage::disk('local')->put($filePath, 'task file');
    $upload = Upload::query()->create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'task_id' => $task->id,
        'user_id' => $manager->id,
        'scope' => UploadScope::TASK,
        'visibility' => UploadVisibility::PRIVATE,
        'file_path' => $filePath,
        'file_name' => 'task-file.pdf',
        'file_type' => 'application/pdf',
        'category' => FileCategory::DOCUMENT,
        'file_size' => 9,
        'status' => UploadStatus::SUCCESS,
        'upload_date' => now(),
    ]);

    Sanctum::actingAs($manager);

    $this->deleteJson(
        route('api.v1.company.tasks.force-delete', $task),
        [],
        taskApiHeaders(),
    )->assertOk();

    Storage::disk('local')->assertMissing($filePath);

    expect(Task::withTrashed()->find($task->id))->toBeNull()
        ->and(Upload::query()->find($upload->id))->toBeNull();
});

/**
 * @return array{Project, User, User}
 */
function taskWorkspace(): array
{
    $company = Company::factory()->create();
    $manager = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);
    $member = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MEMBER,
    ]);
    $project = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Task project',
    ]);
    $project->users()->attach([
        $manager->id => ['role' => ProjectRole::MANAGER->value],
        $member->id => ['role' => ProjectRole::MEMBER->value],
    ]);

    return [$project, $manager, $member];
}

function taskApiHeaders(): array
{
    return ['x-api-key' => 'test-api-key'];
}
