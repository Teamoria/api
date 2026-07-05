<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignTaskUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
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

                $task = Task::query()
                    ->with('project')
                    ->find($this->route('id'));

                if ($task === null || $task->project === null) {
                    return;
                }

                $userIds = $this->array('user_ids');

                if ($task->project->users()->whereKey($userIds)->count() !== count($userIds)) {
                    $validator->errors()->add(
                        'user_ids',
                        'All assignees must be members of the task project.',
                    );
                }
            },
        ];
    }
}
