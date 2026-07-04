<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\UploadScope;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Upload;
use App\Models\User;

class UploadPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role === UserRole::ADMIN && $ability !== 'create'
            ? true
            : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function view(User $user, Upload $upload): bool
    {
        return $upload->isVisibleTo($user);
    }

    public function create(
        User $user,
        UploadScope $scope,
        ?Project $project = null,
        ?Task $task = null,
    ): bool {
        if ($user->role === UserRole::ADMIN) {
            return match ($scope) {
                UploadScope::COMPANY => true,
                UploadScope::PROJECT => $project !== null,
                UploadScope::TASK => $task !== null,
                UploadScope::PERSONAL => false,
            };
        }

        if ($user->company_id === null) {
            return false;
        }

        return match ($scope) {
            UploadScope::PERSONAL => true,
            UploadScope::COMPANY => in_array($user->role, [
                UserRole::COMPANY_OWNER,
                UserRole::COMPANY_MANAGER,
            ], true),
            UploadScope::PROJECT => $project !== null
                && $project->company_id === $user->company_id
                && $project->users()
                    ->whereKey($user->id)
                    ->wherePivotIn('role', [
                        ProjectRole::MANAGER->value,
                        ProjectRole::MEMBER->value,
                    ])
                    ->exists(),
            UploadScope::TASK => $task !== null
                && $task->project->company_id === $user->company_id
                && (
                    $task->assignees()->whereKey($user->id)->exists()
                    || $task->project->users()
                        ->whereKey($user->id)
                        ->wherePivot('role', ProjectRole::MANAGER->value)
                        ->exists()
                ),
        };
    }

    public function download(User $user, Upload $upload): bool
    {
        return $this->view($user, $upload);
    }

    public function update(User $user, Upload $upload): bool
    {
        return $upload->isManageableBy($user);
    }

    public function delete(User $user, Upload $upload): bool
    {
        return $upload->isManageableBy($user);
    }

    public function share(User $user, Upload $upload): bool
    {
        return $upload->isManageableBy($user);
    }
}
