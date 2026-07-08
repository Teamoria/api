<?php

use App\Enums\MessageRole;
use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Jobs\ProcessAiChatJob;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('requires authentication to send a chat message', function () {
    $this->postJson(
        route('api.v1.chat.messages.store'),
        [],
        chatApiHeaders(),
    )->assertUnauthorized();
});

it('validates the required chat message fields', function () {
    $user = User::factory()->create();
    grantActiveSubscription($user->company);

    Sanctum::actingAs($user);

    $this->postJson(
        route('api.v1.chat.messages.store'),
        [],
        chatApiHeaders(),
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message_content'], 'data');
});

it('stores the user message and queues ai processing', function () {
    $user = User::factory()->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);
    grantActiveSubscription($user->company);

    $project = Project::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Chat project',
    ]);
    $project->users()->attach($user, [
        'role' => ProjectRole::MANAGER->value,
    ]);

    Queue::fake();
    Sanctum::actingAs($user);

    $this->postJson(
        route('api.v1.chat.messages.store'),
        [
            'project_id' => $project->id,
            'message_content' => 'What did the team decide?',
        ],
        chatApiHeaders(),
    )
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Message is being processed.')
        ->assertJsonPath('data.status', 'processing');

    $session = ChatSession::query()->sole();
    $message = ChatMessage::query()->sole();

    expect($session->user_id)->toBe($user->id)
        ->and($session->project_id)->toBe($project->id)
        ->and($message->chat_session_id)->toBe($session->id)
        ->and($message->role)->toBe(MessageRole::USER)
        ->and($message->content)->toBe('What did the team decide?');

    Queue::assertPushed(ProcessAiChatJob::class, fn (ProcessAiChatJob $job): bool => $job->chatSession->is($session)
        && $job->userMessage->is($message));
});

it('stores messages against an existing session', function () {
    $user = User::factory()->create();
    grantActiveSubscription($user->company);

    $session = ChatSession::factory()->for($user)->create();

    Queue::fake();
    Sanctum::actingAs($user);

    $this->postJson(route('api.v1.chat.messages.store'), [
        'session_id' => $session->id,
        'message_content' => 'Continue this conversation.',
    ], chatApiHeaders())->assertOk();

    expect(ChatSession::query()->count())->toBe(1);

    $message = ChatMessage::query()->sole();

    expect($message->chat_session_id)->toBe($session->id)
        ->and($message->role)->toBe(MessageRole::USER)
        ->and($message->content)->toBe('Continue this conversation.');

    Queue::assertPushed(ProcessAiChatJob::class);
});

it('prevents company users from sending messages about inaccessible projects', function () {
    $user = User::factory()->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);
    grantActiveSubscription($user->company);

    $project = Project::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Unassigned project',
    ]);

    Sanctum::actingAs($user);

    Queue::fake();

    $this->postJson(route('api.v1.chat.messages.store'), [
        'project_id' => $project->id,
        'message_content' => 'What happened in this project?',
    ], chatApiHeaders())->assertForbidden();

    Queue::assertNothingPushed();
});

it('prevents users from sending messages to another users session', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    grantActiveSubscription($intruder->company);

    $session = ChatSession::factory()->for($owner)->create();

    Queue::fake();
    Sanctum::actingAs($intruder);

    $this->postJson(route('api.v1.chat.messages.store'), [
        'session_id' => $session->id,
        'message_content' => 'Let me in.',
    ], chatApiHeaders())->assertForbidden();

    Queue::assertNothingPushed();
});

it('fetches chat sessions from the database', function () {
    $user = User::factory()->create();
    grantActiveSubscription($user->company);

    $session = ChatSession::factory()->for($user)->create();
    ChatMessage::factory()->for($session)->create([
        'role' => MessageRole::USER,
        'content' => 'What did we decide?',
    ]);

    Sanctum::actingAs($user);

    $this->getJson(
        route('api.v1.chat.sessions'),
        chatApiHeaders(),
    )
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Chat sessions fetched successfully.')
        ->assertJsonPath('data.0.id', $session->id)
        ->assertJsonMissingPath('data.0.messages');
});

it('fetches chat session messages with cursor pagination in oldest order', function () {
    $user = User::factory()->create();
    $session = ChatSession::factory()->for($user)->create();

    foreach (range(1, 31) as $index) {
        ChatMessage::factory()->for($session)->create([
            'role' => MessageRole::USER,
            'content' => 'Message '.$index,
            'created_at' => now()->addSeconds($index),
        ]);
    }

    Sanctum::actingAs($user);

    $this->getJson(
        route('api.v1.chat.sessions.messages', $session),
        chatApiHeaders(),
    )
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Chat messages fetched successfully.')
        ->assertJsonPath('data.per_page', 30)
        ->assertJsonPath('data.data.0.content', 'Message 1')
        ->assertJsonPath('data.data.29.content', 'Message 30')
        ->assertJsonMissingPath('data.data.30');
});

it('prevents users from fetching another users chat messages', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $session = ChatSession::factory()->for($owner)->create();

    Sanctum::actingAs($intruder);

    $this->getJson(
        route('api.v1.chat.sessions.messages', $session),
        chatApiHeaders(),
    )->assertForbidden();
});

function chatApiHeaders(): array
{
    return [
        'Accept' => 'application/json',
        'x-api-key' => 'test-api-key',
    ];
}
