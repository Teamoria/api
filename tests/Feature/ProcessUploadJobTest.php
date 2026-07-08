<?php

use App\Enums\ProcessingStatus;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Jobs\ProcessUploadJob;
use App\Models\Project;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('processes an upload and saves AI extraction results', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $project = Project::query()->create([
        'company_id' => $user->company_id,
        'name' => 'AI Test Project',
    ]);

    $upload = Upload::create([
        'company_id' => $user->company_id,
        'project_id' => $project->id,
        'user_id' => $user->id,
        'scope' => UploadScope::PROJECT,
        'file_path' => 'uploads/test/documents/test.pdf',
        'file_name' => 'test.pdf',
        'file_type' => 'application/pdf',
        'category' => 'document',
        'file_size' => 1024,
        'status' => UploadStatus::SUCCESS,
        'upload_date' => now(),
    ]);

    Storage::disk('local')->put($upload->file_path, 'fake pdf content');

    Http::fake(fn () => Http::response([
        'upload_id' => (string) $upload->id,
        'project_id' => (string) $project->id,
        'source_type' => 'text',
        'transcript' => 'Full extracted text.',
        'transcript_quality' => [
            'level' => 'good',
            'score' => 80,
            'word_count' => 120,
            'unique_word_ratio' => 0.7,
            'warning' => null,
            'suggestions' => [],
        ],
        'summary' => 'Human readable summary.',
        'structured_summary' => [
            'title' => null,
            'overview' => 'Short overview.',
            'priority' => null,
            'key_points' => [],
            'task_count' => 1,
            'decision_count' => 1,
        ],
        'decisions' => ['Decision text'],
        'decision_items' => [
            [
                'title' => 'Decision title',
                'description' => 'Decision description',
                'confidence' => 'medium',
            ],
        ],
        'tasks' => ['Task text'],
        'task_items' => [
            [
                'title' => 'Task title',
                'description' => 'Task description',
                'category' => null,
                'priority' => null,
                'assignee' => null,
                'status' => 'pending',
            ],
        ],
        'indexed_chunk_count' => 8,
        'persisted' => false,
    ], 200));

    ProcessUploadJob::dispatchSync($upload);

    $upload->refresh();

    expect($upload->processing_status)->toBe(ProcessingStatus::PROCESSED)
        ->and($upload->meetingSummary)->not->toBeNull()
        ->and($upload->meetingSummary->source_type)->toBe('text')
        ->and($upload->meetingSummary->transcript)->toBe('Full extracted text.')
        ->and($upload->meetingSummary->summary)->toBe('Human readable summary.')
        ->and($upload->meetingSummary->indexed_chunk_count)->toBe(8)
        ->and($upload->meetingSummary->extractedDecisions)->toHaveCount(1)
        ->and($upload->meetingSummary->extractedDecisions->first()->title)->toBe('Decision title')
        ->and($upload->meetingSummary->extractedTasks)->toHaveCount(1)
        ->and($upload->meetingSummary->extractedTasks->first()->title)->toBe('Task title');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'process-file');
    });
});

it('marks upload as failed when AI service returns an error', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $upload = Upload::create([
        'company_id' => $user->company_id,
        'user_id' => $user->id,
        'scope' => UploadScope::PROJECT,
        'file_path' => 'uploads/test/documents/fail.pdf',
        'file_name' => 'fail.pdf',
        'file_type' => 'application/pdf',
        'category' => 'document',
        'file_size' => 512,
        'status' => UploadStatus::SUCCESS,
        'upload_date' => now(),
    ]);

    Storage::disk('local')->put($upload->file_path, 'fake pdf content');

    Http::fake(fn () => Http::response(['error' => 'Service unavailable'], 500));

    try {
        ProcessUploadJob::dispatchSync($upload);
    } catch (Throwable) {
        // Expected — the job throws on ->throw() for 500 responses
    }

    $upload->refresh();
    expect($upload->processing_status)->toBe(ProcessingStatus::FAILED);
});
