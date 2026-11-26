<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\AwardKey;
use App\PlayerStats\Domain\TopWeapon;
use App\PlayerStats\Ports\Matches;
use App\PlayerStats\Ports\MatchEvents;

// The kills behind one player's row on one award, over the caller's visible matches.
final readonly class AwardKills
{
    public function __construct(
        private Matches $matches,
        private MatchEvents $events,
    ) {}

    /** @return list<KillInMatch> by match, then tick */
    public function execute(int $userId, AwardKey $key, string $steamId, ?string $map = null): array
    {
        $scope = $this->matches->visibleTo($userId);
        if ($scope->isEmpty()) {
            return [];
        }

        // One-trick lists the weapon the award named, picked by the same rule.
        $weapon = null;
        if ($key === AwardKey::OneTrick) {
            $kills = [];
            foreach ($this->events->weaponKills($scope->ids(), [$steamId], $map) as $w) {
                $kills[$w->weapon] = $w->kills;
            }
            $weapon = TopWeapon::of($kills);
        }

        $found = [];
        foreach ($this->events->awardKills($scope->ids(), $key, $steamId, $map, $weapon) as $kill) {
            $match = $scope->info($kill->matchId);
            if ($match) {
                $found[] = new KillInMatch($kill, $match);
            }
        }

        return $found;
    }
}
