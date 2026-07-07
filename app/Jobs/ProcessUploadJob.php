<?php

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Models\Upload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessUploadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function __construct(public Upload $upload) {}

    /**
     * @throws ConnectionException
     * @throws RequestException
     */
    public function handle(): void
    {
        $this->upload->update([
            'processing_status' => ProcessingStatus::PROCESSING,
            'processing_error' => null,
        ]);

        $baseUrl = config('services.ai.base_url');
        $apiKey = config('services.ai.api_key');
        $timeout = (int) config('services.ai.timeout', 120);

        $response = Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->connectTimeout(10)
            ->retry(2, 1000)
            ->withHeaders(array_filter([
                'X-Internal-API-Key' => $apiKey,
                'X-User-Id' => $this->upload->user_id,
                'X-User-Role' => $this->upload->user?->role?->value ?? 'user',
            ]))
            ->post('/api/v1/extractions/process', array_filter([
                'upload_id' => (string) $this->upload->id,
                'project_id' => $this->upload->project_id ? (string) $this->upload->project_id : null,
                'file_path' => $this->upload->file_path,
                'file_url' => $this->generateFileUrl(),
            ]))
            ->throw();

        /**
         * @var array{
         *     source_type?: string,
         *     transcript?: string,
         *     transcript_quality?: array{level: string, score: int, word_count: int, unique_word_ratio: float, warning: ?string, suggestions: array<int, string>},
         *     summary?: string,
         *     structured_summary?: array{title: ?string, overview: string, priority: ?string, key_points: array<int, string>, task_count: int, decision_count: int},
         *     decisions?: array<int, string>,
         *     decision_items?: array<int, array{title: string, description: string, confidence: string}>,
         *     tasks?: array<int, string>,
         *     task_items?: array<int, array{title: string, description: string, category: ?string, priority: ?string, assignee: ?string, status: string}>,
         *     indexed_chunk_count?: int,
         *     persisted?: bool,
         * } $data
         */
        $data = $response->json();

        DB::transaction(function () use ($data): void {
            $this->saveAiResults($data);

            $this->upload->update([
                'processing_status' => ProcessingStatus::PROCESSED,
            ]);
        });

        Log::info('Upload processed successfully.', ['upload_id' => $this->upload->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveAiResults(array $data): void
    {
        $summary = $this->upload->meetingSummary()->create([
            'source_type' => $data['source_type'] ?? null,
            'transcript' => $data['transcript'] ?? null,
            'transcript_quality' => $data['transcript_quality'] ?? null,
            'summary' => $data['summary'] ?? null,
            'structured_summary' => $data['structured_summary'] ?? null,
            'indexed_chunk_count' => $data['indexed_chunk_count'] ?? 0,
        ]);

        if (! empty($data['decision_items'])) {
            $summary->extractedDecisions()->createMany(
                array_map(
                    fn (array $item): array => [
                        'decision_text' => $item['description'] ?? $item['title'] ?? '',
                        'title' => $item['title'] ?? null,
                        'description' => $item['description'] ?? null,
                        'confidence' => $item['confidence'] ?? null,
                    ],
                    $data['decision_items'],
                ),
            );
        } elseif (! empty($data['decisions'])) {
            $summary->extractedDecisions()->createMany(
                array_map(
                    fn (string $decision): array => ['decision_text' => $decision],
                    $data['decisions'],
                ),
            );
        }

        if (! empty($data['task_items'])) {
            $summary->extractedTasks()->createMany(
                array_map(
                    fn (array $item): array => [
                        'task_text' => $item['description'] ?? $item['title'] ?? '',
                        'title' => $item['title'] ?? null,
                        'description' => $item['description'] ?? null,
                        'category' => $item['category'] ?? null,
                        'priority' => $item['priority'] ?? null,
                        'assignee' => $item['assignee'] ?? null,
                        'status' => $item['status'] ?? 'pending',
                    ],
                    $data['task_items'],
                ),
            );
        } elseif (! empty($data['tasks'])) {
            $summary->extractedTasks()->createMany(
                array_map(
                    fn (string $task): array => ['task_text' => $task],
                    $data['tasks'],
                ),
            );
        }

        // [ARCHIVED] knowledge_chunks — FastAPI now handles Pinecone indexing internally.
        // The indexed_chunk_count is stored on meeting_summaries instead.
        // Keeping this code for potential future use if chunk storage moves back to Laravel.
        //
        // if (! empty($data['chunks'])) {
        //     $this->upload->knowledgeChunks()->createMany(
        //         array_map(
        //             fn (array $chunk): array => [
        //                 'project_id' => $this->upload->project_id,
        //                 'content' => $chunk['content'],
        //                 'embedding' => $chunk['embedding'] ?? null,
        //                 'metadata' => $chunk['metadata'] ?? null,
        //             ],
        //             $data['chunks'],
        //         ),
        //     );
        // }
    }

    private function generateFileUrl(): ?string
    {
        if (! Storage::disk('local')->exists($this->upload->file_path)) {
            return null;
        }

        return url(Storage::disk('local')->url($this->upload->file_path));
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Upload processing failed.', [
            'upload_id' => $this->upload->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->upload->update([
            'processing_status' => ProcessingStatus::FAILED,
            'processing_error' => $exception?->getMessage() ?? 'Unknown processing error.',
        ]);
    }
}
