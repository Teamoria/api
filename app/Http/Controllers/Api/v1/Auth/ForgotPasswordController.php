<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Models\User;
use App\OtpType;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validated();

        if (! $otpService->verify($validated['email'], (int) $validated['code'], OtpType::ForgotPassword)) {
            return $this->errorResponse(
                'Invalid or expired verification code.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $user = User::where('email', $validated['email'])->firstOrFail();
        $user->update(['password' => $validated['password']]);

        return $this->successResponse(
            [],
            'Password reset successfully.'
        );
    }
}
