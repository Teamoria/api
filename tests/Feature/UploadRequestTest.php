<?php

use App\Http\Requests\Upload\UploadRequest;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Support\Facades\Validator;

it('accepts supported files at their maximum size', function (
    string $name,
    string $mimeType,
    int $sizeInKilobytes,
) {
    $file = TestingFile::create($name)
        ->mimeType($mimeType)
        ->size($sizeInKilobytes);
    $rules = (new UploadRequest)->rules();

    $validator = Validator::make(
        ['files' => [$file]],
        [
            'files' => $rules['files'],
            'files.*' => $rules['files.*'],
        ],
    );

    expect($validator->errors()->has('files.0'))->toBeFalse();
})->with([
    'plain text document' => ['document.txt', 'text/plain', 20 * 1024],
    'PDF document' => ['document.pdf', 'application/pdf', 20 * 1024],
    'Word document' => ['document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 20 * 1024],
    'OpenDocument document' => ['document.odt', 'application/vnd.oasis.opendocument.text', 20 * 1024],
    'image' => ['photo.avif', 'image/avif', 20 * 1024],
    'audio' => ['track.flac', 'audio/flac', 20 * 1024],
    'video' => ['clip.webm', 'video/webm', 100 * 1024],
]);

it('rejects non-video files larger than 20 megabytes', function (
    string $name,
    string $mimeType,
) {
    $file = TestingFile::create($name)
        ->mimeType($mimeType)
        ->size((20 * 1024) + 1);
    $rules = (new UploadRequest)->rules();

    $validator = Validator::make(
        ['files' => [$file]],
        [
            'files' => $rules['files'],
            'files.*' => $rules['files.*'],
        ],
    );

    expect($validator->errors()->has('files.0'))->toBeTrue();
})->with([
    'document' => ['document.pdf', 'application/pdf'],
    'image' => ['photo.png', 'image/png'],
    'audio' => ['track.mp3', 'audio/mpeg'],
]);

it('rejects executable and script files', function (string $name, string $mimeType) {
    $file = TestingFile::create($name)
        ->mimeType($mimeType)
        ->size(1);
    $rules = (new UploadRequest)->rules();

    $validator = Validator::make(
        ['files' => [$file]],
        [
            'files' => $rules['files'],
            'files.*' => $rules['files.*'],
        ],
    );

    expect($validator->errors()->has('files.0'))->toBeTrue();
})->with([
    'executable' => ['installer.exe', 'application/x-dosexec'],
    'shell script' => ['script.sh', 'text/plain'],
    'executable renamed as text' => ['malware.txt', 'application/x-dosexec'],
    'JavaScript file' => ['script.js', 'text/javascript'],
]);

it('rejects video files larger than 100 megabytes', function () {
    $file = TestingFile::create('clip.mp4')
        ->mimeType('video/mp4')
        ->size((100 * 1024) + 1);
    $rules = (new UploadRequest)->rules();

    $validator = Validator::make(
        ['files' => [$file]],
        [
            'files' => $rules['files'],
            'files.*' => $rules['files.*'],
        ],
    );

    expect($validator->errors()->has('files.0'))->toBeTrue();
});
