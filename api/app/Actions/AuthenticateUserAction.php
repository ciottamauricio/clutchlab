<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateUserAction
{
    public function execute(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            // Code, not a sentence — the frontend localizes it.
            throw ValidationException::withMessages(['email' => 'auth.invalid_credentials']);
        }

        return $user;
    }
}
