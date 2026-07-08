<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageRole;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Jobs\ProcessAiChatJob;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function sendMessage(SendChatMessageRequest $request): JsonResponse
    {
        /** @var array{session_id?: string|null, project_id?: string|null, message_content: string} $validated */
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        /** @var array{0: ChatSession, 1: ChatMessage} $result */
        $result = DB::transaction(function () use ($user, $validated): array {
            $session = $this->resolveChatSession($user, $validated);
            $message = $session->messages()->create([
                'role' => MessageRole::USER,
                'content' => $validated['message_content'],
            ]);

            return [$session, $message];
        });

        [$session, $message] = $result;

        ProcessAiChatJob::dispatch($session, $message);

        return $this->successResponse(
            [
                'session_id' => $session->id,
                'message_id' => $message->id,
                'status' => 'processing',
            ],
            'Message is being processed.',
        );
    }

    public function sessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sessions = $user->chatSessions()
            ->with([
                'project:id,name',
            ])
            ->latest()
            ->get();

        return $this->successResponse(
            $sessions,
            'Chat sessions fetched successfully.',
        );
    }

    public function getMessages(Request $request, ChatSession $session): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($session->user_id !== $user->id) {
            return $this->errorResponse(
                'You do not have permission to view this chat session.',
                403,
            );
        }

        $messages = $session->messages()
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(30);

        return $this->successResponse(
            $messages,
            'Chat messages fetched successfully.',
        );
    }

    /**
     * @param  array{session_id?: string|null, project_id?: string|null, message_content: string}  $validated
     */
    private function resolveChatSession(User $user, array $validated): ChatSession
    {
        if (! empty($validated['session_id'])) {
            return ChatSession::query()
                ->whereBelongsTo($user)
                ->findOrFail($validated['session_id']);
        }

        return ChatSession::query()->create([
            'user_id' => $user->id,
            'project_id' => $validated['project_id'] ?? null,
        ]);
    }
}
