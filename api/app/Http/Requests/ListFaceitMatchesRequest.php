<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// The FACEIT panel's list. `refresh` asks FACEIT again instead of using the 5-minute copy.
class ListFaceitMatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'refresh.boolean' => 'faceit.invalid_refresh',
        ];
    }
}
