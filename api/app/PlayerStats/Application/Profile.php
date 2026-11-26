<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\StatLine;
use App\PlayerStats\Domain\WeaponKills;

final readonly class Profile
{
    /**
     * @param  list<RecentGame>  $recent  oldest first
     * @param  list<WeaponKills>  $weapons
     * @param  list<ClutchInMatch>  $clutches  newest first
     */
    public function __construct(
        public StatLine $stats,
        public array $recent = [],
        public array $weapons = [],
        public array $clutches = [],
    ) {}
}
