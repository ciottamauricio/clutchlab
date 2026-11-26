<?php

namespace App\Http\Resources\PlayerStats;

use App\PlayerStats\Domain\PlayerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property PlayerEntry $resource */
class PlayerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['steam_id' => $this->resource->steamId, 'name' => $this->resource->name, 'match_count' => $this->resource->games];
    }
}
