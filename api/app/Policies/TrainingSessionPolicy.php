<?php

namespace App\Policies;

use App\Contracts\PermissionService;
use App\Models\Team;
use App\Models\TrainingSession;
use App\Models\User;

// Viewing is plain team membership (like tactics); scheduling/editing/canceling is the
// grantable team ability `training.manage` (owner/igl/coach by default — the coach's
// whole job is running practice). Master admins pass upstream via Gate::before.
class TrainingSessionPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function view(User $user, TrainingSession $session): bool
    {
        return $user->teams()->whereKey($session->team_id)->exists();
    }

    /** Creation is authorized against the target team: authorize('create', [TrainingSession::class, $team]). */
    public function create(User $user, Team $team): bool
    {
        return $this->permissions->canOnTeam($user, 'training.manage', $team);
    }

    public function update(User $user, TrainingSession $session): bool
    {
        return $this->permissions->canOnTeam($user, 'training.manage', $session->team);
    }

    public function delete(User $user, TrainingSession $session): bool
    {
        return $this->permissions->canOnTeam($user, 'training.manage', $session->team);
    }
}
