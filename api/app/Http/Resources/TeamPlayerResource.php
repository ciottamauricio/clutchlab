<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// A rostered in-game player (by steam_id). The display name is resolved on the frontend
// from the player catalog; here we carry only what the roster itself owns.
class TeamPlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'steam_id' => $this->steam_id,
            'nickname' => $this->nickname,
        ];
    }
}
