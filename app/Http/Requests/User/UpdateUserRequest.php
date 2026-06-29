<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\UserRole;
use App\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
            'email' => ['sometimes', 'string', 'email', Rule::unique('users', 'email')->ignore($this->userBeingUpdated())],
            'password' => ['sometimes', 'string', 'confirmed', 'min:8'],
            'role' => ['sometimes', 'string', Rule::in(UserRole::cases())],
            'phone' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', Rule::in(UserStatus::cases())],
            'timezone' => ['sometimes', 'string'],
            'company_id' => ['sometimes', 'string', Rule::exists('companies', 'id')],
        ];
    }

    private function userBeingUpdated(): User
    {
        return $this->route('user') ?? $this->user();
    }
}
