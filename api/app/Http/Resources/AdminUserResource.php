<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_admin' => $this->isAdmin(),
            'steam_id' => $this->steam_id,
            'created_at' => $this->created_at,
            // Read-only context: which teams this user belongs to and their role there. The
            // admin panel manages the global role; team membership is the team owner's job.
            'teams' => $this->whenLoaded('teams', fn () => $this->teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'role' => $team->pivot->role,
            ])),
        ];
    }
}
