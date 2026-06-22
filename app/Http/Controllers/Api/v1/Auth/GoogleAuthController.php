<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Returns the Google OAuth redirect URL for the frontend to open.
     */
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

    /**
     * Handles the callback from Google after the user authenticates.
     */
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
            ]
        );

        if (! $user->google_id) {
            $user->update(['google_id' => $googleUser->getId()]);
        }

        $token = $user->createToken('api_token');

        return $this->successResponse(
            ['token' => $token->plainTextToken],
            'Logged in successfully via Google.'
        );
    }
}
