<?php

namespace App\Http\Requests\User;

use App\UserRole;
use App\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return UserRole::ADMIN->value == Auth::user()->role;
    }

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
}
