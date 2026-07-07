<?php

namespace App\Http\Resources;

use App\Models\ExtractedDecision;
use App\Models\ExtractedTask;
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
            'source_type' => $this->whenLoaded(
                'meetingSummary',
                fn () => $this->meetingSummary?->source_type,
            ),
            'summary' => $this->whenLoaded(
                'meetingSummary',
                fn () => $this->meetingSummary ? [
                    'transcript' => $this->meetingSummary->transcript,
                    'transcript_quality' => $this->meetingSummary->transcript_quality,
                    'summary' => $this->meetingSummary->summary,
                    'structured_summary' => $this->meetingSummary->structured_summary,
                    'indexed_chunk_count' => $this->meetingSummary->indexed_chunk_count,
                ] : null,
            ),
            'decisions' => $this->whenLoaded(
                'meetingSummary',
                fn () => $this->meetingSummary
                    ?->extractedDecisions
                    ?->map(fn (ExtractedDecision $d): string => $d->decision_text)
                    ?->values() ?? [],
            ),
            'decision_items' => $this->whenLoaded(
                'meetingSummary',
                fn () => $this->meetingSummary
                    ?->extractedDecisions
                    ?->map(fn (ExtractedDecision $d): array => [
                        'title' => $d->title,
                        'description' => $d->description,
                        'confidence' => $d->confidence,
                    ])
                    ?->values() ?? [],
            ),
            'tasks' => $this->whenLoaded(
                'meetingSummary',
                fn () => $this->meetingSummary
                    ?->extractedTasks
                    ?->map(fn (ExtractedTask $t): string => $t->task_text)
                    ?->values() ?? [],
            ),
            'task_items' => $this->whenLoaded(
                'meetingSummary',
                fn () => $this->meetingSummary
                    ?->extractedTasks
                    ?->map(fn (ExtractedTask $t): array => [
                        'title' => $t->title,
                        'description' => $t->description,
                        'category' => $t->category,
                        'priority' => $t->priority,
                        'assignee' => $t->assignee,
                        'status' => $t->status,
                    ])
                    ?->values() ?? [],
            ),
            'download_url' => route('api.v1.uploads.download', $this->resource),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
