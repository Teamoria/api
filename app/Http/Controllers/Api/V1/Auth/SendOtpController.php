<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpType;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SendOtpController extends Controller
{
    public function __invoke(SendOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validated();

        $type = OtpType::from($validated['type']);

        if ($type === OtpType::VerifyEmail) {
            $user = User::firstWhere('email', $validated['email']);

            if (! $user) {
                return $this->errorResponse('User not found.', Response::HTTP_NOT_FOUND);
            }

            if ($user->email_verified_at) {
                return $this->errorResponse('Email already verified.', Response::HTTP_BAD_REQUEST);
            }
        }

        $data = [
            'email' => $validated['email'],
            'type' => $type,
        ];
        $code = $otpService->generate($data['email'], $data['type']);

        if (config('app.debug')) {
            $data['code'] = $code;
        }

        return $this->successResponse(
            $data,
            'OTP sent successfully.'
        );
    }
}
