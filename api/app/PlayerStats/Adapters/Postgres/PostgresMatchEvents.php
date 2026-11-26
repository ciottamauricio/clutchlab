<?php

namespace App\PlayerStats\Adapters\Postgres;

use App\PlayerStats\Domain\AwardKey;
use App\PlayerStats\Domain\HitGroups;
use App\PlayerStats\Domain\Kill;
use App\PlayerStats\Domain\PlayerCounts;
use App\PlayerStats\Domain\PlayerEntry;
use App\PlayerStats\Domain\ScoreboardLine;
use App\PlayerStats\Domain\SideCounts;
use App\PlayerStats\Domain\WeaponKills;
use App\PlayerStats\Domain\Weapons;
use App\PlayerStats\Ports\MatchEvents;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

// The counts, in Postgres SQL over the worker's analytics tables (kill_events, match_player_stats).
// Each rule is written once here, as a fragment below, and held equal to the domain's definition
// by tests/Postgres/PlayerStats/MatchEventsContractTest. Only analytics tables are named: the
// matches arrive as ids (spec 004 Q9), and the contract test runs with only that schema on the
// search_path, so a join to a public table fails there.
final class PostgresMatchEvents implements MatchEvents
{
    // A won clutch is one round, however many kills it took: distinct (match, round) pairs.
    private const CLUTCH_ROUNDS = "count(DISTINCT CASE WHEN clutch > 0 THEN match_id::text || '-' || round END)";

    private const KILL_COLUMNS = ['match_id', 'round', 'killer_steam_id', 'victim_steam_id', 'weapon', 'headshot', 'opening', 'clutch', 'side', 'killer_team', 'tick', 'hitgroups', 'map', 'killer_name', 'victim_name'];

    public function __construct(private ConnectionInterface $db) {}

