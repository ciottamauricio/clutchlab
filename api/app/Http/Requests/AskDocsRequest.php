<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskDocsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Bounded so a pasted wall of text can't inflate the prompt.
            'question' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'docs.question_required',
            'question.min' => 'docs.question_too_short',
            'question.max' => 'docs.question_too_long',
        ];
    }
}
