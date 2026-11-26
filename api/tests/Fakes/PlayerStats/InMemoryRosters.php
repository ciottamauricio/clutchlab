<?php

namespace Tests\Fakes\PlayerStats;

use App\PlayerStats\Domain\RosterPlayer;
use App\PlayerStats\Ports\Rosters;

class InMemoryRosters implements Rosters
{
    /** @var array<int, list<RosterPlayer>> */
    private array $rosters = [];

    public function add(int $teamId, RosterPlayer $player): void
    {
        $this->rosters[$teamId][] = $player;
    }

    public function of(int $teamId): array
    {
        return $this->rosters[$teamId] ?? [];
    }
}
