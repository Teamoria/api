<?php

namespace App\Http\Requests\Staff;

use App\UserRole;
use App\UserStatus;
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
            'role' => ['required', Rule::in([UserRole::COMPANY_MANAGER, UserRole::COMPANY_MEMBER])],
            'status' => ['required', Rule::in(UserStatus::cases())],
        ];
    }
}
