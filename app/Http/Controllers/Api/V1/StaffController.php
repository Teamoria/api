<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usersQuery = User::whereBelongsTo($request->user()->company);

        if ($request->boolean('archived')) {
            $usersQuery->onlyTrashed();
        }

        if ($request->filled('roles')) {
            $usersQuery->whereIn('role', $request->array('roles'));
        }

        if ($request->filled('statuses')) {
            $usersQuery->whereIn('status', $request->array('statuses'));
        }

        $users = $usersQuery->latest()->paginate(10)->withQueryString();

        return $this->successResponse(
            [
                'users' => UserResource::collection($users),
                'company' => new CompanyResource($request->user()->company),
                'pagination' => $this->pagination($users),
            ],
            'Users fetched successfully',
        );
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['company_id'] = $request->user()->company_id;
        $user = User::create($validated);

        return $this->successResponse(
            new UserResource($user),
            'User created successfully.',
            201
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $this->companyUser($request, $id);

        return $this->successResponse(
            new UserResource($user),
            'User fetched successfully.',
            200
        );
    }

    public function update(UpdateStaffRequest $request, string $id): JsonResponse
    {
        $user = $this->companyUser($request, $id);
        $user->update($request->validated());

        return $this->successResponse(
            new UserResource($user),
            'User updated successfully.',
            200
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $this->companyUser($request, $id);
        $user->delete();

        return $this->successResponse(
            null,
            'User deleted successfully.',
            200
        );
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $user = User::onlyTrashed()
            ->whereBelongsTo($request->user()->company)
            ->findOrFail($id);
        $user->restore();

        return $this->successResponse(
            new UserResource($user),
            'User restored successfully.',
            200
        );
    }

    public function forceDelete(Request $request, string $id): JsonResponse
    {
        $user = User::withTrashed()
            ->whereBelongsTo($request->user()->company)
            ->findOrFail($id);
        $user->forceDelete();

        return $this->successResponse(
            null,
            'User force deleted successfully.',
            200
        );
    }

    private function companyUser(Request $request, string $id): User
    {
        return User::query()
            ->whereBelongsTo($request->user()->company)
            ->findOrFail($id);
    }
}
