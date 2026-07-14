<?php

namespace App\Policies;

use App\Contracts\PermissionService;
use App\Models\TrainingAssignment;
use App\Models\User;

// The homework split of authority: the coach (training.manage) assigns and removes;
// only the assignee can mark it studied. Master admins pass upstream via Gate::before.
class TrainingAssignmentPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function manage(User $user, TrainingAssignment $assignment): bool
    {
        return $this->permissions->canOnTeam($user, 'training.manage', $assignment->session->team);
    }

    public function complete(User $user, TrainingAssignment $assignment): bool
    {
        return $assignment->user_id === $user->id;
    }
}
