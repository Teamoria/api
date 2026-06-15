<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {

        Auth::user()->currentAccessToken()->delete();

        return $this->successResponse(
            [],
            'Logged out successfully.',
        );
    }
}
