<?php

namespace App\Http\Requests\Upload;

use App\Enums\UploadScope;
use App\Enums\UploadVisibility;
use App\Models\Upload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUploadsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Upload::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::enum(UploadScope::class)],
            'visibility' => ['sometimes', Rule::enum(UploadVisibility::class)],
            'project_id' => ['sometimes', 'uuid', 'exists:projects,id'],
            'task_id' => ['sometimes', 'uuid', 'exists:tasks,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
