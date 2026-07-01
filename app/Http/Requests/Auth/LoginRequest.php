<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', Rule::exists('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'The email address is required.',
            'email.email' => 'The email address is not valid or its domain does not exist.',
            'email.exists' => 'The email address is not registered.',

            'password.required' => 'The password is required.',
            'password.min' => 'The password must be at least 6 characters long.',
        ];
    }
}
