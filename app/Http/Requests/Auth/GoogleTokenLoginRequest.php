<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GoogleTokenLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'provider_token' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider_token.required' => 'The provider token field is required.',
            'provider_token.string' => 'The provider token must be a string.',
        ];
    }
}
