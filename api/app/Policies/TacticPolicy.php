<?php

namespace App\Policies;

use App\Models\Tactic;
use App\Models\User;

class TacticPolicy
{
    public function view(User $user, Tactic $tactic): bool
    {
        return $tactic->user_id === $user->id;
    }

    public function update(User $user, Tactic $tactic): bool
    {
        return $tactic->user_id === $user->id;
    }

    public function delete(User $user, Tactic $tactic): bool
    {
        return $tactic->user_id === $user->id;
    }
}
