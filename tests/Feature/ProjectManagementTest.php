<?php

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('allows a project manager to update a project', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);
    $project = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Original project',
        'description' => 'Project description',
        'status' => ProjectStatus::ACTIVE,
        'progress' => 0,
    ]);

    $project->users()->attach($manager, [
        'role' => ProjectRole::MANAGER->value,
    ]);

    Sanctum::actingAs($manager);

    $this->putJson(route('api.v1.projects.update', $project), [
        'name' => 'Updated project',
    ], ['x-api-key' => 'test-api-key'])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.name', 'Updated project');

    expect($project->refresh()->name)->toBe('Updated project');
});
