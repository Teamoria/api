<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status,
            'task_progress' => $this->whenPivotLoaded('task_user', fn () => [
                'seen_at' => $this->pivot->seen_at,
                'completed_at' => $this->pivot->completed_at,
                'is_seen' => $this->pivot->seen_at !== null,
                'is_completed' => $this->pivot->completed_at !== null,
            ]),
            'timezone' => $this->timezone,
            'last_login_at' => $this->last_login_at,
            'is_email_verified' => $this->email_verified_at !== null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
