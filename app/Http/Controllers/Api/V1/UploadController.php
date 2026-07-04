<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileCategory;
use App\Enums\UploadAccessLevel;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Enums\UploadVisibility;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Upload\GrantUploadAccessRequest;
use App\Http\Requests\Upload\ListUploadsRequest;
use App\Http\Requests\Upload\UploadRequest;
use App\Http\Resources\UploadResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    private const string UPLOADS_DIRECTORY = 'uploads';

    public function upload(UploadRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $scope = UploadScope::from($validated['scope']);
        $visibility = UploadVisibility::tryFrom($validated['visibility'] ?? '')
            ?? UploadVisibility::PRIVATE;
        $project = isset($validated['project_id'])
            ? Project::query()->findOrFail($validated['project_id'])
            : null;
        $task = isset($validated['task_id'])
            ? Task::query()->with('project')->findOrFail($validated['task_id'])
            : null;

        Gate::authorize('create', [Upload::class, $scope, $project, $task]);

        $user = $request->user();
        $companyId = $project?->company_id
            ?? ($user->role === UserRole::ADMIN ? ($validated['company_id'] ?? null) : $user->company_id);

        abort_if($companyId === null, 403, 'A company is required to upload files.');

        $uploads = array_map(
            fn (UploadedFile $file): Upload => $this->storeFile(
                file: $file,
                user: $user,
                companyId: $companyId,
                scope: $scope,
                visibility: $visibility,
                project: $project,
                task: $task,
                sharedUserIds: $validated['shared_with_user_ids'] ?? [],
            ),
            $validated['files'],
        );

        return $this->successResponse(
            [
                'files' => UploadResource::collection($uploads)->resolve($request),
            ],
            'Files uploaded successfully.',
            201
        );
    }

    /**
     * @param  array<int, string>  $sharedUserIds
     */
    private function storeFile(
        UploadedFile $file,
        User $user,
        string $companyId,
        UploadScope $scope,
        UploadVisibility $visibility,
        ?Project $project,
        ?Task $task,
        array $sharedUserIds,
    ): Upload {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $category = FileCategory::fromMimeType($mimeType);
        $path = $file->storeAs(
            implode('/', [
                self::UPLOADS_DIRECTORY,
                $companyId,
                $scope->value,
                $this->directoryFor($category),
            ]),
            $this->uniqueFileName($file),
            'local',
        );

        if ($path === false) {
            abort(500, 'File upload failed.');
        }

        $upload = Upload::create([
            'company_id' => $companyId,
            'project_id' => $project?->id,
            'task_id' => $task?->id,
            'user_id' => $user->id,
            'scope' => $scope,
            'visibility' => $visibility,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $mimeType,
            'category' => $category,
            'file_size' => $file->getSize() ?: 0,
            'status' => UploadStatus::SUCCESS,
            'upload_date' => now(),
        ]);

        if ($sharedUserIds !== []) {
            $upload->sharedUsers()->attach(
                collect($sharedUserIds)
                    ->mapWithKeys(fn (string $userId): array => [
                        $userId => [
                            'access_level' => UploadAccessLevel::VIEW->value,
                            'granted_by' => $user->id,
                        ],
                    ]),
            );
        }

        return $upload->load(['user', 'sharedUsers']);
    }

    private function uniqueFileName(UploadedFile $file): string
    {
        $originalName = (string) Str::of($file->getClientOriginalName())
            ->basename()
            ->beforeLast('.')
            ->replaceMatches('/[^\pL\pN._-]+/u', '-')
            ->trim('-_.')
            ->limit(100, '');
        $safeName = $originalName !== '' ? $originalName : 'file';
        $extension = $file->extension();

        return $safeName.'-'.Str::ulid().($extension ? '.'.$extension : '');
    }

    private function directoryFor(FileCategory $category): string
    {
        return match ($category) {
            FileCategory::IMAGE => 'images',
            FileCategory::VIDEO => 'videos',
            FileCategory::AUDIO => 'audio',
            FileCategory::DOCUMENT => 'documents',
        };
    }

    public function index(ListUploadsRequest $request): JsonResponse
    {
        $files = $this->visibleUploadsQuery($request)
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return $this->uploadsResponse($request, $files);
    }

    public function mine(ListUploadsRequest $request): JsonResponse
    {
        $files = Upload::query()
            ->whereBelongsTo($request->user())
            ->with('user')
            ->when(
                $request->validated('scope'),
                fn (Builder $query, string $scope) => $query->where('scope', $scope),
            )
            ->when(
                $request->validated('visibility'),
                fn (Builder $query, string $visibility) => $query->where('visibility', $visibility),
            )
            ->when(
                $request->validated('project_id'),
                fn (Builder $query, string $projectId) => $query->where('project_id', $projectId),
            )
            ->when(
                $request->validated('task_id'),
                fn (Builder $query, string $taskId) => $query->where('task_id', $taskId),
            )
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return $this->uploadsResponse($request, $files);
    }

    public function listUploadedFiles(ListUploadsRequest $request, Project $project): JsonResponse
    {
        if ($request->user()->role !== UserRole::ADMIN
            && $request->user()->company_id !== $project->company_id) {
            throw ApiException::notFound('Project');
        }

        $files = $this->visibleUploadsQuery($request)
            ->whereBelongsTo($project)
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return $this->uploadsResponse($request, $files);
    }

    public function show(Request $request, Upload $upload): JsonResponse
    {
        Gate::authorize('view', $upload);

        $upload->load('user');

        if ($request->user()->can('share', $upload)) {
            $upload->load('sharedUsers');
        }

        return $this->successResponse(
            new UploadResource($upload),
            'File fetched successfully.',
        );
    }

    public function download(Upload $upload): StreamedResponse
    {
        Gate::authorize('download', $upload);

        abort_unless(
            Storage::disk('local')->exists($upload->file_path),
            404,
            'The stored file was not found.',
        );

        return Storage::disk('local')->download(
            $upload->file_path,
            $upload->file_name,
            ['Content-Type' => $upload->file_type],
        );
    }

    public function destroy(Upload $upload): JsonResponse
    {
        Gate::authorize('delete', $upload);

        Storage::disk('local')->delete($upload->file_path);
        $upload->delete();

        return $this->successResponse(
            null,
            'File deleted successfully.',
        );
    }

    public function grantAccess(
        GrantUploadAccessRequest $request,
        Upload $upload,
    ): JsonResponse {
        $accessLevel = $request->validated('access_level');
        $grantedBy = $request->user()->id;
        $permissions = collect($request->validated('user_ids'))
            ->mapWithKeys(fn (string $userId): array => [
                $userId => [
                    'access_level' => $accessLevel,
                    'granted_by' => $grantedBy,
                ],
            ])
            ->all();

        $upload->sharedUsers()->syncWithoutDetaching($permissions);

        return $this->successResponse(
            new UploadResource($upload->load(['user', 'sharedUsers'])),
            'File access granted successfully.',
        );
    }

    public function revokeAccess(
        Request $request,
        Upload $upload,
        User $user,
    ): JsonResponse {
        Gate::authorize('share', $upload);

        abort_unless(
            $upload->sharedUsers()->whereKey($user->id)->exists(),
            404,
            'The user does not have explicit access to this file.',
        );

        $upload->sharedUsers()->detach($user);

        return $this->successResponse(
            new UploadResource($upload->load(['user', 'sharedUsers'])),
            'File access revoked successfully.',
        );
    }

    /**
     * @return Builder<Upload>
     */
    private function visibleUploadsQuery(ListUploadsRequest $request): Builder
    {
        return Upload::query()
            ->visibleTo($request->user())
            ->with('user')
            ->when(
                $request->validated('scope'),
                fn (Builder $query, string $scope) => $query->where('scope', $scope),
            )
            ->when(
                $request->validated('visibility'),
                fn (Builder $query, string $visibility) => $query->where('visibility', $visibility),
            )
            ->when(
                $request->validated('project_id'),
                fn (Builder $query, string $projectId) => $query->where('project_id', $projectId),
            )
            ->when(
                $request->validated('task_id'),
                fn (Builder $query, string $taskId) => $query->where('task_id', $taskId),
            )
            ->latest();
    }

    private function uploadsResponse(
        Request $request,
        LengthAwarePaginator $files,
    ): JsonResponse {
        return $this->successResponse(
            [
                'files' => UploadResource::collection($files->items())->resolve($request),
                'pagination' => $this->pagination($files),
            ],
            'Files listed successfully.',
        );
    }
}
