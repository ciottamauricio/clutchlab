<?php

namespace App\Http\Requests\Trainings;

use Illuminate\Foundation\Http\FormRequest;

// Edits fields, replaces tactics/roster wholesale, or flips cancellation. A session
// never changes team. Authorization (training.manage) is the policy's job — the
// controller authorizes 'update' before validation matters.
class UpdateTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:120'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'scheduled_at' => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'between:1,600'],
            'canceled' => ['sometimes', 'boolean'],
            'tactic_ids' => ['sometimes', 'array'],
            'tactic_ids.*' => ['integer'],
            'player_ids' => ['sometimes', 'array'],
            'player_ids.*' => ['integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            TrainingReferences::check(
                $v,
                $this->route('training')->team,
                $this->input('tactic_ids', []) ?? [],
                $this->input('player_ids', []) ?? [],
            );
        });
    }

    public function messages(): array
    {
        return [
            'title.string' => 'training.invalid_title',
            'title.max' => 'training.invalid_title',
            'notes.string' => 'training.invalid_notes',
            'notes.max' => 'training.invalid_notes',
            'scheduled_at.date' => 'training.invalid_time',
            'duration_minutes.integer' => 'training.invalid_duration',
            'duration_minutes.between' => 'training.invalid_duration',
            'canceled.boolean' => 'training.invalid_canceled',
            'tactic_ids.array' => 'training.invalid_tactic',
            'tactic_ids.*.integer' => 'training.invalid_tactic',
            'player_ids.array' => 'training.invalid_player',
            'player_ids.*.integer' => 'training.invalid_player',
        ];
    }
}
