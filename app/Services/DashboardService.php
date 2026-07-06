<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class DashboardService
{
    /**
     * @return array{
     *     totals: array<string, int>,
     *     project_statuses: array<string, int>,
     *     task_statuses: array<string, int>,
     *     recent_projects: Collection<int, Project>,
     *     upcoming_tasks: Collection<int, Task>
     * }
     */
    public function for(User $user): array
    {
        $projectsQuery = $this->projectsQuery($user);
        $tasksQuery = $this->tasksQuery($projectsQuery);

        $totals = [
            'users' => $this->usersQuery($user)->count(),
            'projects' => (clone $projectsQuery)->count(),
            'tasks' => (clone $tasksQuery)->count(),
            'overdue_tasks' => (clone $tasksQuery)
                ->where('status', '!=', TaskStatus::DONE->value)
                ->whereDate('due_date', '<', today())
                ->count(),
        ];

        if ($user->role === UserRole::ADMIN) {
            $totals['companies'] = Company::query()->count();
        }

        return [
            'totals' => $totals,
            'project_statuses' => $this->statusCounts(
                clone $projectsQuery,
                ProjectStatus::cases(),
            ),
            'task_statuses' => $this->statusCounts(
                clone $tasksQuery,
                TaskStatus::cases(),
            ),
            'recent_projects' => (clone $projectsQuery)
                ->with('company')
                ->latest()
                ->limit(5)
                ->get(),
            'upcoming_tasks' => (clone $tasksQuery)
                ->with(['project.company', 'assignees'])
                ->where('status', '!=', TaskStatus::DONE->value)
                ->whereDate('due_date', '>=', today())
                ->orderBy('due_date')
                ->limit(5)
                ->get(),
        ];
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

        $projectsQuery->where('company_id', $user->company_id);

        if ($user->role !== UserRole::COMPANY_OWNER) {
            $projectsQuery->whereHas(
                'users',
                fn (Builder $query) => $query->whereKey($user->id),
            );
        }

        return $projectsQuery;
    }

    /**
     * @param  Builder<Project>  $projectsQuery
     * @return Builder<Task>
     */
    private function tasksQuery(Builder $projectsQuery): Builder
    {
        return Task::query()->whereIn(
            'project_id',
            (clone $projectsQuery)->select('id'),
        );
    }

    /**
     * @return Builder<User>
     */
    private function usersQuery(User $user): Builder
    {
        return User::query()->when(
            $user->role !== UserRole::ADMIN,
            fn (Builder $query) => $query->where('company_id', $user->company_id),
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, BackedEnum>  $statuses
     * @return array<string, int>
     */
    private function statusCounts(Builder $query, array $statuses): array
    {
        $counts = $query
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect($statuses)
            ->mapWithKeys(fn (BackedEnum $status): array => [
                $status->value => (int) ($counts[$status->value] ?? 0),
            ])
            ->all();
    }
}
