<?php

namespace App\Policies;

use App\Models\GameMatch;
use App\Models\User;

class GameMatchPolicy
{
    public function view(User $user, GameMatch $match): bool
    {
        return $match->user_id === $user->id;
    }

    public function delete(User $user, GameMatch $match): bool
    {
        return $match->user_id === $user->id;
    }
}
