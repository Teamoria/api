<?php

use App\Enums\FileCategory;
use App\Enums\UploadStatus;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Requests\Upload\UploadRequest;
use App\Models\Project;
use App\Models\Upload;
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

    $upload = Upload::query()->sole();

    expect($upload->project_id)->toBe($project->id)
        ->and($upload->user_id)->toBe($user->id)
        ->and($upload->file_path)->toBe($path)
        ->and($upload->file_name)->toBe('notes.pdf')
        ->and($upload->file_type)->toBe('application/pdf')
        ->and($upload->category)->toBe(FileCategory::DOCUMENT)
        ->and($upload->status)->toBe(UploadStatus::SUCCESS)
        ->and($upload->upload_date)->not->toBeNull();
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
    $user = User::factory()->create();
    $project = Project::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Upload project',
    ]);
    $request = mock(UploadRequest::class, function (MockInterface $mock) use ($files, $project, $user): void {
        $mock->shouldReceive('validated')
            ->once()
            ->with('files')
            ->andReturn($files);
        $mock->shouldReceive('validated')
            ->once()
            ->with('project_id')
            ->andReturn($project->id);
        $mock->shouldReceive('user')
            ->once()
            ->andReturn($user);
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

    expect(Upload::query()->count())->toBe(5)
        ->and(Upload::query()->whereBelongsTo($project)->count())->toBe(5)
        ->and(Upload::query()->whereBelongsTo($user)->count())->toBe(5);
});

it('paginates uploaded files', function () {
    $user = User::factory()->create();
    $project = Project::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Paginated upload project',
    ]);
    $otherProject = Project::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Other upload project',
    ]);

    createUploadsForPagination($project, $user, 12);
    createUploadsForPagination($otherProject, $user, 1);

    Sanctum::actingAs($user);

    $this->getJson(
        route('api.v1.uploads.list', ['page' => 2]),
        uploadApiHeaders(),
    )
        ->assertOk()
        ->assertJsonCount(3, 'data.files')
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonPath('data.pagination.last_page', 2)
        ->assertJsonPath('data.pagination.per_page', 10)
        ->assertJsonPath('data.pagination.total', 13)
        ->assertJsonPath('data.pagination.has_more', false);

    $response = $this->getJson(
        route('api.v1.uploads.list.company', [
            'projectId' => $project->id,
            'page' => 2,
        ]),
        uploadApiHeaders(),
    )
        ->assertOk()
        ->assertJsonCount(2, 'data.files')
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonPath('data.pagination.total', 12)
        ->assertJsonPath('data.pagination.has_more', false);

    expect(collect($response->json('data.files'))->pluck('project_id')->unique()->all())
        ->toBe([$project->id]);
});

function uploadApiHeaders(): array
{
    return [
        'Accept' => 'application/json',
        'x-api-key' => 'test-api-key',
    ];
}

function createUploadsForPagination(Project $project, User $user, int $count): void
{
    foreach (range(1, $count) as $index) {
        Upload::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'file_path' => "uploads/documents/file-{$project->id}-{$index}.pdf",
            'file_name' => "file-{$index}.pdf",
            'file_type' => 'application/pdf',
            'category' => FileCategory::DOCUMENT,
            'file_size' => 1024,
            'status' => UploadStatus::SUCCESS,
            'upload_date' => now(),
        ]);
    }
}
