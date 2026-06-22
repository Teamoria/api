<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleAuthController extends Controller
{
    public function loginWithToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_token' => ['required', 'string'],
        ]);

        try {
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');

            $googleUser = $driver
                ->stateless()
                ->userFromToken($validated['provider_token']);

            [$user, $token] = DB::transaction(function () use ($googleUser): array {
                $user = $this->findOrCreateUser($googleUser);

                return [$user, $user->createToken('api_token')->plainTextToken];
            });

            return $this->successResponse(
                [
                    'user' => $user,
                    'token' => $token,
                ],
                'Authenticated successfully.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse(
                'Google authentication failed. The provided token is invalid or expired.',
                Response::HTTP_UNAUTHORIZED
            );
        }
    }

    public function redirect(): JsonResponse
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        $redirectUrl = $driver
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
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');

            $googleUser = $driver
                ->stateless()
                ->user();
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Google authentication failed.',
                Response::HTTP_UNAUTHORIZED,
                ['error' => $e->getMessage()]
            );
        }

        $user = User::firstOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'password' => str()->random(32),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->google_id) {
            $user->google_id = $googleUser->getId();
            $user->save();
        }
        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        $token = $user->createToken('api_token');

        return $this->successResponse(
            ['token' => $token->plainTextToken],
            'Logged in successfully via Google.'
        );
    }

    private function findOrCreateUser(SocialiteUser $googleUser): User
    {
        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        if (! is_string($googleId) || $googleId === '' || ! is_string($email) || $email === '') {
            throw new RuntimeException('Google did not return a valid user identifier and email address.');
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: $email,
                'google_id' => $googleId,
                'password' => Str::random(64),
                'email_verified_at' => now(),
            ]
        );
        if (! $user->google_id) {
            $user->google_id = $googleId;
            $user->save();
        } elseif (! hash_equals((string) $user->google_id, $googleId)) {
            throw new RuntimeException('The Google account does not match the linked account.');
        }

        if (! $user->email_verified_at) {
            $user->email_verified_at = Carbon::now();
            $user->save();
        }

        return $user;
    }
}
