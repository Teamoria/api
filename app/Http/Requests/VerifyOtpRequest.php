<?php

namespace App\Http\Requests;

use App\OtpType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class VerifyOtpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns'],
            'code' => ['required', 'integer', 'digits:6'],
            'type' => ['required', new Enum(OtpType::class)],
            'new_password' => ['required_if:type,forgot-password', 'string', 'min:8', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',

            'code.required' => 'Verification code is required.',
            'code.integer' => 'Verification code must be a number.',
            'code.digits' => 'Verification code must be 6 digits.',

            'type.required' => 'Type is required.',
            'type.Illuminate\Validation\Rules\Enum' => 'Type must be one of: register, login, forgot-password, verify-email.',

            'new_password.required_if' => 'New password is required.',
            'new_password.string' => 'New password must be a string.',
            'new_password.min' => 'New password must be at least 8 characters.',
            'new_password.max' => 'New password must be at most 32 characters.',
        ];
    }
}
