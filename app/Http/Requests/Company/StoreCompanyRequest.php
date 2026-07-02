<?php

namespace App\Http\Requests\Company;

use App\Enums\CompanyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100', 'unique:companies,name'],
            'industry' => ['nullable', 'string'],
            'website' => ['nullable', 'url'],
            'address' => ['nullable', 'string'],
            'logo_path' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(CompanyStatus::class)],
        ];
    }
}
