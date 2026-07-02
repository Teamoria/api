<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['old_password'] === $validated['new_password']) {
            return $this->errorResponse(
                'Old and new passwords cannot be the same.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $user = $request->user();

        if (! Hash::check($validated['old_password'], $user->password)) {
            return $this->errorResponse(
                'Invalid password.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $user->update(['password' => $validated['new_password']]);

        return $this->successResponse(
            [],
            'Password reset successfully.',
            Response::HTTP_OK
        );
    }
}
