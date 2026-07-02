<?php

namespace App\Http\Controllers;

use App\Enums\FileCategory;
use App\Http\Requests\Upload\UploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController
{
    private const string UPLOADS_DIRECTORY = 'uploads';

    public function upload(UploadRequest $request): JsonResponse
    {
        $files = array_map(
            fn (UploadedFile $file): array => $this->storeFile($file),
            $request->validated('files'),
        );

        return response()->json([
            'message' => 'Files uploaded successfully.',
            'data' => [
                'files' => $files,
            ],
        ], 201);
    }

    /**
     * @return array{path: string, url: string, category: string}
     */
    private function storeFile(UploadedFile $file): array
    {
        $category = FileCategory::fromMimeType(
            $file->getMimeType() ?? 'application/octet-stream',
        );
        $path = $file->storeAs(
            self::UPLOADS_DIRECTORY.'/'.$this->directoryFor($category),
            $this->uniqueFileName($file),
            'public',
        );

        if ($path === false) {
            abort(500, 'File upload failed.');
        }

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
}
