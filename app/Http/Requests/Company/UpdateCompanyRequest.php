<?php

namespace App\Http\Requests\Company;

use App\Enums\CompanyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'min:3',
                'max:100',
                Rule::unique('companies', 'name')->ignore($this->route('company') ?? $this->route('id')),
            ],
            'industry' => ['sometimes', 'string'],
            'website' => ['sometimes', 'url'],
            'address' => ['sometimes', 'string'],
            'logo_path' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(CompanyStatus::class)],
        ];
    }
}
