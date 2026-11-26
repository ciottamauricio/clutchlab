<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\SideCounts;

final readonly class SideLine
{
    public function __construct(
        public SideCounts $counts,
        public int $openingDeaths,
        public int $hsPct,
    ) {}
}
