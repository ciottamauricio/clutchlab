<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchPlayerStatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'steam_id' => $this->steam_id,
            'name' => $this->name,
            'team_side' => $this->team_side,
            'kills' => $this->kills,
            'deaths' => $this->deaths,
            'assists' => $this->assists,
            'headshots' => $this->headshots,
        ];
    }
}
