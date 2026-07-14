<?php

namespace App\Http\Requests\Trainings;

use App\Models\Tactic;
use App\Models\Team;
use Illuminate\Validation\Validator;

// The two reference rules from the trainings domain doc, shared by store and update:
// roster ⊆ team members, and tactics visible to the team (shared with it, or owned by
// one of its members).
class TrainingReferences
{
    public static function check(Validator $v, Team $team, array $tacticIds, array $playerIds): void
    {
        if ($playerIds !== []) {
            $members = $team->members()->whereIn('users.id', $playerIds)->count();
            if ($members !== count(array_unique($playerIds))) {
                $v->errors()->add('player_ids', 'training.invalid_player');
            }
        }

        if ($tacticIds !== []) {
            $memberIds = $team->members()->pluck('users.id');
            $visible = Tactic::whereIn('id', $tacticIds)
                ->where(fn ($q) => $q->where('team_id', $team->id)->orWhereIn('user_id', $memberIds))
                ->count();
            if ($visible !== count(array_unique($tacticIds))) {
                $v->errors()->add('tactic_ids', 'training.invalid_tactic');
            }
        }
    }
}
