<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileCategory;
use App\Enums\UploadStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Upload\UploadRequest;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    private const string UPLOADS_DIRECTORY = 'uploads';

    public function upload(UploadRequest $request): JsonResponse
    {
        $projectId = $request->validated('project_id');
        $userId = (string) $request->user()->getAuthIdentifier();
        $files = array_map(
            fn (UploadedFile $file): array => $this->storeFile($file, $projectId, $userId),
            $request->validated('files'),
        );

        return $this->successResponse(
            [
                'files' => $files,
            ],
            'Files uploaded successfully.',
            201
        );
    }

    /**
     * @return array{path: string, url: string, category: string}
     */
    private function storeFile(UploadedFile $file, string $projectId, string $userId): array
    {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $category = FileCategory::fromMimeType($mimeType);
        $path = $file->storeAs(
            self::UPLOADS_DIRECTORY.'/'.$this->directoryFor($category),
            $this->uniqueFileName($file),
            'public',
        );

        if ($path === false) {
            abort(500, 'File upload failed.');
        }

        Upload::create([
            'project_id' => $projectId,
            'user_id' => $userId,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $mimeType,
            'category' => $category,
            'file_size' => $file->getSize() ?: 0,
            'status' => UploadStatus::SUCCESS,
            'upload_date' => now(),
        ]);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'category' => $category->value,
        ];
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

    public function listUploadedFiles(string $projectId): JsonResponse
    {
        $files = Upload::query()
            ->where('project_id', $projectId)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return $this->successResponse(
            [
                'files' => $files->items(),
                'pagination' => $this->pagination($files),
            ],
            'Files listed successfully.'
        );
    }

    public function index(): JsonResponse
    {
        $files = Upload::query()
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return $this->successResponse(
            [
                'files' => $files->items(),
                'pagination' => $this->pagination($files),
            ],
            'Files listed successfully.'
        );
    }
}
