<?php

namespace App\Http\Requests;

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
            'demo' => ['required', 'file', 'extensions:dem', 'max:'.config('clutch.max_demo_kb')],
            // Optional: file the match under a team so the whole team can see it. Must be a
            // team the caller may upload to (owner/igl); null keeps the match private.
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
        ];
    }
}
