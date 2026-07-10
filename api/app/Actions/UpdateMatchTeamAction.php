<?php

namespace App\Actions;

use App\Models\GameMatch;

// Move a match between private and a team (or between teams). team_id null = private.
class UpdateMatchTeamAction
{
    public function execute(GameMatch $match, ?int $teamId): GameMatch
    {
        $match->update(['team_id' => $teamId]);

        return $match;
    }
}
