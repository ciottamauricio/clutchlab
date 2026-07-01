<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'demo' => ['required', 'file', 'extensions:dem', 'max:'.config('clutch.max_demo_kb')],
        ];
    }

    /**
     * Validation returns codes, not sentences — the React layer localizes them.
     */
    public function messages(): array
    {
        return [
            'demo.required' => 'demo.required',
            'demo.file' => 'demo.invalid',
            'demo.extensions' => 'demo.wrong_extension',
            'demo.max' => 'demo.file_too_large',
        ];
    }
}
