<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        return $this->successResponse(
            new UserResource($user),
            'Profile retrieved successfully',
            200
        );
    }

    public function update(UpdateUserRequest $request)
    {
        $user = Auth::user();
        $user->update($request->safe()->except(['company_id', 'role', 'status']));

        return $this->successResponse(
            new UserResource($user->fresh()),
            'Profile updated successfully',
        );
    }
}
