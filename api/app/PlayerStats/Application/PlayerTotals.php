<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\MatchScope;
use App\PlayerStats\Domain\PlayerCounts;
use App\PlayerStats\Domain\StatLine;
use App\PlayerStats\Ports\MatchEvents;

// Per-player totals over any set of matches: the board's line for each, with their latest name.
// The board uses it; spec 003's analyst is meant to (AC-9).
final readonly class PlayerTotals
{
    public function __construct(private MatchEvents $events) {}

    /**
     * @param  list<string>|null  $only
     * @return list<Totals> everyone who killed, died or played, by Steam ID
     */
    public function over(MatchScope $scope, ?array $only = null): array
    {
        if ($scope->isEmpty()) {
            return [];
        }

        // Keyed '#'.Steam ID: a numeric Steam ID used as a key would come back as an int.
        $counts = [];
        foreach ($this->events->counts($scope->ids(), $only) as $c) {
            $counts['#'.$c->steamId] = $c;
        }
        $players = [];
        foreach ($this->events->players($scope->newestFirst(), $only) as $p) {
            $players['#'.$p->steamId] = $p;
        }

        $keys = array_unique([...array_keys($counts), ...array_keys($players)]);
        sort($keys, SORT_STRING);

        return array_map(function (string $key) use ($counts, $players) {
            $steamId = substr($key, 1);
            $player = $players[$key] ?? null;

            return new Totals($steamId, $player?->name, StatLine::of($counts[$key] ?? new PlayerCounts($steamId), $player->games ?? 0));
        }, $keys);
    }
}
