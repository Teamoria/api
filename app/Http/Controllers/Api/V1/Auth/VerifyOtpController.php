<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpType;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifyOtpController extends Controller
{
    public function __invoke(VerifyOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validated();
        $type = OtpType::from($validated['type']);

        if (! $otpService->verify($validated['email'], (int) $validated['code'], $type)) {
            return $this->errorResponse(
                'Invalid or expired verification code.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return match ($type) {
            OtpType::Register, OtpType::VerifyEmail => $this->handleEmailVerification($validated['email']),
            OtpType::ForgotPassword => $this->handleForgotPassword($validated['email'], $validated['new_password']),
        };
    }

    private function handleEmailVerification(string $email): JsonResponse
    {
        $user = User::firstWhere('email', $email);

        if (! $user) {
            return $this->errorResponse('User not found.', Response::HTTP_NOT_FOUND);
        }
        if ($user->email_verified_at) {
            return $this->errorResponse('Email already verified.', Response::HTTP_BAD_REQUEST);
        }

        $user->update([
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);

        return $this->successResponse(
            [
                'email_verified' => $user->email_verified_at,
            ],
            'Email verified successfully.'
        );
    }

    private function handleForgotPassword(string $email, string $newPassword): JsonResponse
    {
        $user = User::firstWhere('email', $email);

        if (! $user) {
            return $this->errorResponse('User not found.', Response::HTTP_NOT_FOUND);
        }

        $user->update(['password' => $newPassword]);

        return $this->successResponse([], 'Password changed successfully.');
    }
}
