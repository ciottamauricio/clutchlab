<?php

namespace App\Actions;

use App\Models\GameMatch;
use App\Models\KillEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Superlative leaderboards ("awards") from kill_events across every match the caller can see
// (their own uploads + their teams' matches). Postgres is the analytics source of truth; each
// award ranks players by one metric. An optional team (roster steam_ids) and map narrow it.
class ComputeAwardsAction
{
    // Pistols + knife — the "eco" weapons (mirrors the frontend weapon registry's Pistols group).
    private const ECO_WEAPONS = ['glock18', 'usps', 'p2000', 'p250', 'fiveseven', 'tec9', 'cz75auto', 'dualberettas', 'deserteagle', 'r8revolver', 'knife'];

    private const MIN_KILLS_RATIO = 20;   // floor for ratio awards, so a 1/1 player can't top them

    private const TOP = 5;

    // The award keys whose leaderboard rows can be drilled into a kill list (see kills()).
    public const KILL_KEYS = ['legshots', 'first_blood', 'headshot', 'eco', 'clutches', 'one_trick'];

    // The SQL predicate behind the "leg-shot" award: the legs took at least as much damage as
    // every other zone. Shared by the leaderboard aggregate and the kill drill-down.
    private const LEGS_DOMINANT = "(hitgroups->>'legs')::int > 0
        AND (hitgroups->>'legs')::int >= COALESCE((hitgroups->>'head')::int, 0)
        AND (hitgroups->>'legs')::int >= COALESCE((hitgroups->>'chest')::int, 0)
        AND (hitgroups->>'legs')::int >= COALESCE((hitgroups->>'stomach')::int, 0)
        AND (hitgroups->>'legs')::int >= COALESCE((hitgroups->>'arms')::int, 0)";

    public function execute(User $owner, ?Team $team, ?string $map): array
    {
        $matchIds = GameMatch::visibleTo($owner)->pluck('id')->all();
        $ids = $team?->players()->pluck('steam_id')->all();
        $eco = "'".implode("','", self::ECO_WEAPONS)."'";
        $legs = self::LEGS_DOMINANT;

        $offence = $this->base($matchIds, $map, 'killer_steam_id', $ids)
            ->groupBy('killer_steam_id')
            ->selectRaw(<<<SQL
                killer_steam_id AS steam_id,
                count(*) AS kills,
                sum(CASE WHEN headshot THEN 1 ELSE 0 END) AS headshots,
                count(*) FILTER (WHERE $legs) AS legshots,
                count(*) FILTER (WHERE weapon IN ($eco)) AS eco,
                count(DISTINCT CASE WHEN clutch > 0 THEN match_id::text || '-' || round END) AS clutches
                SQL)
            ->get()->keyBy('steam_id');

        $defence = $this->base($matchIds, $map, 'victim_steam_id', $ids)
            ->groupBy('victim_steam_id')
            ->selectRaw('victim_steam_id AS steam_id, count(*) FILTER (WHERE opening) AS first_blood')
            ->get()->keyBy('steam_id');

        $byWeapon = $this->base($matchIds, $map, 'killer_steam_id', $ids)
            ->where('weapon', '<>', '')
            ->groupBy('killer_steam_id', 'weapon')
            ->selectRaw('killer_steam_id AS steam_id, weapon, count(*) AS c')
            ->get()->groupBy('steam_id');

        $names = $this->names($matchIds, $offence->keys()->merge($defence->keys())->unique()->all());
        $nameOf = fn ($id) => $names[$id] ?? $id;

        // one-trick: each player's biggest single-weapon share of their kills.
        $oneTrick = $byWeapon->map(function (Collection $rows) {
            $total = $rows->sum('c');
            $top = $rows->sortByDesc('c')->first();

            return ['total' => $total, 'weapon' => $top->weapon, 'share' => $total ? $top->c / $total : 0];
        });

        $awards = [
            $this->countAward('legshots', '🦵', 'Leg-shot leader', 'Kills where the legs took the most damage', $offence, 'legshots', $nameOf),
            $this->countAward('first_blood', '💀', 'First blood', 'Died first in the round the most', $defence, 'first_blood', $nameOf),
            $this->ratioAward($offence, $nameOf),
            $this->countAward('eco', '🔫', 'Eco bully', 'Most kills with a pistol or knife', $offence, 'eco', $nameOf),
            $this->countAward('clutches', '🏆', 'Clutch god', 'Most 1vN rounds won', $offence, 'clutches', $nameOf),
            $this->oneTrickAward($oneTrick, $nameOf),
        ];

        // Drop awards nobody qualified for (e.g. no clutches or leg kills in scope yet).
        return ['awards' => array_values(array_filter($awards, fn ($a) => count($a['leaders']) > 0))];
    }

