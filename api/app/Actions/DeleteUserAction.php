<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

// Admin deletes a user. Their team memberships and tokens go with them; uploaded matches
// survive with a null owner (the FK is nullOnDelete). An admin can't delete their own
// account — which also means the platform can never be left with no admin.
class DeleteUserAction
{
    public function execute(User $actor, User $user): void
    {
        if ($actor->id === $user->id) {
            throw ValidationException::withMessages(['user' => 'admin.cannot_delete_self']);
        }

        $user->tokens()->delete();
        $user->delete();
    }
}
