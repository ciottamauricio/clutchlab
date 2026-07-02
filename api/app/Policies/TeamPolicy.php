<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        return $team->members()->whereKey($user->id)->exists();
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $team->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists();
    }

    public function update(User $user, Team $team): bool
    {
        return $this->manageMembers($user, $team);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->manageMembers($user, $team);
    }
}
