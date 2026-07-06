<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Task\AddTaskDependenciesRequest;
use App\Http\Requests\Task\AssignTaskUsersRequest;
use App\Http\Requests\Task\ListTasksRequest;
use App\Http\Requests\Task\StoreTaskNoteRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskNoteResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    public function index(ListTasksRequest $request): JsonResponse
    {
        $tasksQuery = $this->tasksQuery($request->user())
            ->with(['project.company', 'assignees'])
            ->when(
                $request->validated('project_id'),
                fn (Builder $query, string $projectId) => $query->where('project_id', $projectId),
            )
            ->when(
                $request->validated('statuses'),
                fn (Builder $query, array $statuses) => $query->whereIn('status', $statuses),
            )
            ->when(
                $request->validated('priorities'),
                fn (Builder $query, array $priorities) => $query->whereIn('priority', $priorities),
            )
            ->when(
                $request->validated('assignee_id'),
                fn (Builder $query, string $assigneeId) => $query->whereHas(
                    'assignees',
                    fn (Builder $assigneesQuery) => $assigneesQuery->whereKey($assigneeId),
                ),
            )
            ->when(
                $request->validated('due_from'),
                fn (Builder $query, string $dueFrom) => $query->whereDate('due_date', '>=', $dueFrom),
            )
            ->when(
                $request->validated('due_to'),
                fn (Builder $query, string $dueTo) => $query->whereDate('due_date', '<=', $dueTo),
            );

        if ($request->boolean('archived')) {
            $tasksQuery->onlyTrashed();
        }

        $tasks = $tasksQuery
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return $this->successResponse(
            [
                'tasks' => TaskResource::collection($tasks),
                'pagination' => $this->pagination($tasks),
            ],
            'Tasks fetched successfully.',
        );
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $project = $this->accessibleProject(
            $request->user(),
            $validated['project_id'],
        );
        $this->ensureManager($request->user(), $project);

        $task = DB::transaction(function () use ($validated): Task {
            $task = Task::query()->create(
                Arr::except($validated, ['assignee_ids', 'dependency_ids']),
            );

            $task->assignees()->sync($validated['assignee_ids'] ?? []);
            $task->dependencies()->sync($validated['dependency_ids'] ?? []);

            return $task;
        });

        return $this->successResponse(
            new TaskResource($this->loadTaskRelations($task)),
            'Task created successfully.',
            201,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $task = $this->accessibleTask($request->user(), $id);

        return $this->successResponse(
            new TaskResource($this->loadTaskRelations($task)),
            'Task fetched successfully.',
        );
    }

    public function update(UpdateTaskRequest $request, string $id): JsonResponse
    {
        $task = $this->accessibleTask($request->user(), $id);
        $this->ensureManager($request->user(), $task->project);
        $task->update($request->validated());

        return $this->successResponse(
            new TaskResource($this->loadTaskRelations($task)),
            'Task updated successfully.',
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $task = $this->accessibleTask($request->user(), $id);
        $this->ensureManager($request->user(), $task->project);
        $task->delete();

        return $this->successResponse(
            null,
            'Task deleted successfully.',
        );
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $task = $this->tasksQuery($request->user())
            ->onlyTrashed()
            ->findOrFail($id);
        $this->ensureManager($request->user(), $task->project);
        $task->restore();

        return $this->successResponse(
            new TaskResource($this->loadTaskRelations($task)),
            'Task restored successfully.',
        );
    }

    public function forceDelete(Request $request, string $id): JsonResponse
    {
        $task = $this->tasksQuery($request->user())
            ->withTrashed()
            ->findOrFail($id);
        $this->ensureManager($request->user(), $task->project);
        $filePaths = $task->uploads()->pluck('file_path')->all();
        $task->forceDelete();
        Storage::disk('local')->delete($filePaths);

        return $this->successResponse(
            null,
            'Task force deleted successfully.',
        );
    }

    public function addAssignees(AssignTaskUsersRequest $request, string $id): JsonResponse
    {
        $task = $this->accessibleTask($request->user(), $id);
        $this->ensureManager($request->user(), $task->project);
        $task->assignees()->syncWithoutDetaching($request->validated('user_ids'));

        return $this->successResponse(
            new TaskResource($this->loadTaskRelations($task)),
            'Assignees added to task successfully.',
        );
    }

    public function removeAssignee(
        Request $request,
        string $id,
        string $userId,
    ): JsonResponse {
        $task = $this->accessibleTask($request->user(), $id);
        $this->ensureManager($request->user(), $task->project);

        abort_unless(
            $task->assignees()->whereKey($userId)->exists(),
            404,
            'User is not assigned to this task.',
        );

        $task->assignees()->detach($userId);

        return $this->successResponse(
            new TaskResource($this->loadTaskRelations($task)),
            'Assignee removed from task successfully.',
        );
    }

    public function addDependencies(
        AddTaskDependenciesRequest $request,
        string $id,
    ): JsonResponse {
        $task = $this->accessibleTask($request->user(), $id);
        $this->ensureManager($request->user(), $task->project);
        $task->dependencies()->syncWithoutDetaching(
            $request->validated('dependency_ids'),
        );

        return $this->successResponse(
            new TaskResource($this->loadTaskRelations($task)),
            'Dependencies added to task successfully.',
        );
    }

    public function removeDependency(
        Request $request,
        string $id,
        string $dependencyId,
    ): JsonResponse {
        $task = $this->accessibleTask($request->user(), $id);
        $this->ensureManager($request->user(), $task->project);

        abort_unless(
            $task->dependencies()->whereKey($dependencyId)->exists(),
            404,
            'Task dependency was not found.',
        );

        $task->dependencies()->detach($dependencyId);

        return $this->successResponse(
            new TaskResource($this->loadTaskRelations($task)),
            'Dependency removed from task successfully.',
        );
    }

    public function addNote(StoreTaskNoteRequest $request, string $id): JsonResponse
    {
        $task = $this->accessibleTask($request->user(), $id);
        $this->ensureCanAddNote($request->user(), $task->project);
        $note = $task->notes()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        return $this->successResponse(
            new TaskNoteResource($note->load('user')),
            'Task note created successfully.',
            201,
        );
    }

    public function removeNote(
        Request $request,
        string $id,
        string $noteId,
    ): JsonResponse {
        $task = $this->accessibleTask($request->user(), $id);
        $note = $task->notes()->findOrFail($noteId);

        if ($note->user_id !== $request->user()->id) {
            $this->ensureManager($request->user(), $task->project);
        }

        $note->delete();

        return $this->successResponse(
            null,
            'Task note deleted successfully.',
        );
    }

    /**
     * @return Builder<Task>
     */
    private function tasksQuery(User $user): Builder
    {
        $tasksQuery = Task::query();

        if ($user->role === UserRole::ADMIN) {
            return $tasksQuery;
        }

        return $tasksQuery->whereHas(
            'project',
            function (Builder $projectQuery) use ($user): void {
                $projectQuery->whereBelongsTo($user->company);

                if ($user->role !== UserRole::COMPANY_OWNER) {
                    $projectQuery->whereHas(
                        'users',
                        fn (Builder $usersQuery) => $usersQuery->whereKey($user->id),
                    );
                }
            },
        );
    }

    /**
     * @return Builder<Project>
     */
    private function projectsQuery(User $user): Builder
    {
        $projectsQuery = Project::query();

        if ($user->role === UserRole::ADMIN) {
            return $projectsQuery;
        }

        $projectsQuery->whereBelongsTo($user->company);

        if ($user->role !== UserRole::COMPANY_OWNER) {
            $projectsQuery->whereHas(
                'users',
                fn (Builder $query) => $query->whereKey($user->id),
            );
        }

        return $projectsQuery;
    }

    private function accessibleProject(User $user, string $id): Project
    {
        return $this->projectsQuery($user)->findOrFail($id);
    }

    private function accessibleTask(User $user, string $id): Task
    {
        return $this->tasksQuery($user)
            ->with('project')
            ->findOrFail($id);
    }

    private function ensureManager(User $user, Project $project): void
    {
        if (in_array($user->role, [
            UserRole::ADMIN,
            UserRole::COMPANY_OWNER,
        ], true)) {
            return;
        }

        abort_unless(
            $project->users()
                ->whereKey($user->id)
                ->wherePivot('role', ProjectRole::MANAGER->value)
                ->exists(),
            403,
            'You are not authorized to manage this task.',
        );
    }

    private function ensureCanAddNote(User $user, Project $project): void
    {
        if (in_array($user->role, [
            UserRole::ADMIN,
            UserRole::COMPANY_OWNER,
        ], true)) {
            return;
        }

        abort_unless(
            $project->users()
                ->whereKey($user->id)
                ->wherePivotIn('role', [
                    ProjectRole::MANAGER->value,
                    ProjectRole::MEMBER->value,
                ])
                ->exists(),
            403,
            'You are not authorized to add notes to this task.',
        );
    }

    private function loadTaskRelations(Task $task): Task
    {
        return $task->load([
            'project.company',
            'assignees',
            'dependencies',
            'dependentTasks',
            'notes.user',
        ]);
    }
}
