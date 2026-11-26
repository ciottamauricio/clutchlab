<?php

namespace App\Http\Resources\PlayerStats;

use App\PlayerStats\Domain\StatLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property StatLine $resource */
class StatLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $line = $this->resource;

        return [
            'games' => $line->games,
            'kills' => $line->kills,
            'deaths' => $line->deaths,
            'kd' => $line->kd(),
            'hs_pct' => $line->hsPct(),
            'entry_kills' => $line->openingKills,
            'first_deaths' => $line->openingDeaths,
            'clutches' => $line->clutches,
        ];
    }
}
