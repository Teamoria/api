<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => [
                'required',
                Rule::enum(UserRole::class)->only([
                    UserRole::COMPANY_MANAGER,
                    UserRole::COMPANY_MEMBER,
                ]),
            ],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ];
    }
}
