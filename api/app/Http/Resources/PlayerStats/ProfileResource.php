<?php

namespace App\Http\Resources\PlayerStats;

use App\PlayerStats\Application\ClutchInMatch;
use App\PlayerStats\Application\Profile;
use App\PlayerStats\Application\RecentGame;
use App\PlayerStats\Domain\Kill;
use App\PlayerStats\Domain\WeaponKills;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// The profile, as it has always been written: dates, tick rate and hitgroups as Postgres prints
// them (the other answers cast them).
/** @property Profile|null $resource */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource;
        if ($profile === null) {
            return ['stats' => null, 'recent' => [], 'weapons' => [], 'clutches' => []];
        }

        return [
            'stats' => (new StatLineResource($profile->stats))->toArray($request),
            'recent' => array_map(fn (RecentGame $g) => [
                'match_id' => $g->match->id,
                'map' => $g->match->map,
                'played_at' => $g->match->playedAt,
                'duration_seconds' => $g->match->duration !== null ? (int) $g->match->duration : null,
                'kills' => $g->kills,
                'deaths' => $g->deaths,
                'kd' => $g->kd,
                'won' => $g->won,
            ], $profile->recent),
            'weapons' => array_map(fn (WeaponKills $w) => ['weapon' => $w->weapon, 'kills' => $w->kills], $profile->weapons),
            'clutches' => array_map(fn (ClutchInMatch $c) => [
                'match_id' => $c->match->id,
                'round' => $c->clutch->round,
                'map' => $c->match->map,
                'played_at' => $c->match->playedAt,
                'tick_rate' => $c->match->tickRate,
                'demo' => $c->match->demo,
                'size' => $c->clutch->size,
                'kills' => array_map(fn (Kill $k) => [
                    'victim_name' => $k->victimName,
                    'weapon' => $k->weapon,
                    'side' => $k->side,
                    'headshot' => $k->headshot,
                    'tick' => $k->tick,
                    'hitgroups' => JsonbText::of($k->hitgroups),
                ], $c->clutch->kills),
            ], $profile->clutches),
        ];
    }
}
