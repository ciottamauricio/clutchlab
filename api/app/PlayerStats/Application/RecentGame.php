<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\MatchInfo;

final readonly class RecentGame
{
    public function __construct(
        public MatchInfo $match,
        public int $kills,
        public int $deaths,
        public float $kd,
        public ?bool $won,
    ) {}
}
