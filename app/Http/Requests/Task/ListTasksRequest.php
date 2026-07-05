<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    public function rules(): array
    {
        $dueToRules = ['sometimes', 'date'];

        if ($this->filled('due_from')) {
            $dueToRules[] = 'after_or_equal:due_from';
        }

        return [
            'project_id' => [
                'sometimes',
                'uuid',
                Rule::exists('projects', 'id')->whereNull('deleted_at'),
            ],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => ['required', 'distinct', Rule::enum(TaskStatus::class)],
            'priorities' => ['sometimes', 'array'],
            'priorities.*' => ['required', 'distinct', Rule::enum(TaskPriority::class)],
            'assignee_id' => [
                'sometimes',
                'uuid',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'due_from' => ['sometimes', 'date'],
            'due_to' => $dueToRules,
            'archived' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
