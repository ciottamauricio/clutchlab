<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\Clutch;
use App\PlayerStats\Domain\Clutches;
use App\PlayerStats\Ports\MatchEvents;

final readonly class MatchClutches
{
    public function __construct(private MatchEvents $events) {}

    /** @return list<Clutch> by round */
    public function execute(int $matchId): array
    {
        return Clutches::fromKills($this->events->clutchKills([$matchId]));
    }
}
