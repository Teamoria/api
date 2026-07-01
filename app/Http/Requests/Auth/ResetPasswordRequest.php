<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'old_password' => ['required', 'string', 'min:8'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'old_password.required' => 'Please enter your old password.',
            'old_password.min' => 'Old password must be at least 8 characters long.',

            'new_password.required' => 'Please enter your new password.',
            'new_password.min' => 'New password must be at least 8 characters long.',
            'new_password.confirmed' => 'New passwords do not match.',
        ];
    }
}
