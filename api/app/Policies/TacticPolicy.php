<?php

namespace App\Policies;

use App\Models\Tactic;
use App\Models\User;

class TacticPolicy
{
    // A shared tactic is a collaboration surface: every member of its team may open it
    // AND edit the board — that's the point of sharing a strat. Membership-based on
    // purpose (not a grantable ability yet; see teams-auth.md known limitations).
    public function view(User $user, Tactic $tactic): bool
    {
        return $tactic->user_id === $user->id || $this->memberOfTacticTeam($user, $tactic);
    }

    public function update(User $user, Tactic $tactic): bool
    {
        return $tactic->user_id === $user->id || $this->memberOfTacticTeam($user, $tactic);
    }

    // Deleting and re-sharing stay with the creator only.
    public function delete(User $user, Tactic $tactic): bool
    {
        return $tactic->user_id === $user->id;
    }

    private function memberOfTacticTeam(User $user, Tactic $tactic): bool
    {
        return $tactic->team_id !== null
            && $user->teams()->whereKey($tactic->team_id)->exists();
    }
}
