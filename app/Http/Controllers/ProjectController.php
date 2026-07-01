<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Project\AddProjectMembersRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\ProjectRole;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    private function checkManager(Project $project): void
    {
        $projectUser = $project->users()->find(Auth::user()->id);
        if (! $projectUser || $projectUser->role !== ProjectRole::MANAGER) {
            abort(403, 'You are not authorized to update this project.');
        }
    }

    public function index(Request $request)
    {
        $q = Project::query()->where('company_id', Auth::user()->company_id)->with(['users', 'company']);

        if ($request->has('archived') && $request->archived) {
            $q->onlyTrashed();
        }

        $projects = $q->latest()->paginate(10)->withQueryString();

        return $this->successResponse(
            [
                'projects' => ProjectResource::collection($projects),
                'pagination' => [
                    'current_page' => $projects->currentPage(),
                    'last_page' => $projects->lastPage(),
                    'per_page' => $projects->perPage(),
                    'total' => $projects->total(),
                    'has_more' => $projects->hasMorePages(),
                ],
            ],
            'Projects fetched successfully',
        );
    }

    public function store(StoreProjectRequest $request)
    {
        if (Auth::user()->role === UserRole::COMPANY_MEMBER) {
            return $this->errorResponse(
                'You are not authorized to create a project.',
                403
            );
        }

        $validated = $request->validated();
        $validated['company_id'] = Auth::user()->company_id;
        $project = Project::create($validated);

        return $this->successResponse(
            new ProjectResource($project),
            'Project created successfully.',
            201
        );
    }

    public function show(string $id)
    {
        $project = Project::where('company_id', Auth::user()->company_id)->with(['users', 'company'])->findOrFail($id);

        return $this->successResponse(
            new ProjectResource($project),
            'Project fetched successfully.',
            200
        );
    }

    public function update(UpdateProjectRequest $request, string $id)
    {
        $project = Project::where('company_id', Auth::user()->company_id)->findOrFail($id);

        $this->checkManager($project);
        $validated = $request->validated();
        $project->update($validated);

        return $this->successResponse(
            new ProjectResource($project),
            'Project updated successfully.',
            200
        );
    }

    public function destroy(string $id)
    {
        $project = Project::query()->where('company_id', Auth::user()->company_id)->findOrFail($id);
        $this->checkManager($project);
        $project->delete();

        return $this->successResponse(
            null,
            'Project deleted successfully.',
            200
        );
    }

    public function restore(string $id)
    {
        $project = Project::withTrashed()->whereNotNull('deleted_at')->where('company_id', Auth::user()->company_id)->findOrFail($id);
        $this->checkManager($project);
        $project->restore();

        return $this->successResponse(
            new ProjectResource($project),
            'Project restored successfully.',
            200
        );
    }

    public function forceDelete(string $id)
    {
        $project = Project::withTrashed()->where('company_id', Auth::user()->company_id)->findOrFail($id);
        $this->checkManager($project);
        $project->forceDelete();

        return $this->successResponse(
            null,
            'Project force deleted successfully.',
            200
        );
    }

    public function addMembers(AddProjectMembersRequest $request, string $id)
    {
        $project = Project::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $this->checkManager($project);

        $role = $request->validated('role', ProjectRole::MEMBER->value);

        $syncData = collect($request->validated('user_ids'))
            ->mapWithKeys(fn (string $userId) => [$userId => ['role' => $role]])
            ->all();

        $project->users()->syncWithoutDetaching($syncData);
        $project->load('users');

        return $this->successResponse(
            new ProjectResource($project),
            'Members added to project successfully.',
            200
        );
    }

    public function removeMember(string $id, string $userId)
    {
        $project = Project::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $this->checkManager($project);

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
}
