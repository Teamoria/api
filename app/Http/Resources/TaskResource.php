<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date,
            'project' => $this->whenLoaded(
                'project',
                fn () => new ProjectResource($this->project),
            ),
            'assignees' => $this->whenLoaded(
                'assignees',
                fn () => UserResource::collection($this->assignees),
            ),
            'dependencies' => $this->whenLoaded(
                'dependencies',
                fn () => self::collection($this->dependencies),
            ),
            'dependent_tasks' => $this->whenLoaded(
                'dependentTasks',
                fn () => self::collection($this->dependentTasks),
            ),
            'notes' => $this->whenLoaded(
                'notes',
                fn () => TaskNoteResource::collection($this->notes),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
