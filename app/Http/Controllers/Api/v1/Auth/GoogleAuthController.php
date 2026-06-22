<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): JsonResponse
    {
        $redirectUrl = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return $this->successResponse(
            ['redirect_url' => $redirectUrl],
            'Redirect to Google to authenticate.'
        );
    }

    public function handleCallback(): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Google authentication failed.',
                401,
                ['error' => $e->getMessage()]
            );
        }

        $user = User::firstOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'password' => str()->random(32),
                'email_verified_at' => now()
            ]
        );

        if (! $user->google_id) {
            $user->update(['google_id' => $googleUser->getId()]);
        }
        if (! $user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        $token = $user->createToken('api_token');

        return $this->successResponse(
            ['token' => $token->plainTextToken],
            'Logged in successfully via Google.'
        );
    }
}
