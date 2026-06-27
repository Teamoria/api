<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request)
    {
        $validated = $request->validated();

        if ($validated['old_password'] === $validated['new_password']) {
            return $this->errorResponse(
                'Old and new passwords cannot be the same.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $user = Auth::user();

        if (! Hash::check($validated['old_password'], $user->password)) {
            return $this->errorResponse(
                'Invalid password.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return $this->successResponse(
            [],
            'Password reset successfully.',
            Response::HTTP_OK
        );
    }
}
