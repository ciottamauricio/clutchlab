<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskAnalystRequest extends FormRequest
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
            'question.required' => 'analyst.question_required',
            'question.min' => 'analyst.question_too_short',
            'question.max' => 'analyst.question_too_long',
        ];
    }
}
