<?php

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
