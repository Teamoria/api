<?php

namespace App\Http\Requests\User;

use App\UserRole;
use App\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
            'role' => ['required', 'string', Rule::in(UserRole::cases())],
            'phone' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(UserStatus::cases())],
            'timezone' => ['nullable', 'string'],
            'company_id' => ['required', 'string', 'exists:companies,id'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.unique' => 'This email is already taken.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Passwords do not match.',
            'password.min' => 'Password must be at least 8 characters long.',
            'role.required' => 'Role is required.',
            'role.in' => 'Selected role is invalid. Please select a valid role: '.implode(', ', array_column(UserRole::cases(), 'value')),
            'company_id.required' => 'Company is required.',
            'company_id.exists' => 'Company does not exist.',
        ];
    }
}
