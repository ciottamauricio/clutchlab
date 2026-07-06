<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at,
            'members' => TeamMemberResource::collection($this->whenLoaded('members')),
            'players' => TeamPlayerResource::collection($this->whenLoaded('players')),
            // On list responses (members not loaded) expose the caller's own role.
            'my_role' => $this->whenPivotLoaded('team_user', fn () => $this->pivot->role),
        ];
    }
}
