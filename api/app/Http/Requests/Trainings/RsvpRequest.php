<?php

namespace App\Http\Requests\Trainings;

use Illuminate\Foundation\Http\FormRequest;

class RsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roster membership is the controller's policy check
    }

    public function rules(): array
    {
        return [
            'going' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'going.required' => 'training.invalid_rsvp',
            'going.boolean' => 'training.invalid_rsvp',
        ];
    }
}
