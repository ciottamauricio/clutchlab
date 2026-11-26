<?php

namespace App\PlayerStats\Ports;

use App\PlayerStats\Domain\AwardKey;
use App\PlayerStats\Domain\Kill;
use App\PlayerStats\Domain\PlayerCounts;
use App\PlayerStats\Domain\PlayerEntry;
use App\PlayerStats\Domain\ScoreboardLine;
use App\PlayerStats\Domain\SideCounts;
use App\PlayerStats\Domain\WeaponKills;

// What happened in a set of matches: the worker's kill events and scoreboards (the analytics
// schema). It's told which matches by id only, and counts by the domain's rules: a kill flagged
// opening is an opening kill, a (match, round) with clutch-flagged kills is one clutch won, an
// eco kill is a Weapons::ECO weapon, a leg kill is HitGroups::legsDominant.
//
// $only narrows to these Steam IDs (killers for kills, victims for deaths), and [] means nobody;
// null means everyone. $map narrows to kills on that map.
interface MatchEvents
{
    /**
     * @param  list<int>  $matchIds
     * @param  list<string>|null  $only
     * @return list<PlayerCounts> everyone who killed or died, by Steam ID
     */
    public function counts(array $matchIds, ?array $only = null, ?string $map = null): array;

    /**
     * Kills by weapon, for kills that have one.
     *
     * @param  list<int>  $matchIds
     * @param  list<string>|null  $only
     * @return list<WeaponKills>
     */
    public function weaponKills(array $matchIds, ?array $only = null, ?string $map = null): array;

    /**
     * Everyone on the scoreboards of these matches: the name from the first of $newestFirst they
     * played in, and how many they played. In name order, as the database collates names, then by
     * Steam ID.
     *
     * @param  list<int>  $newestFirst
     * @param  list<string>|null  $only
     * @return list<PlayerEntry>
     */
    public function players(array $newestFirst, ?array $only = null): array;

    /**
     * @param  list<int>  $matchIds
     * @return list<ScoreboardLine> one per match the player is on the scoreboard of
     */
    public function scoreboard(array $matchIds, string $steamId): array;

    /**
     * One match's sides: their scoreboards summed (by team_side), their opening kills and their
     * clutch rounds (by the killer's team).
     *
     * @return array<string, SideCounts> by side
     */
    public function sides(int $matchId): array;

    /**
     * The kills that won clutches, by match, round and tick.
     *
     * @param  list<int>  $matchIds
     * @return list<Kill>
     */
    public function clutchKills(array $matchIds, ?string $clutcher = null): array;

    /**
     * The kills behind one player's row on one award, by match and tick: their own kills, or, for
     * first blood, their opening deaths. One-trick takes the weapon to list; null lists none.
     *
     * @param  list<int>  $matchIds
     * @return list<Kill>
     */
    public function awardKills(array $matchIds, AwardKey $key, string $steamId, ?string $map = null, ?string $weapon = null): array;
}
