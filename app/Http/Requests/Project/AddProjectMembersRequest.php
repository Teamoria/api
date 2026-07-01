<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AddProjectMembersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->where('company_id', Auth::user()->company_id),
            ],
            'role' => ['sometimes', 'string', Rule::in([ProjectRole::MEMBER->value, ProjectRole::VIEWER->value])],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.*.exists' => 'One or more users do not belong to your company.',
        ];
    }
}
