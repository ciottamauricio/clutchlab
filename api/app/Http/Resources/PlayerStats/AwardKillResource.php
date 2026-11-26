<?php

namespace App\Http\Resources\PlayerStats;

use App\PlayerStats\Application\KillInMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// One kill behind an award row, with its match's demo and tick rate for the "watch in game" jump.
/** @property KillInMatch $resource */
class AwardKillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $kill = $this->resource->kill;
        $match = $this->resource->match;

        return [
            'match_id' => $match->id,
            'map' => $match->map,
            'demo' => $match->demo,
            'tick_rate' => (float) $match->tickRate,
            'round' => $kill->round,
            'killer_name' => $kill->killerName,
            'victim_name' => $kill->victimName,
            'weapon' => $kill->weapon,
            'side' => $kill->side,
            'headshot' => $kill->headshot,
            'tick' => $kill->tick,
            'hitgroups' => $kill->hitgroups,
        ];
    }
}
