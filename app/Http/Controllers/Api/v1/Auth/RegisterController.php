<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\OtpType;
use App\Services\OtpService;
use App\UserRole;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, OtpService $otpService): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::COMPANY_OWNER->value,
                'status' => UserStatus::PENDING->value,
            ]);

            $code = $otpService->generate($validated['email'], OtpType::Register);

            DB::commit();

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
        } catch (\Exception $exception) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to register user',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['error' => $exception->getMessage()]
            );
        }
    }
}
