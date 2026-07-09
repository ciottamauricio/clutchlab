<?php

namespace App\Actions;

use App\Models\User;

// Self-service password change (current password required — no email flow yet). On success,
// every other session is signed out; the current token is kept so the user isn't logged out
// by their own change.
class ChangePasswordAction
{
    public function execute(User $user, string $newPassword, mixed $currentToken): void
    {
        $user->password = $newPassword;
        $user->save();

        $user->tokens()->when(
            $currentToken,
            fn ($q) => $q->whereKeyNot($currentToken->id),
        )->delete();
    }
}
