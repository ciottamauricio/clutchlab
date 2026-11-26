<?php

namespace App\Http\Resources\PlayerStats;

use App\PlayerStats\Domain\Clutch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// One clutch in one match's list.
/** @property Clutch $resource */
class MatchClutchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'round' => $this->resource->round,
            'size' => $this->resource->size,
            'killer_name' => $this->resource->clutcherName,
            'killer_steam_id' => $this->resource->clutcherId,
            'kills' => ClutchKills::of($this->resource),
        ];
    }
}
