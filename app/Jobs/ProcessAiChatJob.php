<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Events\AiResponseReceived;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAiChatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public ChatSession $chatSession,
        public ChatMessage $userMessage,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(): void
    {
        sleep(3);

        // TODO: Make HTTP request to FastAPI AI endpoint here.
        $content = sprintf('Mock AI response for: %s', $this->userMessage->content);

        $aiMessage = $this->chatSession->messages()->create([
            'role' => MessageRole::AI,
            'content' => $content,
        ]);

        event(new AiResponseReceived($aiMessage));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('AI chat processing failed.', [
            'chat_session_id' => $this->chatSession->id,
            'user_message_id' => $this->userMessage->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
