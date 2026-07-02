<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Project\AddProjectMembersRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {

        $projectsQuery = Project::query()
            ->whereBelongsTo($request->user()->company)
            ->with(['users', 'company']);

        if ($request->boolean('archived')) {
            $projectsQuery->onlyTrashed();
        }

        $projects = $projectsQuery->latest()->paginate(10)->withQueryString();

        return $this->successResponse(
            [
                'projects' => ProjectResource::collection($projects),
                'pagination' => $this->pagination($projects),
            ],
            'Projects fetched successfully',
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        if ($request->user()->role === UserRole::COMPANY_MEMBER) {
            return $this->errorResponse(
                'You are not authorized to create a project.',
                403
            );
        }

        $validated = $request->validated();
        $validated['company_id'] = $request->user()->company_id;
        $project = Project::create($validated);

        return $this->successResponse(
            new ProjectResource($project),
            'Project created successfully.',
            201
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $project = $this->companyProject($request, $id)
            ->load(['users', 'company']);

        return $this->successResponse(
            new ProjectResource($project),
            'Project fetched successfully.',
            200
        );
    }

    public function update(UpdateProjectRequest $request, string $id): JsonResponse
    {
        $project = $this->companyProject($request, $id);

        $this->ensureManager($request->user(), $project);
        $project->update($request->validated());

        return $this->successResponse(
            new ProjectResource($project),
            'Project updated successfully.',
            200
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $project = $this->companyProject($request, $id);
        $this->ensureManager($request->user(), $project);
        $project->delete();

        return $this->successResponse(
            null,
            'Project deleted successfully.',
            200
        );
    }

    public function restore(Request $request, string $id): JsonResponse
    {

        $project = Project::onlyTrashed()
            ->whereBelongsTo($request->user()->company)
            ->findOrFail($id);

        $this->ensureManager($request->user(), $project);
        $project->restore();

        return $this->successResponse(
            new ProjectResource($project),
            'Project restored successfully.',
            200
        );
    }

    public function forceDelete(Request $request, string $id): JsonResponse
    {

        $project = Project::withTrashed()
            ->whereBelongsTo($request->user()->company)
            ->findOrFail($id);

        $this->ensureManager($request->user(), $project);
        $project->forceDelete();

        return $this->successResponse(
            null,
            'Project force deleted successfully.',
            200
        );
    }

    public function addMembers(AddProjectMembersRequest $request, string $id): JsonResponse
    {
        $project = $this->companyProject($request, $id);
        $this->ensureManager($request->user(), $project);

        $role = $request->validated('role', ProjectRole::MEMBER->value);

        $syncData = collect($request->validated('user_ids'))
            ->mapWithKeys(fn(string $userId) => [$userId => ['role' => $role]])
            ->all();

        $project->users()->syncWithoutDetaching($syncData);
        $project->load('users');

        return $this->successResponse(
            new ProjectResource($project),
            'Members added to project successfully.',
            200
        );
    }

    public function removeMember(Request $request, string $id, string $userId): JsonResponse
    {
        $project = $this->companyProject($request, $id);
        $this->ensureManager($request->user(), $project);

        abort_unless(
            $project->users()->where('user_id', $userId)->exists(),
            404,
            'User is not a member of this project.'
        );

        $project->users()->detach($userId);
        $project->load('users');

        return $this->successResponse(
            new ProjectResource($project),
            'Member removed from project successfully.',
            200
        );
    }

    private function companyProject(Request $request, string $id): Project
    {

        return Project::query()
            ->whereBelongsTo($request->user()->company)
            ->findOrFail($id);
    }


    private function ensureManager(User $user, Project $project): void
    {
        $projectMember = $project->users()->find($user->id);

        abort_unless(
            $projectMember?->pivot->role === ProjectRole::MANAGER->value,
            403,
            'You are not authorized to update this project.',
        );
    }
}
