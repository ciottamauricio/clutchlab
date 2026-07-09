<?php

namespace App\Policies;

use App\Contracts\PermissionService;
use App\Models\Team;
use App\Models\User;

// Team management abilities are team-scope permissions resolved against the caller's role in the
// team. `view` stays a plain membership check — belonging to a team you're in isn't a grantable
// ability. Master admins are handled upstream by Gate::before.
class TeamPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function view(User $user, Team $team): bool
    {
        return $team->members()->whereKey($user->id)->exists();
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $this->permissions->canOnTeam($user, 'team.manage_members', $team);
    }

    public function manageRoster(User $user, Team $team): bool
    {
        return $this->permissions->canOnTeam($user, 'team.manage_roster', $team);
    }

    public function uploadMatch(User $user, Team $team): bool
    {
        return $this->permissions->canOnTeam($user, 'team.upload_match', $team);
    }

    public function update(User $user, Team $team): bool
    {
        return $this->permissions->canOnTeam($user, 'team.update', $team);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->permissions->canOnTeam($user, 'team.update', $team);
    }
}
