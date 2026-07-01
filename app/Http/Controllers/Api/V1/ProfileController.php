<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()),
            'Profile retrieved successfully',
            200
        );
    }

    public function update(UpdateUserRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->safe()->except(['company_id', 'role', 'status']));

        return $this->successResponse(
            new UserResource($user->fresh()),
            'Profile updated successfully',
        );
    }
}
