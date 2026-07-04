<?php

namespace App\Http\Resources;

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
            'download_url' => route('api.v1.uploads.download', $this->resource),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
