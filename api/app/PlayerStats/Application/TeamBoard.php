<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\Ranking;
use App\PlayerStats\Domain\StatLine;
use App\PlayerStats\Ports\Matches;
use App\PlayerStats\Ports\Rosters;

// The team's stat board: every rostered player's line over the team's own matches, so every member
// sees the same numbers, optionally only the matches played in a date range.
final readonly class TeamBoard
{
    public function __construct(
        private Matches $matches,
        private Rosters $rosters,
        private PlayerTotals $totals,
    ) {}

    /** @return list<BoardRow> by kills, then Steam ID */
    public function execute(int $teamId, ?string $from = null, ?string $to = null): array
    {
        $roster = $this->rosters->of($teamId);
        if ($roster === []) {
            return [];
        }

        // No game in range is an empty board, not the roster with zeroes.
        $scope = $this->matches->ofTeam($teamId, $from, $to);
        if ($scope->isEmpty()) {
            return [];
        }

        $totals = [];
        foreach ($this->totals->over($scope, array_map(fn ($p) => $p->steamId, $roster)) as $t) {
            $totals['#'.$t->steamId] = $t;
        }

        // The owner's nickname, else the latest demo name, else the Steam ID itself.
        $rows = array_map(function ($p) use ($totals) {
            $t = $totals['#'.$p->steamId] ?? null;

            return new BoardRow($p->steamId, $p->nickname ?: ($t?->name ?? $p->steamId), $t?->line ?? StatLine::none());
        }, $roster);

        return Ranking::rank($rows, fn (BoardRow $r) => $r->line->kills, fn (BoardRow $r) => $r->steamId);
    }
}
