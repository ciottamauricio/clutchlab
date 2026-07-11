<?php

namespace App\Actions;

use App\Models\Tactic;
use App\Models\User;

class CreateTacticAction
{
    public function execute(User $owner, string $name, ?string $map = null): Tactic
    {
        return $owner->tactics()->create([
            'name' => $name,
            'map' => $map,
            'board' => ['pieces' => []],
        ]);
    }
}
