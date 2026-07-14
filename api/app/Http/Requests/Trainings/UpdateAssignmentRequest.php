<?php

namespace App\Http\Requests\Trainings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // assignee-only is the controller's policy check
    }

    public function rules(): array
    {
        return [
            'done' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'done.required' => 'training.invalid_done',
            'done.boolean' => 'training.invalid_done',
        ];
    }
}
