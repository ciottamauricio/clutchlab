<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

// Change which team a match is shared with. `team_id` null (or absent) makes it private; a team
// id shares it — but only with a team the caller may upload to, the same bar as uploading.
class UpdateMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_id' => ['present', 'nullable', 'integer', function ($attr, $value, $fail) {
                if ($value === null) {
                    return; // making the match private — no team to authorize against
                }
                $team = Team::find($value);
                if (! $team || $this->user()->cannot('uploadMatch', $team)) {
                    $fail('match.invalid_team');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.present' => 'match.invalid_team',
            'team_id.integer' => 'match.invalid_team',
        ];
    }
}
