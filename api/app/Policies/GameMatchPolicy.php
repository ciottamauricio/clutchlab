<?php

namespace App\Policies;

use App\Contracts\PermissionService;
use App\Models\GameMatch;
use App\Models\User;

// Thin policy: every match ability is a team-scope permission, resolved by the service against
// the user's role in the match's team (plus the uploader carve-out). Master admins are handled
// upstream by Gate::before.
class GameMatchPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function view(User $user, GameMatch $match): bool
    {
        return $this->permissions->canOnMatch($user, 'match.view', $match);
    }

    public function delete(User $user, GameMatch $match): bool
    {
        return $this->permissions->canOnMatch($user, 'match.delete', $match);
    }

    public function reparse(User $user, GameMatch $match): bool
    {
        return $this->permissions->canOnMatch($user, 'match.reparse', $match);
    }
}
