<?php

namespace App\Http\Requests\Auth;

use App\Enums\OtpType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendOtpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'exists:users,email'],
            'type' => ['required', Rule::enum(OtpType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.exists' => 'The email address is not registered.',

            'type.required' => 'Type is required.',
            'type.enum' => 'Type must be one of: register, forgot-password, verify-email.',
        ];
    }
}
