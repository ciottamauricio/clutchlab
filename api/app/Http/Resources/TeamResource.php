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
            // What the caller may do with this team, resolved through the policy (so it honors
            // the live grants and the admin bypass). The client shows controls accordingly; the
            // server still enforces each request.
            'can' => [
                'manage_members' => (bool) $request->user()?->can('manageMembers', $this->resource),
                'manage_roster' => (bool) $request->user()?->can('manageRoster', $this->resource),
                'update' => (bool) $request->user()?->can('update', $this->resource),
                'upload_match' => (bool) $request->user()?->can('uploadMatch', $this->resource),
                'manage_trainings' => (bool) $request->user()?->can('create', [\App\Models\TrainingSession::class, $this->resource]),
            ],
        ];
    }
}
