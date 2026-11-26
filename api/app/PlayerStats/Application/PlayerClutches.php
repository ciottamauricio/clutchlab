<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\Clutches;
use App\PlayerStats\Ports\Matches;
use App\PlayerStats\Ports\MatchEvents;

// Every clutch a player won in the caller's visible matches, all time: what the board's clutch
// cell opens, whatever date range the cell counted (spec 004 Q7 keeps that).
final readonly class PlayerClutches
{
    public function __construct(
        private Matches $matches,
        private MatchEvents $events,
    ) {}

    /** @return list<ClutchInMatch> by match, then round */
    public function execute(int $userId, string $steamId): array
    {
        $scope = $this->matches->visibleTo($userId);
        if ($scope->isEmpty()) {
            return [];
        }

        $found = [];
        foreach (Clutches::fromKills($this->events->clutchKills($scope->ids(), $steamId)) as $clutch) {
            $match = $scope->info($clutch->matchId);
            if ($match) {
                $found[] = new ClutchInMatch($clutch, $match);
            }
        }

        return $found;
    }
}
