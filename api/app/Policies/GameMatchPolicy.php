<?php

namespace App\Policies;

use App\Models\GameMatch;
use App\Models\User;

class GameMatchPolicy
{
    // Any team member sees the team's matches; the uploader always sees their own.
    public function view(User $user, GameMatch $match): bool
    {
        return $match->user_id === $user->id || $this->onMatchTeam($user, $match);
    }

    // Write actions are the uploader, or a team member who may upload to that team.
    public function delete(User $user, GameMatch $match): bool
    {
        return $match->user_id === $user->id || $this->onMatchTeam($user, $match, ['owner', 'igl']);
    }

    public function reparse(User $user, GameMatch $match): bool
    {
        return $this->delete($user, $match);
    }

    /**
     * @param  list<string>|null  $roles  restrict to these team roles; null = any member
     */
    private function onMatchTeam(User $user, GameMatch $match, ?array $roles = null): bool
    {
        if (! $match->team_id) {
            return false;
        }

        $query = $user->teams()->whereKey($match->team_id);

        if ($roles !== null) {
            $query->wherePivotIn('role', $roles);
        }

        return $query->exists();
    }
}
