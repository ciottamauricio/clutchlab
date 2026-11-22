<?php

namespace App\Http\Requests;

use App\Contracts\PermissionService;
use App\Models\GameMatch;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDemoRequest extends FormRequest
{
    private ?string $hash = null;

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
                if (! $this->input('team_id') && ! $this->user()->isAdmin()
                    && ! app(PermissionService::class)->canOnAnyTeam($this->user(), 'team.upload_match')) {
                    $fail('match.upload_forbidden');

                    return;
                }

                // Reject a demo this user has already uploaded — identity is the file's content,
                // not its (random) storage key or client-supplied name.
                $exists = $this->user()->matches()
                    ->where('content_hash', $this->contentHash())
                    ->exists();
                if ($exists) {
                    $fail('match.duplicate');
                }
            }],
            'platform' => ['required', Rule::in(GameMatch::PLATFORMS)],
            // Optional: only Gamers Club names carry the date, so other demos need it stated.
            // Absent, the action falls back to the filename's date.
            'played_at' => ['nullable', 'date', 'before_or_equal:now'],
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
            'platform.required' => 'match.platform_required',
            'platform.in' => 'match.invalid_platform',
            'played_at.date' => 'match.invalid_played_at',
            'played_at.before_or_equal' => 'match.invalid_played_at',
            'team_id.integer' => 'match.invalid_team',
            'match.upload_forbidden' => 'match.upload_forbidden',
            'match.duplicate' => 'match.duplicate',
        ];
    }

    // The uploaded demo's content hash, computed once. Shared with the action so the same hash
    // that gated the upload is the one stored on the match.
    public function contentHash(): ?string
    {
        if ($this->hash === null) {
            $file = $this->file('demo');
            $this->hash = $file ? hash_file('sha256', $file->getRealPath()) : null;
        }

        return $this->hash;
    }
}
