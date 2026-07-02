<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTacticRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'board' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'tactic.name_required',
        ];
    }
}
