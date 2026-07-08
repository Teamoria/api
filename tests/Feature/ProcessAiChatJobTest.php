<?php

use App\Enums\MessageRole;
use App\Events\AiResponseReceived;
use App\Jobs\ProcessAiChatJob;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('sends chat context to the ai service and broadcasts the reply', function () {
    config()->set('services.ai.chat_endpoint', 'https://ai.example.test/api/v1/chat');
    config()->set('services.ai.api_key', 'internal-ai-key');
    config()->set('services.ai.timeout', 30);

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $project = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'AI Integration Project',
    ]);
    $session = ChatSession::factory()->for($user)->create([
        'project_id' => $project->id,
    ]);
    $historyMessage = ChatMessage::factory()->for($session)->create([
        'role' => MessageRole::AI,
        'content' => 'Welcome back. What can I help with?',
        'created_at' => now()->subMinute(),
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://ai.example.test/api/v1/chat' => Http::response([
            'status' => 'success',
            'data' => [
                'reply' => 'Here is the API-powered answer.',
                'sources_used' => [],
                'chat_history' => null,
            ],
        ]),
    ]);
    Event::fake([AiResponseReceived::class]);

    (new ProcessAiChatJob($session, 'Hello API'))->handle();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://ai.example.test/api/v1/chat'
        && $request->hasHeader('X-Internal-API-Key', 'internal-ai-key')
        && $request->hasHeader('X-User-Id', $user->id)
        && $request['user_id'] === (string) $user->id
        && $request['company_id'] === (string) $company->id
        && $request['project_id'] === (string) $project->id
        && $request['message'] === 'Hello API'
        && $request['chat_history'] === [
            [
                'role' => 'assistant',
                'content' => 'Welcome back. What can I help with?',
            ],
        ]);
    Http::assertSentCount(1);

    $aiMessage = ChatMessage::query()
        ->whereBelongsTo($session)
        ->where('role', MessageRole::AI)
        ->where('content', 'Here is the API-powered answer.')
        ->sole();

    expect($aiMessage->chat_session_id)->toBe($session->id)
        ->and($aiMessage->created_at->greaterThan($historyMessage->created_at))->toBeTrue();

    Event::assertDispatched(
        AiResponseReceived::class,
        fn (AiResponseReceived $event): bool => $event->message->is($aiMessage)
            && $event->broadcastWith()['message']['content'] === 'Here is the API-powered answer.',
    );
});

it('broadcasts the ai message on the users private chat channel', function () {
    $user = User::factory()->create();
    $session = ChatSession::factory()->for($user)->create();
    $aiMessage = ChatMessage::factory()->for($session)->create([
        'role' => MessageRole::AI,
        'content' => 'Here is the answer.',
    ]);

    $event = new AiResponseReceived($aiMessage);
    $payload = $event->broadcastWith();

    expect($event->broadcastOn()[0]->name)->toBe('private-chat.'.$user->id)
        ->and($event->broadcastAs())->toBe('ai.message.received')
        ->and($payload['message']['id'])->toBe($aiMessage->id)
        ->and($payload['message']['chat_session_id'])->toBe($session->id)
        ->and($payload['message']['role'])->toBe(MessageRole::AI->value)
        ->and($payload['message']['content'])->toBe('Here is the answer.')
        ->and($payload['message'])->toHaveKeys(['created_at', 'updated_at']);
});