    // The kills behind one award for one player, in the same scope — each carries its own
    // match/demo/tick so the dialog's "watch in game" jump works. Offence awards key on the
    // killer; first_blood keys on the victim (the round's first death).
    public function kills(User $owner, string $key, string $steamId, ?string $map): array
    {
        if (! in_array($key, self::KILL_KEYS, true)) {
            return ['kills' => []];
        }

        $matchIds = GameMatch::visibleTo($owner)->pluck('id')->all();

        $q = KillEvent::query()
            ->join('matches', 'matches.id', '=', 'kill_events.match_id')
            ->whereIn('kill_events.match_id', $matchIds);
        if ($map) {
            $q->where('kill_events.map', $map);
        }

        match ($key) {
            'legshots' => $q->where('kill_events.killer_steam_id', $steamId)->whereRaw(self::LEGS_DOMINANT),
            'eco' => $q->where('kill_events.killer_steam_id', $steamId)->whereIn('kill_events.weapon', self::ECO_WEAPONS),
            'clutches' => $q->where('kill_events.killer_steam_id', $steamId)->where('kill_events.clutch', '>', 0),
            'headshot' => $q->where('kill_events.killer_steam_id', $steamId)->where('kill_events.headshot', true),
            'one_trick' => $q->where('kill_events.killer_steam_id', $steamId)->where('kill_events.weapon', (string) $this->topWeapon($matchIds, $steamId, $map)),
            'first_blood' => $q->where('kill_events.victim_steam_id', $steamId)->where('kill_events.opening', true),
        };

        $kills = $q->orderBy('kill_events.match_id')->orderBy('kill_events.tick')
            ->get([
                'kill_events.match_id', 'matches.map_name', 'matches.original_filename', 'matches.tick_rate',
                'kill_events.round', 'kill_events.killer_name', 'kill_events.victim_name', 'kill_events.weapon',
                'kill_events.side', 'kill_events.headshot', 'kill_events.tick', 'kill_events.hitgroups',
            ])
            ->map(fn ($k) => [
                'match_id' => (int) $k->match_id,
                'map' => $k->map_name,
                'demo' => $k->original_filename,
                'tick_rate' => (float) $k->tick_rate,
                'round' => $k->round,
                'killer_name' => $k->killer_name,
                'victim_name' => $k->victim_name,
                'weapon' => $k->weapon,
                'side' => $k->side,
                'headshot' => $k->headshot,
                'tick' => $k->tick,
                'hitgroups' => $k->hitgroups,
            ])->all();

        return ['kills' => $kills];
    }

    private function topWeapon(array $matchIds, string $steamId, ?string $map): ?string
    {
        $q = DB::table('kill_events')
            ->whereIn('match_id', $matchIds)
            ->where('killer_steam_id', $steamId)
            ->where('weapon', '<>', '');
        if ($map) {
            $q->where('map', $map);
        }

        return $q->groupBy('weapon')->orderByRaw('count(*) DESC')->value('weapon');
    }

    private function base(array $matchIds, ?string $map, string $idColumn, ?array $ids)
    {
        $q = DB::table('kill_events')->whereIn('match_id', $matchIds);
        if ($map) {
            $q->where('map', $map);
        }
        if ($ids !== null) {
            $q->whereIn($idColumn, $ids ?: ['__none__']);
        }

        return $q;
    }

    private function names(array $matchIds, array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return DB::table('match_player_stats')
            ->join('matches', 'matches.id', '=', 'match_player_stats.match_id')
            ->whereIn('match_player_stats.match_id', $matchIds)
            ->whereIn('match_player_stats.steam_id', $ids)
            ->groupBy('match_player_stats.steam_id')
            ->selectRaw('match_player_stats.steam_id, (array_agg(match_player_stats.name ORDER BY matches.created_at DESC))[1] AS name')
            ->pluck('name', 'steam_id');
    }

    private function countAward(string $key, string $emoji, string $label, string $hint, Collection $rows, string $field, callable $nameOf): array
    {
        $leaders = $rows
            ->map(fn ($r, $id) => ['id' => $id, 'v' => (int) $r->$field])
            ->filter(fn ($x) => $x['v'] > 0)
            ->sortByDesc('v')->take(self::TOP)
            ->map(fn ($x) => ['steam_id' => (string) $x['id'], 'name' => $nameOf($x['id']), 'value' => (string) $x['v'], 'sub' => null])
            ->values()->all();

        return compact('key', 'emoji', 'label', 'hint', 'leaders');
    }

    private function ratioAward(Collection $offence, callable $nameOf): array
    {
        $leaders = $offence
            ->filter(fn ($r) => (int) $r->kills >= self::MIN_KILLS_RATIO)
            ->map(fn ($r, $id) => ['id' => $id, 'v' => (int) $r->headshots / (int) $r->kills, 'kills' => (int) $r->kills])
            ->filter(fn ($x) => $x['v'] > 0)
            ->sortByDesc('v')->take(self::TOP)
            ->map(fn ($x) => ['steam_id' => (string) $x['id'], 'name' => $nameOf($x['id']), 'value' => round($x['v'] * 100).'%', 'sub' => $x['kills'].' kills'])
            ->values()->all();

        return [
            'key' => 'headshot', 'emoji' => '🎯', 'label' => 'Headshot machine',
            'hint' => 'Highest headshot rate (min '.self::MIN_KILLS_RATIO.' kills)', 'leaders' => $leaders,
        ];
    }

    private function oneTrickAward(Collection $oneTrick, callable $nameOf): array
    {
        $leaders = $oneTrick
            ->filter(fn ($x) => $x['total'] >= self::MIN_KILLS_RATIO)
            ->map(fn ($x, $id) => ['id' => $id, 'v' => $x['share'], 'weapon' => $x['weapon']])
            ->sortByDesc('v')->take(self::TOP)
            ->map(fn ($x) => ['steam_id' => (string) $x['id'], 'name' => $nameOf($x['id']), 'value' => round($x['v'] * 100).'%', 'sub' => $x['weapon']])
            ->values()->all();

        return [
            'key' => 'one_trick', 'emoji' => '🎼', 'label' => 'One-trick',
            'hint' => 'Highest share of kills from a single weapon (min '.self::MIN_KILLS_RATIO.' kills)', 'leaders' => $leaders,
        ];
    }
}
