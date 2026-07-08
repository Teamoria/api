<?php

use App\Enums\MessageRole;
use App\Events\AiResponseReceived;
use App\Jobs\ProcessAiChatJob;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('stores a mocked ai response and dispatches the broadcast event', function () {
    $user = User::factory()->create();
    $session = ChatSession::factory()->for($user)->create();
    $userMessage = ChatMessage::factory()->for($session)->create([
        'role' => MessageRole::USER,
        'content' => 'Summarize the latest meeting.',
    ]);

    Event::fake([AiResponseReceived::class]);

    (new ProcessAiChatJob($session, $userMessage))->handle();

    $aiMessage = ChatMessage::query()
        ->where('role', MessageRole::AI)
        ->sole();

    expect($aiMessage->chat_session_id)->toBe($session->id)
        ->and($aiMessage->content)->toBe('Mock AI response for: Summarize the latest meeting.');

    Event::assertDispatched(AiResponseReceived::class, fn (AiResponseReceived $event): bool => $event->message->is($aiMessage));
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
