<?php

namespace App\Actions;

use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AddTeamMemberAction
{
    public function execute(Team $team, string $email, string $role): User
    {
        $user = User::where('email', $email)->firstOrFail();

        if ($team->members()->whereKey($user->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'team.already_member']);
        }

        $team->members()->attach($user->id, ['role' => $role]);

        return $user;
    }
}
