<?php

namespace App\PlayerStats\Domain;

// One player's scoreboard row in one match.
final readonly class ScoreboardLine
{
    public function __construct(
        public int $matchId,
        public int $kills,
        public int $deaths,
        public ?string $side,
    ) {}
}
