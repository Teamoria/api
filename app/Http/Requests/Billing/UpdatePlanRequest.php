<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price_monthly' => ['sometimes', 'numeric', 'min:0'],
            'price_yearly' => ['sometimes', 'numeric', 'min:0'],
            'max_projects' => ['sometimes', 'integer', 'min:0'],
            'max_members' => ['sometimes', 'integer', 'min:0'],
            'max_storage_mb' => ['sometimes', 'integer', 'min:0'],
            'has_ai_features' => ['sometimes', 'boolean'],
        ];
    }
}
