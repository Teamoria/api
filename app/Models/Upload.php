<?php

namespace App\Models;

use App\Enums\FileCategory;
use App\Enums\ProcessingStatus;
use App\Enums\ProjectRole;
use App\Enums\UploadAccessLevel;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Enums\UploadVisibility;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Upload extends Model
{
    use HasUuids;

    protected $attributes = [
        'visibility' => UploadVisibility::PRIVATE->value,
        'status' => UploadStatus::PENDING->value,
        'processing_status' => ProcessingStatus::QUEUED->value,
        'file_size' => 0,
    ];

    protected $fillable = [
        'company_id',
        'project_id',
        'task_id',
        'user_id',
        'scope',
        'visibility',
        'file_path',
        'file_name',
        'file_type',
        'category',
        'file_size',
        'status',
        'processing_status',
        'processing_error',
        'upload_date',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'category' => FileCategory::class,
            'scope' => UploadScope::class,
            'visibility' => UploadVisibility::class,
            'status' => UploadStatus::class,
            'processing_status' => ProcessingStatus::class,
            'upload_date' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'upload_permissions')
            ->withPivot(['access_level', 'granted_by'])
            ->withTimestamps();
    }

    public function meetingSummary(): HasOne
    {
        return $this->hasOne(MeetingSummary::class);
    }

    public function knowledgeChunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    /**
     * @param  Builder<Upload>  $query
     * @return Builder<Upload>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::ADMIN) {
            return $query;
        }

        if ($user->company_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereBelongsTo($user->company)
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->whereBelongsTo($user)
                    ->orWhereHas(
                        'sharedUsers',
                        fn (Builder $query) => $query->whereKey($user->id),
                    )
                    ->orWhere(function (Builder $query) use ($user): void {
                        $query
                            ->where('visibility', UploadVisibility::MEMBERS)
                            ->where(function (Builder $query) use ($user): void {
                                $query
                                    ->where('scope', UploadScope::COMPANY)
                                    ->orWhere(function (Builder $query) use ($user): void {
                                        $query
                                            ->where('scope', UploadScope::PROJECT)
                                            ->whereHas(
                                                'project.users',
                                                fn (Builder $query) => $query->whereKey($user->id),
                                            );
                                    })
                                    ->orWhere(function (Builder $query) use ($user): void {
                                        $query
                                            ->where('scope', UploadScope::TASK)
                                            ->where(function (Builder $query) use ($user): void {
                                                $query
                                                    ->whereHas(
                                                        'task.assignees',
                                                        fn (Builder $query) => $query->whereKey($user->id),
                                                    )
                                                    ->orWhereHas(
                                                        'project.managers',
                                                        fn (Builder $query) => $query->whereKey($user->id),
                                                    );
                                            });
                                    });
                            });
                    });
            });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if ($user->company_id === null || $user->company_id !== $this->company_id) {
            return false;
        }

        if ($this->user_id === $user->id || $this->sharedUsers()->whereKey($user->id)->exists()) {
            return true;
        }

        if ($this->visibility !== UploadVisibility::MEMBERS) {
            return false;
        }

        return match ($this->scope) {
            UploadScope::COMPANY => true,
            UploadScope::PROJECT => $this->project?->users()->whereKey($user->id)->exists() === true,
            UploadScope::TASK => $this->task?->assignees()->whereKey($user->id)->exists() === true
                || $this->isProjectManager($user),
            UploadScope::PERSONAL => false,
        };
    }

    public function isManageableBy(User $user): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if ($user->company_id === null || $user->company_id !== $this->company_id) {
            return false;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->sharedUsers()
            ->whereKey($user->id)
            ->wherePivot('access_level', UploadAccessLevel::MANAGE->value)
            ->exists()) {
            return true;
        }

        if ($this->visibility !== UploadVisibility::MEMBERS) {
            return false;
        }

        return match ($this->scope) {
            UploadScope::COMPANY => in_array($user->role, [
                UserRole::COMPANY_OWNER,
                UserRole::COMPANY_MANAGER,
            ], true),
            UploadScope::PROJECT, UploadScope::TASK => $this->isProjectManager($user),
            UploadScope::PERSONAL => false,
        };
    }

    private function isProjectManager(User $user): bool
    {
        return $this->project?->users()
            ->whereKey($user->id)
            ->wherePivot('role', ProjectRole::MANAGER->value)
            ->exists() === true;
    }
}
