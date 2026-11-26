<?php

namespace App\PlayerStats\Ports;

use App\PlayerStats\Domain\RosterPlayer;

// A team's roster: the Steam IDs whose stats are the team's.
interface Rosters
{
    /** @return list<RosterPlayer> */
    public function of(int $teamId): array;
}
