<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'role' => $this->role->value,
            'is_admin' => $this->isAdmin(),
            'steam_id' => $this->steam_id,
            'created_at' => $this->created_at,
            // Self-maintained profile: in-game role, bio, and gear/setup.
            'player_role' => $this->player_role,
            'bio' => $this->bio,
            'gear' => [
                'pc' => $this->pc,
                'mouse' => $this->mouse,
                'keyboard' => $this->keyboard,
                'headset' => $this->headset,
                'monitor' => $this->monitor,
                'mousepad' => $this->mousepad,
            ],
        ];
    }
}
