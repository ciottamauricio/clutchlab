<?php

namespace App\PlayerStats\Domain;

// What one player did over a set of matches: their kills and what they were, and their deaths.
final readonly class PlayerCounts
{
    public function __construct(
        public string $steamId,
        public int $kills = 0,
        public int $headshots = 0,
        public int $openingKills = 0,
        public int $clutchRounds = 0,
        public int $ecoKills = 0,
        public int $legKills = 0,
        public int $deaths = 0,
        public int $openingDeaths = 0,
    ) {}
}