    public function counts(array $matchIds, ?array $only = null, ?string $map = null): array
    {
        $eco = implode(', ', array_fill(0, count(Weapons::ECO), '?'));
        $offence = $this->kills($matchIds, $map, 'killer_steam_id', $only)
            ->groupBy('killer_steam_id')
            ->selectRaw('killer_steam_id AS steam_id, count(*) AS kills, sum(CASE WHEN headshot THEN 1 ELSE 0 END) AS headshots,
                count(*) FILTER (WHERE opening) AS opening_kills, '.self::CLUTCH_ROUNDS." AS clutch_rounds,
                count(*) FILTER (WHERE weapon IN ($eco)) AS eco_kills, count(*) FILTER (WHERE ".self::legsDominant().') AS leg_kills', Weapons::ECO)
            ->get();
        $defence = $this->kills($matchIds, $map, 'victim_steam_id', $only)
            ->groupBy('victim_steam_id')
            ->selectRaw('victim_steam_id AS steam_id, count(*) AS deaths, count(*) FILTER (WHERE opening) AS opening_deaths')
            ->get();

        $by = [];
        foreach ($offence as $r) {
            $by['#'.$r->steam_id] = ['kills' => (int) $r->kills, 'headshots' => (int) $r->headshots, 'openingKills' => (int) $r->opening_kills,
                'clutchRounds' => (int) $r->clutch_rounds, 'ecoKills' => (int) $r->eco_kills, 'legKills' => (int) $r->leg_kills];
        }
        foreach ($defence as $r) {
            $by['#'.$r->steam_id] = ($by['#'.$r->steam_id] ?? []) + ['deaths' => (int) $r->deaths, 'openingDeaths' => (int) $r->opening_deaths];
        }
        ksort($by, SORT_STRING);

        return array_values(array_map(fn ($key, $c) => new PlayerCounts(substr($key, 1), ...$c), array_keys($by), $by));
    }

    public function weaponKills(array $matchIds, ?array $only = null, ?string $map = null): array
    {
        return $this->kills($matchIds, $map, 'killer_steam_id', $only)
            ->where('weapon', '<>', '')
            ->groupBy('killer_steam_id', 'weapon')
            ->orderBy('killer_steam_id')->orderBy('weapon')
            ->selectRaw('killer_steam_id, weapon, count(*) AS kills')
            ->get()
            ->map(fn ($r) => new WeaponKills((string) $r->killer_steam_id, (string) $r->weapon, (int) $r->kills))
            ->all();
    }

    // The latest name is the one from the first of the given matches the player appears in; the
    // list is ordered by the database's collation, which no PHP sort here reproduces.
    public function players(array $newestFirst, ?array $only = null): array
    {
        if ($newestFirst === []) {
            return [];
        }

        return $this->db->table('match_player_stats')
            ->whereIn('match_id', $newestFirst)
            ->when($only !== null, fn ($q) => $q->whereIn('steam_id', $only))
            ->groupBy('steam_id')
            ->selectRaw('steam_id, (array_agg(name ORDER BY array_position(?::bigint[], match_id)))[1] AS name, count(DISTINCT match_id) AS games', ['{'.implode(',', $newestFirst).'}'])
            ->orderBy('name')->orderBy('steam_id')
            ->get()
            ->map(fn ($r) => new PlayerEntry((string) $r->steam_id, (string) $r->name, (int) $r->games))
            ->all();
    }

    public function scoreboard(array $matchIds, string $steamId): array
    {
        return $this->db->table('match_player_stats')
            ->whereIn('match_id', $matchIds)
            ->where('steam_id', $steamId)
            ->orderBy('match_id')
            ->get(['match_id', 'kills', 'deaths', 'team_side'])
            ->map(fn ($r) => new ScoreboardLine((int) $r->match_id, (int) $r->kills, (int) $r->deaths, $r->team_side))
            ->all();
    }

    public function sides(int $matchId): array
    {
        $sides = [];
        $scoreboards = $this->db->table('match_player_stats')->where('match_id', $matchId)->groupBy('team_side')
            ->selectRaw('team_side, sum(kills) AS kills, sum(deaths) AS deaths, sum(assists) AS assists, sum(headshots) AS headshots')->get();
        foreach ($scoreboards as $r) {
            $sides[(string) $r->team_side] = ['kills' => (int) $r->kills, 'deaths' => (int) $r->deaths, 'assists' => (int) $r->assists, 'headshots' => (int) $r->headshots];
        }

        $byTeam = $this->db->table('kill_events')->where('match_id', $matchId)->groupBy('killer_team')
            ->selectRaw('killer_team, count(*) FILTER (WHERE opening) AS opening_kills, '.self::CLUTCH_ROUNDS.' AS clutch_rounds')->get();
        foreach ($byTeam as $r) {
            $team = (string) $r->killer_team;
            if ((int) $r->opening_kills > 0 || (int) $r->clutch_rounds > 0) {
                $sides[$team] = ($sides[$team] ?? []) + ['openingKills' => (int) $r->opening_kills, 'clutchRounds' => (int) $r->clutch_rounds];
            }
        }

        return array_map(fn ($c) => new SideCounts(...$c), $sides);
    }

    public function clutchKills(array $matchIds, ?string $clutcher = null): array
    {
        return $this->kills($matchIds, null)
            ->where('clutch', '>', 0)
            ->when($clutcher !== null, fn ($q) => $q->where('killer_steam_id', $clutcher))
            ->orderBy('match_id')->orderBy('round')->orderBy('tick')
            ->get(self::KILL_COLUMNS)
            ->map(fn ($r) => $this->kill($r))
            ->all();
    }

    public function awardKills(array $matchIds, AwardKey $key, string $steamId, ?string $map = null, ?string $weapon = null): array
    {
        if ($key === AwardKey::OneTrick && $weapon === null) {
            return [];
        }

        $q = $this->kills($matchIds, $map);
        match ($key) {
            AwardKey::Legshots => $q->where('killer_steam_id', $steamId)->whereRaw(self::legsDominant()),
            AwardKey::Eco => $q->where('killer_steam_id', $steamId)->whereIn('weapon', Weapons::ECO),
            AwardKey::Clutches => $q->where('killer_steam_id', $steamId)->where('clutch', '>', 0),
            AwardKey::Headshot => $q->where('killer_steam_id', $steamId)->where('headshot', true),
            AwardKey::OneTrick => $q->where('killer_steam_id', $steamId)->where('weapon', $weapon),
            AwardKey::FirstBlood => $q->where('victim_steam_id', $steamId)->where('opening', true),
        };

        return $q->orderBy('match_id')->orderBy('tick')->get(self::KILL_COLUMNS)->map(fn ($r) => $this->kill($r))->all();
    }

    // HitGroups::legsDominant in SQL: the legs took damage, and at least as much as every other zone.
    private static function legsDominant(): string
    {
        $legs = "(hitgroups->>'legs')::int";

        return "$legs > 0 AND ".implode(' AND ', array_map(fn ($zone) => "$legs >= COALESCE((hitgroups->>'$zone')::int, 0)", HitGroups::OTHER_ZONES));
    }

    /** @param  list<string>|null  $only  narrows $column to these Steam IDs; [] means nobody */
    private function kills(array $matchIds, ?string $map, ?string $column = null, ?array $only = null): Builder
    {
        return $this->db->table('kill_events')
            ->whereIn('match_id', $matchIds)
            ->when($map !== null, fn ($q) => $q->where('map', $map))
            ->when($column !== null && $only !== null, fn ($q) => $q->whereIn($column, $only));
    }

    private function kill(object $r): Kill
    {
        return new Kill(
            (int) $r->match_id, (int) $r->round, (string) $r->killer_steam_id, (string) $r->victim_steam_id, $r->weapon, (bool) $r->headshot,
            (bool) $r->opening, (int) $r->clutch, $r->side, $r->killer_team, $r->tick === null ? null : (int) $r->tick,
            $r->hitgroups === null ? null : json_decode($r->hitgroups, true), $r->map, $r->killer_name, $r->victim_name,
        );
    }
}
