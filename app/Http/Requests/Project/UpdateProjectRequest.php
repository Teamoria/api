<?php

namespace App\Http\Requests\Project;

use App\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', Rule::in(ProjectStatus::cases())],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages()
    {
        return [
            'status.in' => 'The status must be one of the following: '.implode(', ', ProjectStatus::cases()),
            'progress.min' => 'The progress must be at least 0.',
            'progress.max' => 'The progress must be at most 100.',
            'end_date.after_or_equal:start_date' => 'The end date must be after or equal to the start date.',
            'start_date.date' => 'The start date must be a valid date.',
            'end_date.date' => 'The end date must be a valid date.',
        ];
    }
}
