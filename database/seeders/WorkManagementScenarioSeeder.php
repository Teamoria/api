<?php

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use Illuminate\Database\Seeder;

class WorkManagementScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()
            ->where('name', 'Teamoria Demo')
            ->sole();
        $manager = $this->findUser('manager@teamoria.test');
        $member = $this->findUser('member@teamoria.test');
        $viewer = $this->findUser('viewer@teamoria.test');

        $projects = $this->seedProjects($company, $manager, $member, $viewer);

        $this->seedTasks(
            $projects[ProjectStatus::ACTIVE->value],
            $manager,
            $member,
            $viewer,
        );
    }

    /**
     * @return array<string, Project>
     */
    private function seedProjects(
        Company $company,
        User $manager,
        User $member,
        User $viewer,
    ): array {
        $projectScenarios = [
            ProjectStatus::ACTIVE->value => [
                'name' => 'Active Product Launch',
                'description' => 'The main workspace containing task and upload scenarios.',
                'status' => ProjectStatus::ACTIVE,
                'progress' => 45,
                'start_date' => now()->subMonth(),
                'end_date' => now()->addMonths(2),
            ],
            ProjectStatus::PENDING->value => [
                'name' => 'Pending Mobile Application',
                'description' => 'A project waiting for approval.',
                'status' => ProjectStatus::PENDING,
                'progress' => 0,
                'start_date' => now()->addMonth(),
                'end_date' => now()->addMonths(5),
            ],
            ProjectStatus::PAUSED->value => [
                'name' => 'Paused Data Migration',
                'description' => 'Work is temporarily paused.',
                'status' => ProjectStatus::PAUSED,
                'progress' => 60,
                'start_date' => now()->subMonths(3),
                'end_date' => now()->addMonth(),
            ],
            ProjectStatus::COMPLETED->value => [
                'name' => 'Completed Brand Refresh',
                'description' => 'A completed project retained for history.',
                'status' => ProjectStatus::COMPLETED,
                'progress' => 100,
                'start_date' => now()->subMonths(6),
                'end_date' => now()->subMonth(),
            ],
            ProjectStatus::CANCELLED->value => [
                'name' => 'Cancelled Office Expansion',
                'description' => 'A project cancelled before completion.',
                'status' => ProjectStatus::CANCELLED,
                'progress' => 20,
                'start_date' => now()->subMonths(2),
                'end_date' => now()->subMonth(),
            ],
        ];

        $projects = [];

        foreach ($projectScenarios as $key => $scenario) {
            $project = Project::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $scenario['name'],
                ],
                $scenario,
            );

            $project->users()->sync([
                $manager->id => ['role' => ProjectRole::MANAGER->value],
                $member->id => ['role' => ProjectRole::MEMBER->value],
                $viewer->id => ['role' => ProjectRole::VIEWER->value],
            ]);

            $projects[$key] = $project;
        }

        return $projects;
    }

    private function seedTasks(
        Project $project,
        User $manager,
        User $member,
        User $viewer,
    ): void {
        $taskScenarios = [
            TaskStatus::TODO->value => [
                'title' => 'Write launch checklist',
                'description' => 'Prepare the final checklist before launch.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::LOW,
                'due_date' => now()->addDays(10),
                'assignees' => [$member->id],
            ],
            TaskStatus::IN_PROGRESS->value => [
                'title' => 'Build project dashboard',
                'description' => 'Implement the main project dashboard.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::MEDIUM,
                'due_date' => now()->addDays(3),
                'assignees' => [$manager->id, $member->id],
            ],
            TaskStatus::ON_HOLD->value => [
                'title' => 'Integrate calendar provider',
                'description' => 'Waiting for external provider credentials.',
                'status' => TaskStatus::ON_HOLD,
                'priority' => TaskPriority::HIGH,
                'due_date' => now()->addWeeks(2),
                'assignees' => [$viewer->id],
            ],
            TaskStatus::BLOCKED->value => [
                'title' => 'Publish staging release',
                'description' => 'Blocked until the project dashboard is ready.',
                'status' => TaskStatus::BLOCKED,
                'priority' => TaskPriority::EMERGENCY,
                'due_date' => now()->subDay(),
                'assignees' => [$member->id],
            ],
            TaskStatus::REVIEW->value => [
                'title' => 'Review authentication flow',
                'description' => 'The implementation is ready for peer review.',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::HIGH,
                'due_date' => now()->addDay(),
                'assignees' => [$manager->id, $member->id],
            ],
            TaskStatus::DONE->value => [
                'title' => 'Define product requirements',
                'description' => 'The product requirements have been approved.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::MEDIUM,
                'due_date' => now()->subWeek(),
                'assignees' => [$member->id],
            ],
        ];

        $tasks = [];

        foreach ($taskScenarios as $key => $scenario) {
            $task = Task::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'title' => $scenario['title'],
                ],
                [
                    'description' => $scenario['description'],
                    'status' => $scenario['status'],
                    'priority' => $scenario['priority'],
                    'due_date' => $scenario['due_date'],
                ],
            );

            $task->assignees()->sync($scenario['assignees']);
            $tasks[$key] = $task;
        }

        $tasks['in_progress']->dependencies()->sync([$tasks['done']->id]);
        $tasks['blocked']->dependencies()->sync([$tasks['in_progress']->id]);
        $tasks['review']->dependencies()->sync([$tasks['done']->id]);

        TaskNote::query()->updateOrCreate([
            'task_id' => $tasks['in_progress']->id,
            'user_id' => $member->id,
            'content' => 'The first dashboard widgets are ready.',
        ]);

        TaskNote::query()->updateOrCreate([
            'task_id' => $tasks['in_progress']->id,
            'user_id' => $manager->id,
            'content' => 'Please add empty states before requesting review.',
        ]);
    }

    private function findUser(string $email): User
    {
        return User::query()
            ->where('email', $email)
            ->sole();
    }
}
