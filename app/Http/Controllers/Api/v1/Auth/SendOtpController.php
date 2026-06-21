<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\SendOtpRequest;
use App\OtpType;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class SendOtpController extends Controller
{
    public function __invoke(SendOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validated();

        $type = OtpType::from($validated['type']);

        $otpService->generate($validated['email'], $type);

        return $this->successResponse(
            [],
            'OTP sent successfully.'
        );
    }
}
