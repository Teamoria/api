<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\GoogleTokenLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleAuthController extends Controller
{
    public function loginWithToken(GoogleTokenLoginRequest $request): JsonResponse
    {
        try {
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');

            $googleUser = $driver
                ->stateless()
                ->userFromToken($request->validated()['provider_token']);

            [$user, $token] = DB::transaction(function () use ($googleUser): array {
                $user = $this->findOrCreateUser($googleUser);
                $user->last_login_at = now();
                $user->save();

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

            $token = DB::transaction(function () use ($googleUser): string {
                $user = $this->findOrCreateUser($googleUser);

                return $user->createToken('api_token')->plainTextToken;
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse(
                'Google authentication failed.',
                Response::HTTP_UNAUTHORIZED,
                ['error' => $exception->getMessage()]
            );
        }

        return $this->successResponse(
            ['token' => $token],
            'Logged in successfully via Google.'
        );
    }

    private function findOrCreateUser(SocialiteUser $googleUser): User
    {
        if ($googleUser instanceof AbstractUser && ($googleUser->getRaw()['email_verified'] ?? null) === false) {
            throw new RuntimeException('Google account email is not verified.');
        }

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
                'status' => UserStatus::ACTIVE->value,
            ]
        );

        if (! $user->google_id) {
            $user->google_id = $googleId;
            $user->status = UserStatus::ACTIVE->value;
        } elseif (! hash_equals((string) $user->google_id, $googleId)) {
            throw new RuntimeException('The Google account does not match the linked account.');
        }

        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
        }

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }
}
