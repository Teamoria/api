<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddTaskDependenciesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'dependency_ids' => ['required', 'array', 'min:1'],
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

                $task = Task::query()->find($this->route('id'));

                if ($task === null) {
                    return;
                }

                $dependencyIds = $this->array('dependency_ids');

                if (in_array($task->id, $dependencyIds, true)) {
                    $validator->errors()->add(
                        'dependency_ids',
                        'A task cannot depend on itself.',
                    );

                    return;
                }

                if (Task::query()
                    ->whereKey($dependencyIds)
                    ->where('project_id', $task->project_id)
                    ->count() !== count($dependencyIds)) {
                    $validator->errors()->add(
                        'dependency_ids',
                        'All dependencies must belong to the task project.',
                    );

                    return;
                }

                if ($this->createsCircularDependency($task, $dependencyIds)) {
                    $validator->errors()->add(
                        'dependency_ids',
                        'The selected dependencies would create a circular dependency.',
                    );
                }
            },
        ];
    }

    /**
     * @param  array<int, string>  $dependencyIds
     */
    private function createsCircularDependency(Task $task, array $dependencyIds): bool
    {
        $projectTasks = Task::query()
            ->where('project_id', $task->project_id)
            ->with('dependencies:id')
            ->get(['id', 'project_id'])
            ->keyBy('id');
        $pendingTaskIds = $dependencyIds;
        $visitedTaskIds = [];

        while ($pendingTaskIds !== []) {
            $currentTaskId = array_pop($pendingTaskIds);

            if ($currentTaskId === $task->id) {
                return true;
            }

            if (isset($visitedTaskIds[$currentTaskId])) {
                continue;
            }

            $visitedTaskIds[$currentTaskId] = true;
            $currentTask = $projectTasks->get($currentTaskId);

            if ($currentTask !== null) {
                array_push(
                    $pendingTaskIds,
                    ...$currentTask->dependencies->modelKeys(),
                );
            }
        }

        return false;
    }
}
