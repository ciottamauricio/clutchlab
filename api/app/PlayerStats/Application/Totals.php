<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\StatLine;

// One player's totals over a set of matches. No name when they're on no scoreboard there.
final readonly class Totals
{
    public function __construct(
        public string $steamId,
        public ?string $name,
        public StatLine $line,
    ) {}
}
