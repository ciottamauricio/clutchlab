<?php

namespace App\Http\Requests\Trainings;

use App\Contracts\PermissionService;
use App\Models\Team;
use App\Models\TrainingAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Same precedent as match.invalid_team: a team you can't schedule for is
            // indistinguishable from a team that doesn't exist (422, not 403/404).
            'team_id' => ['required', 'integer', function ($attr, $value, $fail) {
                $team = Team::find($value);
                if (! $team || ! app(PermissionService::class)->canOnTeam($this->user(), 'training.manage', $team)) {
                    $fail('training.invalid_team');
                }
            }],
            'title' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,600'],
            'tactic_ids' => ['required', 'array', 'min:1'],
            'tactic_ids.*' => ['integer'],
            'player_ids' => ['required', 'array', 'min:1'],
            'player_ids.*' => ['integer'],
            // Optional homework picked during scheduling. Roster-membership is checked in
            // withValidator against the submitted player_ids (the roster doesn't exist yet).
            'assignments' => ['sometimes', 'array'],
            'assignments.*.user_id' => ['required', 'integer'],
            'assignments.*.map' => ['required', 'string', 'max:32'],
            'assignments.*.nade_type' => ['required', Rule::in(TrainingAssignment::NADE_TYPES)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $team = Team::find($this->input('team_id'));
            if (! $team) {
                return; // team_id rule already failed
            }
            $playerIds = $this->input('player_ids', []) ?? [];
            TrainingReferences::check($v, $team, $this->input('tactic_ids', []) ?? [], $playerIds);

            // Homework is for attendees: every assignee must be in the submitted roster.
            foreach ($this->input('assignments', []) ?? [] as $i => $a) {
                if (! in_array($a['user_id'] ?? null, $playerIds)) {
                    $v->errors()->add("assignments.$i.user_id", 'training.invalid_assignee');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'training.invalid_team',
            'team_id.integer' => 'training.invalid_team',
            'title.required' => 'training.invalid_title',
            'title.string' => 'training.invalid_title',
            'title.max' => 'training.invalid_title',
            'notes.string' => 'training.invalid_notes',
            'notes.max' => 'training.invalid_notes',
            'scheduled_at.required' => 'training.invalid_time',
            'scheduled_at.date' => 'training.invalid_time',
            'duration_minutes.integer' => 'training.invalid_duration',
            'duration_minutes.between' => 'training.invalid_duration',
            'tactic_ids.required' => 'training.invalid_tactic',
            'tactic_ids.array' => 'training.invalid_tactic',
            'tactic_ids.min' => 'training.invalid_tactic',
            'tactic_ids.*.integer' => 'training.invalid_tactic',
            'player_ids.required' => 'training.invalid_player',
            'player_ids.array' => 'training.invalid_player',
            'player_ids.min' => 'training.invalid_player',
            'player_ids.*.integer' => 'training.invalid_player',
            'assignments.*.user_id.required' => 'training.invalid_assignee',
            'assignments.*.user_id.integer' => 'training.invalid_assignee',
            'assignments.*.map.required' => 'training.invalid_nade',
            'assignments.*.map.string' => 'training.invalid_nade',
            'assignments.*.map.max' => 'training.invalid_nade',
            'assignments.*.nade_type.required' => 'training.invalid_nade',
            'assignments.*.nade_type.in' => 'training.invalid_nade',
        ];
    }
}
