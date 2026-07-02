<?php

namespace App\Http\Requests\Upload;

use App\Enums\FileCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

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
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'category' => ['required', Rule::enum(FileCategory::class)],
        ];
    }
}
