<?php

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
    Carbon::setTestNow('2026-07-06 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('requires authentication', function () {
    $this->getJson(
        route('api.v1.admin.dashboard'),
        dashboardApiHeaders(),
    )->assertUnauthorized();
});

it('returns a global dashboard for administrators', function () {
    $administrator = User::factory()->create([
        'company_id' => null,
        'role' => UserRole::ADMIN,
    ]);
    $firstCompany = Company::factory()->create();
    $secondCompany = Company::factory()->create();
    User::factory()->for($firstCompany)->create();
    User::factory()->for($secondCompany)->create();

    $activeProject = dashboardProject($firstCompany, 'Active project', ProjectStatus::ACTIVE);
    $completedProject = dashboardProject($secondCompany, 'Completed project', ProjectStatus::COMPLETED);

    dashboardTask($activeProject, 'Upcoming task', TaskStatus::IN_PROGRESS, '2026-07-08');
    dashboardTask($activeProject, 'Overdue task', TaskStatus::BLOCKED, '2026-07-01');
    dashboardTask($completedProject, 'Completed task', TaskStatus::DONE, '2026-07-09');

    Sanctum::actingAs($administrator);

    $this->getJson(
        route('api.v1.admin.dashboard'),
        dashboardApiHeaders(),
    )
        ->assertSuccessful()
        ->assertJsonPath('data.totals.companies', 2)
        ->assertJsonPath('data.totals.users', 3)
        ->assertJsonPath('data.totals.projects', 2)
        ->assertJsonPath('data.totals.tasks', 3)
        ->assertJsonPath('data.totals.overdue_tasks', 1)
        ->assertJsonPath('data.project_statuses.active', 1)
        ->assertJsonPath('data.project_statuses.completed', 1)
        ->assertJsonPath('data.task_statuses.in_progress', 1)
        ->assertJsonPath('data.task_statuses.blocked', 1)
        ->assertJsonPath('data.task_statuses.done', 1)
        ->assertJsonCount(2, 'data.recent_projects')
        ->assertJsonCount(1, 'data.upcoming_tasks')
        ->assertJsonPath('data.upcoming_tasks.0.title', 'Upcoming task');
});

it('scopes a company owner dashboard to their company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $owner = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_OWNER,
    ]);
    User::factory()->for($company)->create();
    User::factory()->for($otherCompany)->create();

    $companyProject = dashboardProject($company, 'Company project');
    $otherProject = dashboardProject($otherCompany, 'Other project');
    dashboardTask($companyProject, 'Company task', TaskStatus::TODO, '2026-07-07');
    dashboardTask($otherProject, 'Other task', TaskStatus::TODO, '2026-07-07');

    Sanctum::actingAs($owner);

    $this->getJson(
        route('api.v1.company.dashboard'),
        dashboardApiHeaders(),
    )
        ->assertSuccessful()
        ->assertJsonMissingPath('data.totals.companies')
        ->assertJsonPath('data.totals.users', 2)
        ->assertJsonPath('data.totals.projects', 1)
        ->assertJsonPath('data.totals.tasks', 1)
        ->assertJsonCount(1, 'data.recent_projects')
        ->assertJsonPath('data.recent_projects.0.id', $companyProject->id)
        ->assertJsonCount(1, 'data.upcoming_tasks')
        ->assertJsonPath('data.upcoming_tasks.0.title', 'Company task');
});

it('limits a company member dashboard to assigned projects', function () {
    $company = Company::factory()->create();
    $member = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MEMBER,
    ]);
    $assignedProject = dashboardProject($company, 'Assigned project');
    $hiddenProject = dashboardProject($company, 'Hidden project');
    $assignedProject->users()->attach($member, [
        'role' => ProjectRole::MEMBER->value,
    ]);
    dashboardTask($assignedProject, 'Assigned task', TaskStatus::TODO, '2026-07-10');
    dashboardTask($hiddenProject, 'Hidden task', TaskStatus::TODO, '2026-07-10');

    Sanctum::actingAs($member);

    $this->getJson(
        route('api.v1.company.dashboard'),
        dashboardApiHeaders(),
    )
        ->assertSuccessful()
        ->assertJsonPath('data.totals.projects', 1)
        ->assertJsonPath('data.totals.tasks', 1)
        ->assertJsonCount(1, 'data.recent_projects')
        ->assertJsonPath('data.recent_projects.0.id', $assignedProject->id)
        ->assertJsonCount(1, 'data.upcoming_tasks')
        ->assertJsonPath('data.upcoming_tasks.0.title', 'Assigned task');
});

function dashboardProject(
    Company $company,
    string $name,
    ProjectStatus $status = ProjectStatus::ACTIVE,
): Project {
    return Project::query()->create([
        'company_id' => $company->id,
        'name' => $name,
        'status' => $status,
    ]);
}

function dashboardTask(
    Project $project,
    string $title,
    TaskStatus $status,
    string $dueDate,
): Task {
    return Task::query()->create([
        'project_id' => $project->id,
        'title' => $title,
        'status' => $status,
        'due_date' => $dueDate,
    ]);
}

function dashboardApiHeaders(): array
{
    return ['x-api-key' => 'test-api-key'];
}
