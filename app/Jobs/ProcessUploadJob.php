<?php

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Models\Upload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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
            ->post('/api/v1/uploads/process', [
                'upload_id' => $this->upload->id,
                'project_id' => $this->upload->project_id,
                'file_path' => $this->upload->file_path,
                'file_url' => $this->generateFileUrl(),
            ])
            ->throw();

        /** @var array{transcript?: string, summary?: string, decisions?: array<int, string>, tasks?: array<int, string>, chunks?: array<int, array{content: string, metadata?: array<string, mixed>, embedding?: array<int, float>}>} $data */
        $data = $response->json();

        $this->saveAiResults($data);

        $this->upload->update([
            'processing_status' => ProcessingStatus::PROCESSED,
        ]);

        Log::info('Upload processed successfully.', ['upload_id' => $this->upload->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveAiResults(array $data): void
    {
        $summary = $this->upload->meetingSummary()->create([
            'transcript' => $data['transcript'] ?? null,
            'summary' => $data['summary'] ?? null,
        ]);

        if (! empty($data['decisions'])) {
            $summary->extractedDecisions()->createMany(
                array_map(
                    fn (string $decision): array => ['decision_text' => $decision],
                    $data['decisions'],
                ),
            );
        }

        if (! empty($data['tasks'])) {
            $summary->extractedTasks()->createMany(
                array_map(
                    fn (string $task): array => ['task_text' => $task],
                    $data['tasks'],
                ),
            );
        }

        if (! empty($data['chunks'])) {
            $this->upload->knowledgeChunks()->createMany(
                array_map(
                    fn (array $chunk): array => [
                        'project_id' => $this->upload->project_id,
                        'content' => $chunk['content'],
                        'embedding' => $chunk['embedding'] ?? null,
                        'metadata' => $chunk['metadata'] ?? null,
                    ],
                    $data['chunks'],
                ),
            );
        }
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
