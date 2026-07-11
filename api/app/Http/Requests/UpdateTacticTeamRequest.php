<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTacticTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_id' => ['present', 'nullable', 'integer', function ($attribute, $value, $fail) {
                if ($value === null) {
                    return; // making it private again — always allowed for the owner
                }
                if (! $this->user()->teams()->whereKey($value)->exists()) {
                    $fail('tactic.invalid_team');
                }
            }],
        ];
    }
}
