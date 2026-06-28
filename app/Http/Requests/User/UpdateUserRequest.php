<?php

namespace App\Http\Requests\User;

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
            'email' => ['sometimes', 'string', 'email', 'unique:users,email,'.$this->user()->id],
            'password' => ['sometimes', 'string', 'confirmed', 'min:8'],
            'role' => ['sometimes', 'string', Rule::in(UserRole::cases())],
            'phone' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', Rule::in(UserStatus::cases())],
            'timezone' => ['sometimes', 'string'],
            'company_id' => ['sometimes', 'string', 'exists:companies,id,'.$this->user()->company_id],
        ];
    }
}
