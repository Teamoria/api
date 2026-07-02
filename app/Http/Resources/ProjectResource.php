<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'progress' => $this->progress,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'users' => $this->whenLoaded('users', fn () => UserResource::collection($this->users)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
