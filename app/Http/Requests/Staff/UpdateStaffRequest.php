<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($this->route('id'))],
            'password' => ['sometimes', 'string', 'min:6', 'confirmed'],
            'role' => [
                'sometimes',
                Rule::enum(UserRole::class)->only([
                    UserRole::COMPANY_MANAGER,
                    UserRole::COMPANY_MEMBER,
                ]),
            ],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
        ];
    }
}
