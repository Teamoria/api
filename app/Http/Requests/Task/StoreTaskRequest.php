<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                'uuid',
                Rule::exists('projects', 'id')->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'dependency_ids' => ['sometimes', 'array'],
            'dependency_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('tasks', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $project = Project::query()->find($this->string('project_id')->toString());

                if ($project === null) {
                    return;
                }

                $assigneeIds = $this->array('assignee_ids');

                if ($project->users()->whereKey($assigneeIds)->count() !== count($assigneeIds)) {
                    $validator->errors()->add(
                        'assignee_ids',
                        'All assignees must be members of the selected project.',
                    );
                }

                $dependencyIds = $this->array('dependency_ids');

                if (Task::query()
                    ->whereKey($dependencyIds)
                    ->whereBelongsTo($project)
                    ->count() !== count($dependencyIds)) {
                    $validator->errors()->add(
                        'dependency_ids',
                        'All dependencies must belong to the selected project.',
                    );
                }
            },
        ];
    }
}
