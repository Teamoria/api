<?php

use App\Enums\FileCategory;
use App\Http\Controllers\UploadController;
use App\Http\Requests\Upload\UploadRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('requires authentication to access the upload endpoint', function () {
    $this->postJson(
        route('api.v1.uploads.store'),
        [],
        uploadApiHeaders(),
    )->assertUnauthorized();
});

it('uploads files through the api endpoint', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $project = Project::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Upload project',
    ]);

    Sanctum::actingAs($user);

    $response = $this->post(route('api.v1.uploads.store'), [
        'files' => [
            File::create('notes.pdf')->mimeType('application/pdf'),
        ],
        'project_id' => $project->id,
        'category' => FileCategory::DOCUMENT->value,
    ], uploadApiHeaders())
        ->assertCreated();

    $path = $response->json('data.files.0.path');

    expect($path)
        ->toStartWith('uploads/documents/notes-')
        ->toEndWith('.pdf');

    Storage::disk('public')->assertExists($path);
});

it('stores uploaded files in directories based on their type', function () {
    Storage::fake('public');

    $files = [
        File::create('photo.jpg')->mimeType('image/jpeg'),
        File::create('meeting.mp4')->mimeType('video/mp4'),
        File::create('recording.mp3')->mimeType('audio/mpeg'),
        File::create('notes.pdf')->mimeType('application/pdf'),
        File::create('notes.pdf')->mimeType('application/pdf'),
    ];
    $request = mock(UploadRequest::class, function (MockInterface $mock) use ($files): void {
        $mock->shouldReceive('validated')
            ->once()
            ->with('files')
            ->andReturn($files);
    });

    $response = (new UploadController)->upload($request);
    $uploadedFiles = $response->getData(true)['data']['files'];

    expect($response->getStatusCode())->toBe(201)
        ->and($uploadedFiles)->toHaveCount(5)
        ->and($uploadedFiles[0]['path'])->toStartWith('uploads/images/')
        ->and($uploadedFiles[1]['path'])->toStartWith('uploads/videos/')
        ->and($uploadedFiles[2]['path'])->toStartWith('uploads/audio/')
        ->and($uploadedFiles[3]['path'])->toStartWith('uploads/documents/notes-')
        ->and($uploadedFiles[3]['path'])->toEndWith('.pdf')
        ->and($uploadedFiles[4]['path'])->toStartWith('uploads/documents/notes-')
        ->and($uploadedFiles[4]['path'])->toEndWith('.pdf')
        ->and($uploadedFiles[3]['path'])->not->toBe($uploadedFiles[4]['path']);

    Storage::disk('public')->assertExists(
        array_column($uploadedFiles, 'path'),
    );
});

function uploadApiHeaders(): array
{
    return [
        'Accept' => 'application/json',
        'x-api-key' => 'test-api-key',
    ];
}
