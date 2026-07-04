<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddProjectMembersRequest extends FormRequest
{
    public function rules(): array
    {
        $assignableRoles = [
            ProjectRole::MEMBER->value,
            ProjectRole::VIEWER->value,
        ];

        if (in_array($this->user()?->role, [
            UserRole::ADMIN,
            UserRole::COMPANY_OWNER,
        ], true)) {
            $assignableRoles[] = ProjectRole::MANAGER->value;
        }

        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'role' => ['sometimes', 'string', Rule::in($assignableRoles)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $project = Project::query()->find($this->route('id'));

                if ($project === null) {
                    return;
                }

                $userIds = $this->array('user_ids');
                $companyUsersCount = User::query()
                    ->whereIn('id', $userIds)
                    ->where('company_id', $project->company_id)
                    ->count();

                if ($companyUsersCount !== count($userIds)) {
                    $validator->errors()->add(
                        'user_ids',
                        'One or more users do not belong to the project company.',
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.*.exists' => 'One or more users do not exist.',
        ];
    }
}
