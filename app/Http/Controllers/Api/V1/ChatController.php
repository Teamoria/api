<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Chat\ChatRequest;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    public function ask(ChatRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $response = $this->aiClient($request)
            ->post('/api/v1/retrieval/query', [
                'project_id' => $validated['project_id'],
                'question' => $validated['question'],
                'top_k' => $validated['top_k'] ?? 5,
            ])
            ->throw();

        return $this->successResponse(
            $response->json(),
            'Chat response fetched successfully.',
        );
    }

    public function sessions(Request $request): JsonResponse
    {
        $response = $this->aiClient($request)
            ->get('/api/v1/chat/sessions')
            ->throw();

        return $this->successResponse(
            $response->json(),
            'Chat sessions fetched successfully.',
        );
    }

    private function aiClient(Request $request): PendingRequest
    {
        /** @var User $user */
        $user = $request->user();

        return Http::baseUrl(config('services.ai.base_url'))
            ->timeout((int) config('services.ai.timeout', 120))
            ->connectTimeout(10)
            ->retry(2, 1000)
            ->withHeaders(array_filter([
                'X-Internal-API-Key' => config('services.ai.api_key'),
                'X-User-Id' => $user->id,
                'X-User-Role' => $user->role->value,
            ]));
    }
}
