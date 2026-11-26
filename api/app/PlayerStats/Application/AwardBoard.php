<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\Award;
use App\PlayerStats\Domain\PlayerEntry;

final readonly class AwardBoard
{
    /** @var array<string, string> '#'.Steam ID → latest name */
    private array $names;

    /**
     * @param  list<Award>  $awards
     * @param  list<PlayerEntry>  $players
     */
    public function __construct(public array $awards, array $players = [])
    {
        $names = [];
        foreach ($players as $p) {
            $names['#'.$p->steamId] = $p->name;
        }
        $this->names = $names;
    }

    // A player's latest name; one on no scoreboard (the kills with no killer) shows as their id.
    public function nameOf(string $steamId): string
    {
        return $this->names['#'.$steamId] ?? $steamId;
    }
}
