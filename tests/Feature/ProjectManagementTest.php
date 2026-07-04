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

    $this->putJson(route('api.v1.company.projects.update', $project), [
        'name' => 'Updated project',
    ], ['x-api-key' => 'test-api-key'])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.name', 'Updated project');

    expect($project->refresh()->name)->toBe('Updated project');
});

it('lists only projects assigned to a company member', function () {
    $company = Company::factory()->create();
    $member = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MEMBER,
    ]);
    $assignedProject = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Assigned project',
    ]);
    Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Hidden project',
    ]);
    $assignedProject->users()->attach($member, [
        'role' => ProjectRole::MEMBER->value,
    ]);

    Sanctum::actingAs($member);

    $this->getJson(
        route('api.v1.company.projects.index'),
        ['x-api-key' => 'test-api-key'],
    )
        ->assertOk()
        ->assertJsonCount(1, 'data.projects')
        ->assertJsonPath('data.projects.0.id', $assignedProject->id);
});

it('allows a company owner to appoint a project manager', function () {
    $company = Company::factory()->create();
    $owner = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_OWNER,
    ]);
    $newManager = User::factory()->for($company)->create([
        'role' => UserRole::COMPANY_MANAGER,
    ]);
    $project = Project::query()->create([
        'company_id' => $company->id,
        'name' => 'Managed project',
    ]);

    Sanctum::actingAs($owner);

    $this->postJson(
        route('api.v1.company.projects.members.add', $project),
        [
            'user_ids' => [$newManager->id],
            'role' => ProjectRole::MANAGER->value,
        ],
        ['x-api-key' => 'test-api-key'],
    )->assertOk();

    expect($project->users()
        ->whereKey($newManager->id)
        ->wherePivot('role', ProjectRole::MANAGER->value)
        ->exists())->toBeTrue();
});
