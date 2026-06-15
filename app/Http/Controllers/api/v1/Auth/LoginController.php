<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            return $this->errorResponse(
                'Invalid credentials',
                401,
                ['error' => 'Invalid credentials']
            );
        }

        $token = Auth::user()->createToken('api_token');

        return $this->successResponse(
            [
                'token' => $token->plainTextToken,
            ],
            'Logged in successfully.'
        );
    }
}
