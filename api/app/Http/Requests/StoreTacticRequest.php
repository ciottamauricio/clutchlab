<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class StoreTacticRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'map' => ['nullable', 'string', 'max:32'],
            // Optional: create the tactic already shared with a team you belong to. Null
            // (or absent) keeps it private. Same "invalid = doesn't exist" precedent as
            // match/training team fields; the create-ability check is the controller's job.
            'team_id' => ['nullable', 'integer', function ($attr, $value, $fail) {
                $team = Team::find($value);
                if (! $team || ! $this->user()->teams()->whereKey($team->id)->exists()) {
                    $fail('tactic.invalid_team');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'tactic.name_required',
            'team_id.integer' => 'tactic.invalid_team',
        ];
    }
}
