<?php

namespace App\Http\Requests\Upload;

use App\Enums\UploadAccessLevel;
use App\Enums\UploadScope;
use App\Models\Upload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GrantUploadAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $upload = $this->route('upload');

        return $upload instanceof Upload
            && $this->user()?->can('share', $upload) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Upload $upload */
        $upload = $this->route('upload');

        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('users', 'id')
                    ->where(fn (Builder $query) => $query
                        ->where('company_id', $upload->company_id)
                        ->whereNull('deleted_at')),
            ],
            'access_level' => ['required', Rule::enum(UploadAccessLevel::class)],
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

                /** @var Upload $upload */
                $upload = $this->route('upload');

                if (! in_array($upload->scope, [UploadScope::PROJECT, UploadScope::TASK], true)) {
                    return;
                }

                $userIds = $this->array('user_ids');
                $projectUsersCount = $upload->project?->users()
                    ->whereKey($userIds)
                    ->count();

                if ($projectUsersCount !== count($userIds)) {
                    $validator->errors()->add(
                        'user_ids',
                        'Project and task files can only be shared with project members.',
                    );
                }
            },
        ];
    }
}
