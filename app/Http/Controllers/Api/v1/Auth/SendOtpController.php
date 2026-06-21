<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\SendOtpRequest;
use App\Models\User;
use App\OtpType;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SendOtpController extends Controller
{
    public function __invoke(SendOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validated();

        $type = OtpType::from($validated['type']);
        if ($validated['type'] == OtpType::VerifyEmail->value) {
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
        if (env('APP_DEBUG', false)) {
            $data['code'] = $code;
        }

        return $this->successResponse(
            $data,
            'OTP sent successfully.'
        );
    }
}
