<?php

namespace App\Http\Requests;

use App\OtpType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SendOtpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns', 'exists:users,email'],
            'type' => ['required', new Enum(OtpType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.exists' => 'The email address is not registered.',

            'type.required' => 'Type is required.',
            'type.Illuminate\Validation\Rules\Enum' => 'Type must be one of: register, login, forgot-password, verify-email.',
        ];
    }
}
