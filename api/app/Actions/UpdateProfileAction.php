<?php

namespace App\Actions;

use App\Models\User;

// A user editing their own profile. Only profile fields are fillable here — role, steam_id,
// email and password are deliberately not touched (those have their own guarded paths).
class UpdateProfileAction
{
    public function execute(User $user, array $data): User
    {
        $user->fill($data)->save();

        return $user;
    }
}
