<?php

namespace App\Http\Resources;

use App\Authorization\PermissionCatalog;
use App\Contracts\PermissionService;
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
            // App-scope abilities the client uses to show/hide whole pages (Awards, Search,
            // Tactics). Admins bypass every check, so they get the full set. Team-scope
            // abilities aren't here — they're contextual (per team) and checked per action.
            'abilities' => $this->appAbilities(),
            // Whether the user may upload at all (uploader in some team, or admin) — lets the
            // client hide the upload UI. Team-specific upload rights are still checked per team.
            'can_upload' => $this->isAdmin() || app(PermissionService::class)->canOnAnyTeam($this->resource, 'team.upload_match'),
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

    /** @return list<string> */
    private function appAbilities(): array
    {
        if ($this->isAdmin()) {
            return array_keys(array_filter(
                PermissionCatalog::abilities(),
                fn ($a) => $a[0] === PermissionCatalog::APP,
            ));
        }

        return app(PermissionService::class)->appGrantsFor($this->resource);
    }
}
