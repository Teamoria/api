<?php

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
    config()->set('services.ai.base_url', 'https://ai.example.test');
    config()->set('services.ai.api_key', 'internal-ai-key');
    config()->set('services.ai.timeout', 30);

    Http::preventStrayRequests();
});

it('requires authentication to ask a chat question', function () {
    $this->postJson(
        route('api.v1.chat.ask'),
        [],
        chatApiHeaders(),
    )->assertUnauthorized();

    Http::assertNothingSent();
});

it('validates the required chat question fields', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(
        route('api.v1.chat.ask'),
        [],
        chatApiHeaders(),
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project_id', 'question'], 'data');

    Http::assertNothingSent();
});

it('sends chat questions to the ai service', function () {
    $user = User::factory()->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);
    $project = Project::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Chat project',
    ]);
    $project->users()->attach($user, [
        'role' => ProjectRole::MANAGER->value,
    ]);
    $payload = [
        'project_id' => $project->id,
        'question' => 'What did the team decide?',
    ];
    $aiResponse = [
        'project_id' => $project->id,
        'question' => 'What did the team decide?',
        'answer' => 'The team decided to connect Laravel to FastAPI.',
        'sources' => [],
    ];

    Http::fake([
        'https://ai.example.test/api/v1/retrieval/query' => Http::response($aiResponse),
    ]);
    Sanctum::actingAs($user);

    $this->postJson(
        route('api.v1.chat.ask'),
        $payload,
        chatApiHeaders(),
    )
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Chat response fetched successfully.',
            'data' => $aiResponse,
        ]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://ai.example.test/api/v1/retrieval/query'
        && $request->hasHeader('X-Internal-API-Key', 'internal-ai-key')
        && $request->hasHeader('X-User-Id', $user->id)
        && $request->hasHeader('X-User-Role', UserRole::COMPANY_MANAGER->value)
        && $request['project_id'] === $project->id
        && $request['question'] === 'What did the team decide?'
        && $request['top_k'] === 5);
    Http::assertSentCount(1);
});

it('prevents company users from asking about inaccessible projects', function () {
    $user = User::factory()->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);
    $project = Project::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Unassigned project',
    ]);

    Sanctum::actingAs($user);

    $this->postJson(route('api.v1.chat.ask'), [
        'project_id' => $project->id,
        'question' => 'What happened in this project?',
    ], chatApiHeaders())->assertForbidden();

    Http::assertNothingSent();
});

it('fetches chat sessions from the ai service', function () {
    $user = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);
    $aiResponse = [
        'sessions' => [
            [
                'id' => 'session-123',
                'title' => 'Project decisions',
            ],
        ],
    ];

    Http::fake([
        'https://ai.example.test/api/v1/chat/sessions' => Http::response($aiResponse),
    ]);
    Sanctum::actingAs($user);

    $this->getJson(
        route('api.v1.chat.sessions'),
        chatApiHeaders(),
    )
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Chat sessions fetched successfully.',
            'data' => $aiResponse,
        ]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://ai.example.test/api/v1/chat/sessions'
        && $request->hasHeader('X-Internal-API-Key', 'internal-ai-key')
        && $request->hasHeader('X-User-Id', $user->id)
        && $request->hasHeader('X-User-Role', UserRole::ADMIN->value));
    Http::assertSentCount(1);
});

function chatApiHeaders(): array
{
    return [
        'Accept' => 'application/json',
        'x-api-key' => 'test-api-key',
    ];
}
