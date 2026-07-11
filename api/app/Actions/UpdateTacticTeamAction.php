<?php

namespace App\Actions;

use App\Models\Tactic;

class UpdateTacticTeamAction
{
    public function execute(Tactic $tactic, ?int $teamId): Tactic
    {
        $tactic->update(['team_id' => $teamId]);

        return $tactic;
    }
}
