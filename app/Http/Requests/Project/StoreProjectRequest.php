<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_id' => [
                Rule::requiredIf(fn (): bool => $this->user()?->role === UserRole::ADMIN),
                Rule::prohibitedIf(fn (): bool => $this->user()?->role !== UserRole::ADMIN),
                'uuid',
                Rule::exists('companies', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}
