<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
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
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'phone' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'timezone' => ['sometimes', 'string'],
            'company_id' => ['sometimes', 'string', Rule::exists('companies', 'id')],
        ];
    }

    private function userBeingUpdated(): User
    {
        $id = $this->route('id');

        return $id ? User::findOrFail($id) : $this->user();
    }
}
