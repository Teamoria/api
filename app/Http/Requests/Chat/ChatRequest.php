<?php

namespace App\Http\Requests\Chat;

use App\Enums\UserRole;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $project = Project::query()->find($this->string('project_id')->toString());

        if ($project === null || $user->role === UserRole::ADMIN) {
            return true;
        }

        if ($user->company_id !== $project->company_id) {
            return false;
        }

        if ($user->role === UserRole::COMPANY_OWNER) {
            return true;
        }

        return $project->users()->whereKey($user->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                'uuid',
                Rule::exists('projects', 'id')->whereNull('deleted_at'),
            ],
            'question' => ['required', 'string', 'max:5000'],
            'context' => ['sometimes', 'array'],
            'context.*' => ['required', 'string'],
            'top_k' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
