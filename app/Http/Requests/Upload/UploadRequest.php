<?php

namespace App\Http\Requests\Upload;

use App\Enums\UploadScope;
use App\Enums\UploadVisibility;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UploadRequest extends FormRequest
{
    private const int DEFAULT_MAX_SIZE_IN_KILOBYTES = 20 * 1024;

    private const int VIDEO_MAX_SIZE_IN_KILOBYTES = 100 * 1024;

    private const array DOCUMENT_EXTENSIONS = [
        'abw',
        'azw',
        'azw3',
        'csv',
        'djvu',
        'doc',
        'docx',
        'epub',
        'json',
        'key',
        'log',
        'markdown',
        'md',
        'mobi',
        'numbers',
        'odp',
        'ods',
        'odt',
        'pages',
        'pdf',
        'ppt',
        'pptx',
        'rtf',
        'tex',
        'tsv',
        'txt',
        'vsd',
        'vsdx',
        'wpd',
        'wps',
        'xls',
        'xlsx',
        'xml',
        'xps',
        'yaml',
        'yml',
        'txt',
    ];

    private const array MEDIA_MIME_TYPES = [
        'audio/*',
        'image/*',
        'video/*',
    ];

    public function rules(): array
    {
        $scope = UploadScope::tryFrom($this->string('scope')->toString());
        $visibility = UploadVisibility::tryFrom($this->string('visibility')->toString());
        $isPlatformAdmin = $this->user()?->role === UserRole::ADMIN;
        $usesProject = in_array($scope, [UploadScope::PROJECT, UploadScope::TASK], true);

        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => Rule::forEach(function (mixed $file): array {
                $mimeType = $file instanceof UploadedFile
                    ? $file->getMimeType()
                    : null;
                $isMedia = $mimeType !== null
                    && (
                        str_starts_with($mimeType, 'audio/')
                        || str_starts_with($mimeType, 'image/')
                        || str_starts_with($mimeType, 'video/')
                    );
                $maximumSize = $file instanceof UploadedFile
                    && str_starts_with($mimeType ?? '', 'video/')
                    ? self::VIDEO_MAX_SIZE_IN_KILOBYTES
                    : self::DEFAULT_MAX_SIZE_IN_KILOBYTES;
                $fileRule = $isMedia
                    ? File::types(self::MEDIA_MIME_TYPES)
                    : File::types(self::DOCUMENT_EXTENSIONS)
                        ->extensions(self::DOCUMENT_EXTENSIONS);

                return [
                    $fileRule->max($maximumSize),
                ];
            }),
            'scope' => [
                'required',
                Rule::enum(UploadScope::class)
                    ->when(
                        $isPlatformAdmin,
                        fn ($rule) => $rule->except(UploadScope::PERSONAL),
                    ),
            ],
            'visibility' => ['sometimes', Rule::enum(UploadVisibility::class)],
            'company_id' => [
                Rule::requiredIf($isPlatformAdmin && $scope === UploadScope::COMPANY),
                Rule::prohibitedIf(! $isPlatformAdmin || $scope !== UploadScope::COMPANY),
                'uuid',
                Rule::exists('companies', 'id')->whereNull('deleted_at'),
            ],
            'project_id' => [
                Rule::requiredIf($usesProject),
                Rule::prohibitedIf(! $usesProject),
                'uuid',
                Rule::exists('projects', 'id')->whereNull('deleted_at'),
            ],
            'task_id' => [
                Rule::requiredIf($scope === UploadScope::TASK),
                Rule::prohibitedIf($scope !== UploadScope::TASK),
                'uuid',
                Rule::exists('tasks', 'id')->whereNull('deleted_at'),
            ],
            'shared_with_user_ids' => [
                Rule::requiredIf($visibility === UploadVisibility::SELECTED),
                Rule::prohibitedIf($visibility !== UploadVisibility::SELECTED),
                'array',
                'min:1',
            ],
            'shared_with_user_ids.*' => [
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

                $scope = UploadScope::from($this->string('scope')->toString());
                $visibility = UploadVisibility::tryFrom($this->string('visibility')->toString())
                    ?? UploadVisibility::PRIVATE;

                if ($scope === UploadScope::PERSONAL && $visibility === UploadVisibility::MEMBERS) {
                    $validator->errors()->add(
                        'visibility',
                        'Personal files cannot be visible to all company members.',
                    );

                    return;
                }

                $project = $this->project();
                $task = $this->task();

                if ($task !== null && $project?->id !== $task->project_id) {
                    $validator->errors()->add(
                        'task_id',
                        'The selected task does not belong to the selected project.',
                    );

                    return;
                }

                $sharedUserIds = $this->array('shared_with_user_ids');

                if ($sharedUserIds === []) {
                    return;
                }

                $companyId = ($project?->company_id
                    ?? $this->string('company_id')->toString())
                    ?: $this->user()?->company_id;

                $companyUsersCount = User::query()
                    ->whereKey($sharedUserIds)
                    ->where('company_id', $companyId)
                    ->count();

                if ($companyUsersCount !== count($sharedUserIds)) {
                    $validator->errors()->add(
                        'shared_with_user_ids',
                        'All shared users must belong to the file company.',
                    );

                    return;
                }

                if ($project !== null) {
                    $projectUsersCount = $project->users()
                        ->whereKey($sharedUserIds)
                        ->count();

                    if ($projectUsersCount !== count($sharedUserIds)) {
                        $validator->errors()->add(
                            'shared_with_user_ids',
                            'Project and task files can only be shared with project members.',
                        );
                    }
                }
            },
        ];
    }

    private function project(): ?Project
    {
        $projectId = $this->string('project_id')->toString();

        return $projectId === '' ? null : Project::query()->find($projectId);
    }

    private function task(): ?Task
    {
        $taskId = $this->string('task_id')->toString();

        return $taskId === '' ? null : Task::query()->find($taskId);
    }
}
