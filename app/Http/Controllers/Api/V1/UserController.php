<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usersQuery = User::query()->with('company');

        if ($request->boolean('archived')) {
            $usersQuery->onlyTrashed();
        }

        $users = $usersQuery->latest()->paginate(10)->withQueryString();

        return $this->successResponse(
            [
                'users' => UserResource::collection($users),
                'pagination' => $this->pagination($users),
            ],
            'Users fetched successfully',
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->successResponse(
            new UserResource($user),
            'User created successfully.',
            201
        );
    }

    public function show(User $user): JsonResponse
    {
        return $this->successResponse(
            new UserResource($user),
            'User fetched successfully.',
            200
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return $this->successResponse(
            new UserResource($user),
            'User updated successfully.',
            200
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        return $this->successResponse(
            null,
            'User deleted successfully.',
            200
        );
    }

    public function restore(string $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return $this->successResponse(
            new UserResource($user),
            'User restored successfully.',
            200
        );
    }

    public function forceDelete(string $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->forceDelete();

        return $this->successResponse(
            null,
            'User force deleted successfully.',
            200
        );
    }
}
