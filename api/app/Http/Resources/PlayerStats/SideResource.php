<?php

namespace App\Http\Resources\PlayerStats;

use App\PlayerStats\Application\SideLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// One side of the match page's comparison. Its name and score come from the match itself.
/** @property SideLine $resource */
class SideResource extends JsonResource
{
    public function __construct(SideLine $line, private string $side, private ?string $name, private ?int $score)
    {
        parent::__construct($line);
    }

    public function toArray(Request $request): array
    {
        $counts = $this->resource->counts;

        return [
            'side' => $this->side,
            'name' => $this->name ?: ($this->side === 'CT' ? 'Counter-Terrorists' : 'Terrorists'),
            'score' => (int) $this->score,
            'kills' => $counts->kills,
            'deaths' => $counts->deaths,
            'assists' => $counts->assists,
            'hs_pct' => $this->resource->hsPct,
            'opening_kills' => $counts->openingKills,
            'opening_deaths' => $this->resource->openingDeaths,
            'clutches' => $counts->clutchRounds,
        ];
    }
}
