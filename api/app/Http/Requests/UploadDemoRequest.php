<?php

namespace App\Http\Requests;

use App\Contracts\PermissionService;
use App\Models\Team;
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
            'demo' => ['required', 'file', 'extensions:dem', 'max:'.config('clutch.max_demo_kb'), function ($attr, $value, $fail) {
                // A teamless (private) upload still requires the caller to be an uploader
                // somewhere — a user who can't upload to any team can't upload at all. Team
                // uploads carry their own per-team check on `team_id` below. Admins bypass.
                if ($this->input('team_id') || $this->user()->isAdmin()) {
                    return;
                }
                if (! app(PermissionService::class)->canOnAnyTeam($this->user(), 'team.upload_match')) {
                    $fail('match.upload_forbidden');
                }
            }],
            // Optional: file the match under a team so the whole team can see it. Must be a
            // team the caller may upload to; null keeps the match private.
            'team_id' => ['nullable', 'integer', function ($attr, $value, $fail) {
                $team = Team::find($value);
                if (! $team || $this->user()->cannot('uploadMatch', $team)) {
                    $fail('match.invalid_team');
                }
            }],
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
            'team_id.integer' => 'match.invalid_team',
            'match.upload_forbidden' => 'match.upload_forbidden',
        ];
    }
}
