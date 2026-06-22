<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'confirm_password' => ['required', 'string', 'same:password'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Name is required.',
            'name.string' => 'Name must be a string.',
            'name.max' => 'Name must be at most 255 characters.',

            'email.required' => 'Email is required.',
            'email.string' => 'Email must be a string.',
            'email.email' => 'Email is invalid.',
            'email.unique' => 'Email already exists.',

            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a string.',
            'password.min' => 'Password must be at least 6 characters.',
            'password.max' => 'Password must be at most 255 characters.',

            'confirm_password.required' => 'Confirm password is required.',
            'confirm_password.string' => 'Confirm password must be a string.',
            'confirm_password.same' => 'Confirm password must be same as password.',
        ];
    }
}
