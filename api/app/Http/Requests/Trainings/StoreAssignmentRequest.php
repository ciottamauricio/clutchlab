<?php

namespace App\Http\Requests\Trainings;

use App\Models\TrainingAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // manage rights are the controller's policy check
    }

    public function rules(): array
    {
        return [
            // Homework is for attendees: the assignee must be on this session's roster.
            'user_id' => ['required', 'integer', function ($attr, $value, $fail) {
                if (! $this->route('training')->players()->whereKey($value)->exists()) {
                    $fail('training.invalid_assignee');
                }
            }],
            'map' => ['required', 'string', 'max:32'],
            'nade_type' => ['required', Rule::in(TrainingAssignment::NADE_TYPES)],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'training.invalid_assignee',
            'user_id.integer' => 'training.invalid_assignee',
            'map.required' => 'training.invalid_nade',
            'map.string' => 'training.invalid_nade',
            'map.max' => 'training.invalid_nade',
            'nade_type.required' => 'training.invalid_nade',
            'nade_type.in' => 'training.invalid_nade',
        ];
    }
}
