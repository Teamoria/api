<?php

namespace App\Enums;

enum OtpType: string
{
    case Register = 'register';
    case ForgotPassword = 'forgot-password';
    case VerifyEmail = 'verify-email';

    public function subject(): string
    {
        return match ($this) {
            self::Register => 'Register Code',
            self::ForgotPassword => 'Forgot Password Code',
            self::VerifyEmail => 'Verify Email Code',
        };
    }
}
