<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::query()->where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse(
                message: 'Invalid credentials.',
                code: Response::HTTP_UNAUTHORIZED,
                errorCode: 'INVALID_CREDENTIALS',
            );
        }

        if (!$user->email_verified_at || $user->status === UserStatus::PENDING) {
            return $this->errorResponse(
                message: 'Your account is registered but your email address is not verified. Please verify your email before logging in.',
                code: Response::HTTP_FORBIDDEN,
                errorCode: 'EMAIL_NOT_VERIFIED',
            );
        }

        if ($user->status !== UserStatus::ACTIVE) {
            return $this->errorResponse(
                message: 'Your account is not active. Please contact support.',
                code: Response::HTTP_FORBIDDEN,
                errorCode: 'ACCOUNT_INACTIVE',
            );
        }
        $user->last_login_at = Carbon::now();
        $user->save();
        $token = $user->createToken('api_token');

        return $this->successResponse(
            [
                'token' => $token->plainTextToken,
            ],
            'Logged in successfully.'
        );
    }
}
