<?php

namespace App\PlayerStats\Domain;

// One side of one match: its scoreboard summed, its opening kills and the rounds it won in a clutch.
final readonly class SideCounts
{
    public function __construct(
        public int $kills = 0,
        public int $deaths = 0,
        public int $assists = 0,
        public int $headshots = 0,
        public int $openingKills = 0,
        public int $clutchRounds = 0,
    ) {}
}
