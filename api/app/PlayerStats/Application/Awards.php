<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\AwardRules;
use App\PlayerStats\Ports\Matches;
use App\PlayerStats\Ports\MatchEvents;
use App\PlayerStats\Ports\Rosters;

// The awards over every match the caller can see; a team (the caller's own) narrows the field to
// its roster, a map narrows the kills.
final readonly class Awards
{
    public function __construct(
        private Matches $matches,
        private Rosters $rosters,
        private MatchEvents $events,
    ) {}

    public function execute(int $userId, ?int $teamId = null, ?string $map = null): AwardBoard
    {
        $scope = $this->matches->visibleTo($userId);
        if ($scope->isEmpty()) {
            return new AwardBoard([]);
        }

        // An empty roster ranks nobody, not everybody.
        $only = $teamId === null ? null : array_map(fn ($p) => $p->steamId, $this->rosters->of($teamId));

        $counts = $this->events->counts($scope->ids(), $only, $map);
        $awards = AwardRules::awards($counts, $this->events->weaponKills($scope->ids(), $only, $map));

        // Names come from every visible match, whatever the map.
        $ranked = array_map(fn ($c) => $c->steamId, $counts);

        return new AwardBoard($awards, $ranked === [] ? [] : $this->events->players($scope->newestFirst(), $ranked));
    }
}
