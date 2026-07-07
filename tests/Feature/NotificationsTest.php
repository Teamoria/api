<?php

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('lists notifications for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $notification = createDatabaseNotification($user, [
        'title' => 'Visible notification',
    ]);
    createDatabaseNotification($otherUser, [
        'title' => 'Hidden notification',
    ]);

    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.notifications.index'), notificationApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data.notifications')
        ->assertJsonPath('data.notifications.0.id', $notification->id)
        ->assertJsonPath('data.notifications.0.data.title', 'Visible notification')
        ->assertJsonPath('data.unread_count', 1);
});

it('filters unread notifications and returns the unread count', function () {
    $user = User::factory()->create();
    createDatabaseNotification($user, ['title' => 'Unread']);
    createDatabaseNotification($user, ['title' => 'Read'], now());

    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.notifications.index', [
        'status' => 'unread',
    ]), notificationApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data.notifications')
        ->assertJsonPath('data.notifications.0.data.title', 'Unread')
        ->assertJsonPath('data.unread_count', 1);

    $this->getJson(route('api.v1.notifications.unread-count'), notificationApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);
});

it('marks owned notifications as read', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $notification = createDatabaseNotification($user);
    $otherNotification = createDatabaseNotification($otherUser);

    Sanctum::actingAs($user);

    $this->patchJson(
        route('api.v1.notifications.read', $notification),
        [],
        notificationApiHeaders(),
    )
        ->assertOk()
        ->assertJsonPath('data.is_read', true);

    $this->patchJson(
        route('api.v1.notifications.read', $otherNotification),
        [],
        notificationApiHeaders(),
    )->assertNotFound();

    expect($notification->refresh()->read_at)->not->toBeNull()
        ->and($otherNotification->refresh()->read_at)->toBeNull();
});

it('marks all notifications as read and deletes owned notifications', function () {
    $user = User::factory()->create();
    $firstNotification = createDatabaseNotification($user);
    createDatabaseNotification($user);

    Sanctum::actingAs($user);

    $this->patchJson(
        route('api.v1.notifications.read-all'),
        [],
        notificationApiHeaders(),
    )
        ->assertOk()
        ->assertJsonPath('data.unread_count', 0);

    expect($user->unreadNotifications()->count())->toBe(0);

    $this->deleteJson(
        route('api.v1.notifications.destroy', $firstNotification),
        [],
        notificationApiHeaders(),
    )->assertOk();

    expect($user->notifications()->whereKey($firstNotification->id)->exists())->toBeFalse();
});

it('sends a database notification when a member is assigned to a task', function () {
    [$project, $manager, $member] = notificationWorkspace();
    Notification::fake();

    Sanctum::actingAs($manager);

    $this->postJson(route('api.v1.company.tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Build notifications',
        'assignee_ids' => [$member->id],
    ], notificationApiHeaders())->assertCreated();

    Notification::assertSentTo(
        $member,
        fn (TaskAssignedNotification $notification, array $channels): bool => $channels === ['database']
            && $notification->task->title === 'Build notifications',
    );
    Notification::assertNotSentTo($manager, TaskAssignedNotification::class);
});

function createDatabaseNotification(User $user, array $data = [], mixed $readAt = null): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'task_assigned',
        'data' => array_merge([
            'title' => 'Task assigned',
            'body' => 'You were assigned to a task.',
        ], $data),
        'read_at' => $readAt,
    ]);
}

/**
 * @return array{Project, User, User}
 */
function notificationWorkspace(): array
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
        'name' => 'Notification project',
    ]);
    $project->users()->attach([
        $manager->id => ['role' => ProjectRole::MANAGER->value],
        $member->id => ['role' => ProjectRole::MEMBER->value],
    ]);

    return [$project, $manager, $member];
}

function notificationApiHeaders(): array
{
    return ['x-api-key' => 'test-api-key'];
}
