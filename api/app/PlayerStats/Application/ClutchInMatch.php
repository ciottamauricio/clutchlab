<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\Clutch;
use App\PlayerStats\Domain\MatchInfo;

// A clutch with the match it happened in, so a list across matches can jump into each demo.
final readonly class ClutchInMatch
{
    public function __construct(
        public Clutch $clutch,
        public MatchInfo $match,
    ) {}
}
