<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Events\AiResponseReceived;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

class ProcessAiChatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        #[WithoutRelations]
        public ChatSession $session,
        public string $message,
        ?ChatMessage $currentMessage = null,
    ) {
        $this->currentMessageId = $currentMessage?->getKey();
    }

    public ?string $currentMessageId;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(): void
    {
        $this->session->loadMissing('user');

        $endpoint = config('services.ai.chat_endpoint');

        if (! is_string($endpoint) || $endpoint === '') {
            throw new UnexpectedValueException('The AI chat endpoint is not configured.');
        }

        $response = Http::timeout((int) config('services.ai.timeout', 120))
            ->connectTimeout(10)
            ->retry(2, 1000)
            ->withHeaders(array_filter([
                'X-Internal-API-Key' => config('services.ai.api_key'),
                'X-User-Id' => $this->session->user->id,
                'X-User-Role' => $this->session->user->role->value,
            ]))
            ->post($endpoint, [
                'user_id' => $this->session->user->id,
                'company_id' => $this->session->user->company_id,
                'message' => $this->message,
                'chat_history' => $this->chatHistory(),
            ])
            ->throw();

        $reply = $response->json('data.reply');

        if ($response->json('status') !== 'success' || ! is_string($reply)) {
            throw new UnexpectedValueException('The AI chat response did not include a successful reply.');
        }

        $aiMessage = $this->session->messages()->create([
            'role' => MessageRole::AI,
            'content' => $reply,
        ]);

        event(new AiResponseReceived($aiMessage));
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function chatHistory(): array
    {
        return $this->session->messages()
            ->when(
                $this->currentMessageId !== null,
                fn ($query) => $query->whereKeyNot($this->currentMessageId),
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'created_at'])
            ->map(fn (ChatMessage $message): array => [
                'role' => $this->chatHistoryRole($message->role),
                'content' => $message->content,
            ])
            ->all();
    }

    private function chatHistoryRole(MessageRole $role): string
    {
        return match ($role) {
            MessageRole::USER => 'user',
            MessageRole::AI => 'assistant',
        };
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('AI chat processing failed.', [
            'chat_session_id' => $this->session->id,
            'current_message_id' => $this->currentMessageId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
