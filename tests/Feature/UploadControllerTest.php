<?php

use App\Enums\FileCategory;
use App\Enums\ProjectRole;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Enums\UploadVisibility;
use App\Jobs\ProcessUploadJob;
use App\Models\Project;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

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
    Storage::fake('local');
    Queue::fake();
    $user = User::factory()->create();
    $project = Project::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Upload project',
    ]);
    $project->users()->attach($user, [
        'role' => ProjectRole::MEMBER->value,
    ]);

    Sanctum::actingAs($user);

    $response = $this->post(route('api.v1.uploads.store'), [
        'files' => [
            File::create('notes.pdf')->mimeType('application/pdf'),
        ],
        'project_id' => $project->id,
        'scope' => UploadScope::PROJECT->value,
        'visibility' => UploadVisibility::MEMBERS->value,
    ], uploadApiHeaders())
        ->assertCreated();

    $upload = Upload::query()->sole();

    expect($upload->file_path)
        ->toStartWith("uploads/{$user->company_id}/project/documents/notes-")
        ->toEndWith('.pdf')
        ->and($upload->company_id)->toBe($user->company_id)
        ->and($upload->project_id)->toBe($project->id)
        ->and($upload->user_id)->toBe($user->id)
        ->and($upload->scope)->toBe(UploadScope::PROJECT)
        ->and($upload->visibility)->toBe(UploadVisibility::MEMBERS)
        ->and($upload->file_name)->toBe('notes.pdf')
        ->and($upload->file_type)->toBe('application/pdf')
        ->and($upload->category)->toBe(FileCategory::DOCUMENT)
        ->and($upload->status)->toBe(UploadStatus::SUCCESS)
        ->and($upload->upload_date)->not->toBeNull();

    Storage::disk('local')->assertExists($upload->file_path);

    $response
        ->assertJsonPath('data.files.0.id', $upload->id)
        ->assertJsonPath('data.files.0.scope', UploadScope::PROJECT->value)
        ->assertJsonPath('data.files.0.download_url', route('api.v1.uploads.download', $upload));

    Queue::assertPushed(ProcessUploadJob::class, 1);
});

it('stores uploaded files in directories based on their type', function () {
    Storage::fake('local');
    Queue::fake();
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
    $project->users()->attach($user, [
        'role' => ProjectRole::MEMBER->value,
    ]);

    Sanctum::actingAs($user);

    $this->post(route('api.v1.uploads.store'), [
        'files' => $files,
        'project_id' => $project->id,
        'scope' => UploadScope::PROJECT->value,
    ], uploadApiHeaders())
        ->assertCreated()
        ->assertJsonCount(5, 'data.files');

    $paths = Upload::query()->latest('created_at')->pluck('file_path');

    expect($paths)->toHaveCount(5)
        ->and($paths->contains(fn (string $path) => str_contains($path, '/images/')))->toBeTrue()
        ->and($paths->contains(fn (string $path) => str_contains($path, '/videos/')))->toBeTrue()
        ->and($paths->contains(fn (string $path) => str_contains($path, '/audio/')))->toBeTrue()
        ->and($paths->filter(fn (string $path) => str_contains($path, '/documents/notes-')))
        ->toHaveCount(2)
        ->and($paths->unique())->toHaveCount(5);

    Storage::disk('local')->assertExists($paths->all());

    expect(Upload::query()->count())->toBe(5)
        ->and(Upload::query()->whereBelongsTo($project)->count())->toBe(5)
        ->and(Upload::query()->whereBelongsTo($user)->count())->toBe(5);

    Queue::assertPushed(ProcessUploadJob::class, 5);
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
            'project' => $project->id,
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
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'scope' => UploadScope::PROJECT,
            'visibility' => UploadVisibility::PRIVATE,
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
