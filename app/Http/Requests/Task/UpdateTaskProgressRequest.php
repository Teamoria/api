<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seen' => ['required_without:completed', 'boolean'],
            'completed' => ['required_without:seen', 'boolean'],
        ];
    }
}
