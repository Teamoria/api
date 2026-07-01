<?php

namespace App\Services\Auth;

use App\Enums\OtpType;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OtpService
{
    public function generate(string $email, OtpType $type): string|false
    {
        $cacheKey = $this->cacheKey($email, $type);

        Cache::forget($cacheKey);

        $code = random_int(100000, 999999);
        $minutes = (int) config('auth.otp_expiry_time', 10);
        $ttl = now()->addMinutes($minutes);

        Cache::put($cacheKey, $code, $ttl);

        try {
            Mail::to($email)->send(new OtpMail($code, $type->value, $type->subject()));

            return $code;
        } catch (Throwable $exception) {
            report($exception);
        }

        return false;
    }

    public function verify(string $email, int $code, OtpType $type): bool
    {
        $cacheKey = $this->cacheKey($email, $type);
        $cachedCode = Cache::get($cacheKey);

        if ($cachedCode && $cachedCode === $code) {
            Cache::forget($cacheKey);

            return true;
        }

        return false;
    }

    private function cacheKey(string $email, OtpType $type): string
    {
        return "otp:{$type->value}:{$email}";
    }
}
