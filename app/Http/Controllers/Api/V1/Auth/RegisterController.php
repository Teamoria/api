<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, OtpService $otpService): JsonResponse
    {
        try {
            $validated = $request->validated();

            $code = DB::transaction(function () use ($otpService, $validated): string {
                User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'role' => UserRole::COMPANY_OWNER->value,
                    'status' => UserStatus::PENDING->value,
                ]);

                $code = $otpService->generate($validated['email'], OtpType::Register);

                if ($code === false) {
                    throw new \RuntimeException('Failed to send verification code.');
                }

                return $code;
            });

            $data = [
                'type' => OtpType::Register->value,
                'expires_in' => config('auth.otp_expiry_time', 10),
            ];

            if (config('app.debug')) {
                $data['code'] = $code;
            }

            return $this->successResponse(
                $data,
                'User registered successfully. Please verify your email.',
                Response::HTTP_CREATED
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse(
                'Failed to register user.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
