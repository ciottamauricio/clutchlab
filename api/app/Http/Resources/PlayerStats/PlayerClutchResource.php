<?php

namespace App\Http\Resources\PlayerStats;

use App\PlayerStats\Application\ClutchInMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// One clutch in a list across matches, with its match's demo and tick rate for the jump.
/** @property ClutchInMatch $resource */
class PlayerClutchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clutch = $this->resource->clutch;
        $match = $this->resource->match;

        return [
            'match_id' => $match->id,
            'map' => $match->map,
            'demo' => $match->demo,
            'tick_rate' => (float) $match->tickRate,
            'round' => $clutch->round,
            'size' => $clutch->size,
            'killer_name' => $clutch->clutcherName,
            'kills' => ClutchKills::of($clutch),
        ];
    }
}
