<?php

namespace App\Http\Requests\Chat;

use App\Enums\UserRole;
use App\Models\ChatSession;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $sessionId = $this->string('session_id')->toString();

        if ($sessionId !== '' && Str::isUuid($sessionId)) {
            $session = ChatSession::query()->find($sessionId);

            if ($session !== null && $session->user_id !== $user->id) {
                return false;
            }
        }

        $projectId = $this->string('project_id')->toString();

        if ($projectId === '' || ! Str::isUuid($projectId)) {
            return true;
        }

        $project = Project::query()->find($projectId);

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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'session_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists((new ChatSession)->getTable(), 'id'),
            ],
            'project_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists((new Project)->getTable(), 'id')->whereNull('deleted_at'),
            ],
            'message_content' => ['required', 'string', 'max:5000'],
        ];
    }
}
