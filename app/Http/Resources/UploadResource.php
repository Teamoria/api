<?php

namespace App\Http\Resources;

use App\Models\ExtractedDecision;
use App\Models\ExtractedTask;
use App\Models\KnowledgeChunk;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->file_name,
            'company_id' => $this->company_id,
            'project_id' => $this->project_id,
            'task_id' => $this->task_id,
            'scope' => $this->scope->value,
            'visibility' => $this->visibility->value,
            'file_name' => $this->file_name,
            'file_type' => $this->file_type,
            'category' => $this->category->value,
            'file_size' => $this->file_size,
            'status' => $this->status->value,
            'processing_status' => $this->processing_status->value,
            'processing_error' => $this->processing_error,
            'upload_date' => $this->upload_date,
            'uploaded_by' => $this->whenLoaded(
                'user',
                fn () => new UserResource($this->user),
            ),
            'shared_with' => $this->whenLoaded(
                'sharedUsers',
                fn () => $this->sharedUsers->map(fn (User $user): array => [
                    'user' => new UserResource($user),
                    'access_level' => $user->pivot->access_level,
                ]),
            ),
            'summary' => $this->whenLoaded(
                'meetingSummary',
                fn () => $this->meetingSummary ? [
                    'transcript' => $this->meetingSummary->transcript,
                    'summary' => $this->meetingSummary->summary,
                ] : null,
            ),
            'decisions' => $this->whenLoaded(
                'meetingSummary',
                fn () => $this->meetingSummary
                    ?->extractedDecisions
                    ?->map(fn (ExtractedDecision $d): string => $d->decision_text)
                    ?->values() ?? [],
            ),
            'tasks' => $this->whenLoaded(
                'meetingSummary',
                fn () => $this->meetingSummary
                    ?->extractedTasks
                    ?->map(fn (ExtractedTask $t): string => $t->task_text)
                    ?->values() ?? [],
            ),
            'chunks' => $this->whenLoaded(
                'knowledgeChunks',
                fn () => $this->knowledgeChunks->map(fn (KnowledgeChunk $chunk): array => [
                    'content' => $chunk->content,
                    'metadata' => $chunk->metadata,
                ]),
            ),
            'download_url' => route('api.v1.uploads.download', $this->resource),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
