<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\Kill;
use App\PlayerStats\Domain\MatchInfo;

// A kill with the match it happened in, so the dialog can jump into its demo.
final readonly class KillInMatch
{
    public function __construct(
        public Kill $kill,
        public MatchInfo $match,
    ) {}
}
