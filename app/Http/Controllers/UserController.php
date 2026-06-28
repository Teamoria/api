<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::with('company');
        if ($request->has('archived') && $request->archived == true) {
            $q->onlyTrashed();
        }
        $users = $q->latest()->paginate(10)->withQueryString();

        return $this->successResponse(
            [
                'users' => UserResource::collection($users),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'has_more' => $users->hasMorePages(),
                ],
            ],
            'Users fetched successfully',
        );
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();
        $user = User::create($validated->all());

        return $this->successResponse(
            new UserResource($user),
            'User created successfully.',
            201
        );
    }

    public function show(User $user)
    {
        return $this->successResponse(
            new UserResource($user),
            'User fetched successfully.',
            200
        );
    }

    public function update(UpdateUserRequest $request, string $id)
    {
        $validated = $request->validated();
        $user = User::findOrFail($id);
        $user->update($validated->all());

        return $this->successResponse(
            new UserResource($user),
            'User updated successfully.',
            200
        );
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return $this->successResponse(
            null,
            'User deleted successfully.',
            200
        );
    }

    public function restore(string $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return $this->successResponse(
            new UserResource($user),
            'User restored successfully.',
            200
        );
    }

    public function forceDelete(string $id)
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
