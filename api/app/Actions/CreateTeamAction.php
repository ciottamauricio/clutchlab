<?php

namespace App\Actions;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeamAction
{
    public function execute(User $owner, string $name): Team
    {
        return DB::transaction(function () use ($owner, $name) {
            $team = Team::create(['name' => $name]);
            // The creator is enrolled as the team's owner in the same operation.
            $team->members()->attach($owner->id, ['role' => 'owner']);

            return $team;
        });
    }
}
