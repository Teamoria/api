<?php

namespace App\Http\Requests\Staff;

use App\UserRole;
use App\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($this->id)],
            'password' => ['sometimes', 'string', 'min:6', 'confirmed'],
            'role' => ['sometimes', Rule::in([UserRole::COMPANY_MANAGER, UserRole::COMPANY_MEMBER])],
            'status' => ['required', Rule::in(UserStatus::cases())],

        ];
    }
}
