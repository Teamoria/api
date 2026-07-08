<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'max_projects' => ['required', 'integer', 'min:0'],
            'max_members' => ['required', 'integer', 'min:0'],
            'max_storage_mb' => ['required', 'integer', 'min:0'],
            'has_ai_features' => ['required', 'boolean'],
        ];
    }
}
