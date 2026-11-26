<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\Clutches;
use App\PlayerStats\Domain\MatchScope;
use App\PlayerStats\Domain\Outcome;
use App\PlayerStats\Domain\StatLine;
use App\PlayerStats\Domain\TopWeapon;
use App\PlayerStats\Domain\WeaponKills;
use App\PlayerStats\Ports\Matches;
use App\PlayerStats\Ports\MatchEvents;

// A user's profile analytics for their linked Steam ID, over every match they can see: their line
// (the board's rules), recent form, top weapons, and latest clutches.
final readonly class PlayerProfile
{
    private const RECENT = 10;

    private const WEAPONS = 6;

    private const CLUTCHES = 8;

    public function __construct(
        private Matches $matches,
        private MatchEvents $events,
        private PlayerTotals $totals,
    ) {}

    // Null without a linked Steam ID.
    public function execute(int $userId, ?string $steamId): ?Profile
    {
        if (! $steamId) {
            return null;
        }

        $scope = $this->matches->visibleTo($userId);
        if ($scope->isEmpty()) {
            return new Profile(StatLine::none());
        }

        $line = $this->totals->over($scope, [$steamId])[0]->line ?? StatLine::none();

        return new Profile($line, $this->recent($scope, $steamId), $this->weapons($scope, $steamId), $this->clutches($scope, $steamId));
    }

    // The last games, by when they were played (else uploaded), returned oldest first for the chart.
    /** @return list<RecentGame> */
    private function recent(MatchScope $scope, string $steamId): array
    {
        $games = [];
        foreach ($this->events->scoreboard($scope->ids(), $steamId) as $line) {
            $match = $scope->info($line->matchId);
            if ($match) {
                $games[] = new RecentGame($match, $line->kills, $line->deaths, StatLine::kdOf($line->kills, $line->deaths), Outcome::won($match->scoreCt, $match->scoreT, $line->side));
            }
        }
        usort($games, fn (RecentGame $a, RecentGame $b) => [$b->match->playedOrUploaded(), $b->match->id] <=> [$a->match->playedOrUploaded(), $a->match->id]);

        return array_reverse(array_slice($games, 0, self::RECENT));
    }

    /** @return list<WeaponKills> */
    private function weapons(MatchScope $scope, string $steamId): array
    {
        $kills = [];
        foreach ($this->events->weaponKills($scope->ids(), [$steamId]) as $w) {
            $kills[$w->weapon] = $w->kills;
        }

        return array_map(fn (string $weapon) => new WeaponKills($steamId, $weapon, $kills[$weapon]), array_slice(TopWeapon::ranked($kills), 0, self::WEAPONS));
    }

    // The latest clutches won: by when the match was played (else uploaded), then the later round.
    /** @return list<ClutchInMatch> */
    private function clutches(MatchScope $scope, string $steamId): array
    {
        $clutches = [];
        foreach (Clutches::fromKills($this->events->clutchKills($scope->ids(), $steamId)) as $clutch) {
            $match = $scope->info($clutch->matchId);
            if ($match) {
                $clutches[] = new ClutchInMatch($clutch, $match);
            }
        }
        usort($clutches, fn (ClutchInMatch $a, ClutchInMatch $b) => [$b->match->playedOrUploaded(), $b->clutch->round, $b->match->id] <=> [$a->match->playedOrUploaded(), $a->clutch->round, $a->match->id]);

        return array_slice($clutches, 0, self::CLUTCHES);
    }
}
