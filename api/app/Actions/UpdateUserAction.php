<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

// Admin-driven edit of a user's global role and linked SteamID. role/steam_id are not
// mass-assignable by design, so they're set here on the model directly.
class UpdateUserAction
{
    public function execute(User $user, array $data): User
    {
        if (array_key_exists('role', $data)) {
            $this->applyRole($user, UserRole::from($data['role']));
        }

        if (array_key_exists('steam_id', $data)) {
            $user->steam_id = $data['steam_id'] ?: null;
        }

        $user->save();

        return $user;
    }

    private function applyRole(User $user, UserRole $role): void
    {
        // Never demote the last remaining admin — that would lock everyone out of user
        // management with no way back in.
        $demotingLastAdmin = $user->isAdmin()
            && $role !== UserRole::Admin
            && User::where('role', UserRole::Admin->value)->count() <= 1;

        if ($demotingLastAdmin) {
            throw ValidationException::withMessages(['role' => 'admin.last_admin']);
        }

        $user->role = $role;
    }
}
