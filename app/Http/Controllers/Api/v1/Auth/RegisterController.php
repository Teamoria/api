<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\UserRole;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{

    public function __invoke(RegisterRequest $request): JsonResponse
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

            $token = $user->createToken('api_token');

            DB::commit();

            return $this->successResponse(
                [
                    'token' => $token->plainTextToken,
                ],
                'User registered successfully.',
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
