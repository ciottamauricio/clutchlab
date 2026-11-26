<?php

namespace App\PlayerStats\Domain;

// One row of a leaderboard: a count or a share, and what's behind it for the ratio awards (the
// kills a headshot rate is over; a one-trick's weapon).
final readonly class Leader
{
    public function __construct(
        public string $steamId,
        public int|float $value,
        public ?int $kills = null,
        public ?string $weapon = null,
    ) {}
}
