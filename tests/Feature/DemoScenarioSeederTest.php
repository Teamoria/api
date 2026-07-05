<?php

use App\Enums\CompanyStatus;
use App\Enums\FileCategory;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UploadAccessLevel;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Enums\UploadVisibility;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\ExtractedDecision;
use App\Models\KnowledgeChunk;
use App\Models\MeetingSummary;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds every demo scenario and can be safely run again', function () {
    $this->seed();
    $this->seed();

    $this->assertDatabaseCount((new Plan)->getTable(), 3);
    $this->assertDatabaseCount((new Company)->getTable(), 3);
    $this->assertDatabaseCount((new User)->getTable(), 9);
    $this->assertDatabaseCount((new Subscription)->getTable(), 3);
    $this->assertDatabaseCount((new Payment)->getTable(), 4);
    $this->assertDatabaseCount((new Project)->getTable(), 5);
    $this->assertDatabaseCount('project_user', 15);
    $this->assertDatabaseCount((new Task)->getTable(), 6);
    $this->assertDatabaseCount('task_user', 8);
    $this->assertDatabaseCount('task_dependencies', 3);
    $this->assertDatabaseCount((new TaskNote)->getTable(), 2);
    $this->assertDatabaseCount((new Upload)->getTable(), 6);
    $this->assertDatabaseCount('upload_permissions', 2);
    $this->assertDatabaseCount((new MeetingSummary)->getTable(), 1);
    $this->assertDatabaseCount((new ExtractedDecision)->getTable(), 3);
    $this->assertDatabaseCount((new KnowledgeChunk)->getTable(), 2);

    expect(sortedValues(Company::query()->pluck('status')->all()))
        ->toBe(sortedValues(CompanyStatus::cases()))
        ->and(sortedValues(User::query()->pluck('role')->all()))
        ->toBe(sortedValues(UserRole::cases()))
        ->and(sortedValues(User::query()->pluck('status')->all()))
        ->toBe(sortedValues(UserStatus::cases()))
        ->and(sortedValues(Project::query()->pluck('status')->all()))
        ->toBe(sortedValues(ProjectStatus::cases()))
        ->and(sortedValues(Task::query()->pluck('status')->all()))
        ->toBe(sortedValues(TaskStatus::cases()))
        ->and(sortedValues(Task::query()->pluck('priority')->all()))
        ->toBe(sortedValues(TaskPriority::cases()))
        ->and(sortedValues(Upload::query()->pluck('scope')->all()))
        ->toBe(sortedValues(UploadScope::cases()))
        ->and(sortedValues(Upload::query()->pluck('visibility')->all()))
        ->toBe(sortedValues(UploadVisibility::cases()))
        ->and(sortedValues(Upload::query()->pluck('category')->all()))
        ->toBe(sortedValues(FileCategory::cases()))
        ->and(sortedValues(Upload::query()->pluck('status')->all()))
        ->toBe(sortedValues(UploadStatus::cases()));

    $projectRoles = Project::query()
        ->with('users')
        ->get()
        ->flatMap(fn (Project $project) => $project->users->pluck('pivot.role'))
        ->unique()
        ->sort()
        ->values()
        ->all();

    $uploadAccessLevels = Upload::query()
        ->with('sharedUsers')
        ->get()
        ->flatMap(fn (Upload $upload) => $upload->sharedUsers->pluck('pivot.access_level'))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($projectRoles)
        ->toBe(sortedValues(ProjectRole::cases()))
        ->and($uploadAccessLevels)
        ->toBe(sortedValues(UploadAccessLevel::cases()));

    $activeProject = Project::query()
        ->where('status', ProjectStatus::ACTIVE)
        ->sole();

    $meetingSummary = MeetingSummary::query()
        ->with(['extractedDecisions', 'upload.knowledgeChunks'])
        ->sole();

    expect($activeProject->tasks)->toHaveCount(6)
        ->and($meetingSummary->extractedDecisions)->toHaveCount(3)
        ->and($meetingSummary->upload->knowledgeChunks)->toHaveCount(2)
        ->and(Hash::check(
            'password',
            User::query()->where('email', 'owner@teamoria.test')->sole()->password,
        ))->toBeTrue()
        ->and(Hash::check(
            '1234568',
            User::query()->where('email', 'ahmedalyazuri@gmail.com')->sole()->password,
        ))->toBeTrue();
});

/**
 * @param  array<int, BackedEnum|int|string>  $values
 * @return array<int, int|string>
 */
function sortedValues(array $values): array
{
    return collect($values)
        ->map(
            fn (BackedEnum|int|string $value): int|string => $value instanceof BackedEnum
                ? $value->value
                : $value,
        )
        ->unique()
        ->sort()
        ->values()
        ->all();
}
