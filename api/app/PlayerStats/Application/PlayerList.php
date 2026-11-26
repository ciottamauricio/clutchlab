<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\PlayerEntry;
use App\PlayerStats\Domain\Ranking;
use App\PlayerStats\Ports\Matches;
use App\PlayerStats\Ports\MatchEvents;

// Every player seen in demos, by Steam ID with their latest name: there's no players table.
final readonly class PlayerList
{
    public function __construct(
        private Matches $matches,
        private MatchEvents $events,
    ) {}

    // In the caller's visible matches, most-played first: the roster picker.
    /** @return list<PlayerEntry> */
    public function seenBy(int $userId): array
    {
        $scope = $this->matches->visibleTo($userId);
        if ($scope->isEmpty()) {
            return [];
        }

        return Ranking::rank($this->events->players($scope->newestFirst()), fn (PlayerEntry $p) => $p->games, fn (PlayerEntry $p) => $p->steamId);
    }

    // In every match, by name: the admin's pick list for linking an account.
    /** @return list<PlayerEntry> */
    public function all(): array
    {
        $scope = $this->matches->all();

        return $scope->isEmpty() ? [] : $this->events->players($scope->newestFirst());
    }
}
