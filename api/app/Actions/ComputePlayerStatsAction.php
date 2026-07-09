<?php

namespace App\Actions;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// One player's profile analytics (their linked SteamID) across every match the user can see:
// the aggregate stat line (same rules as the team board), a recent-form series (K/D + W/L per
// match), and their top weapons. Null when no SteamID is linked.
class ComputePlayerStatsAction
{
    public function execute(User $user): ?array
    {
        if (! $user->steam_id) {
            return null;
        }

        $id = $user->steam_id;
        $matchIds = GameMatch::visibleTo($user)->pluck('id')->all();

        if (empty($matchIds)) {
            return ['stats' => $this->zeroed(), 'recent' => [], 'weapons' => [], 'clutches' => []];
        }

        return [
            'stats' => $this->aggregate($id, $matchIds),
            'recent' => $this->recent($id, $matchIds),
            'weapons' => $this->weapons($id, $matchIds),
            'clutches' => $this->clutches($id, $matchIds),
        ];
    }

    private function aggregate(string $id, array $matchIds): array
    {
        $offence = DB::table('kill_events')
            ->whereIn('match_id', $matchIds)
            ->where('killer_steam_id', $id)
            ->selectRaw(<<<'SQL'
                count(*) AS kills,
                sum(CASE WHEN headshot THEN 1 ELSE 0 END) AS headshots,
                sum(CASE WHEN opening THEN 1 ELSE 0 END) AS entry_kills,
                count(DISTINCT CASE WHEN clutch > 0 THEN match_id::text || '-' || round END) AS clutches
                SQL)
            ->first();

        $defence = DB::table('kill_events')
            ->whereIn('match_id', $matchIds)
            ->where('victim_steam_id', $id)
            ->selectRaw(<<<'SQL'
                count(*) AS deaths,
                sum(CASE WHEN opening THEN 1 ELSE 0 END) AS first_deaths
                SQL)
            ->first();

        $games = DB::table('match_player_stats')
            ->whereIn('match_id', $matchIds)
            ->where('steam_id', $id)
            ->distinct('match_id')
            ->count('match_id');

        $kills = (int) ($offence->kills ?? 0);
        $deaths = (int) ($defence->deaths ?? 0);
        $headshots = (int) ($offence->headshots ?? 0);

        return [
            'games' => $games,
            'kills' => $kills,
            'deaths' => $deaths,
            'kd' => $deaths > 0 ? round($kills / $deaths, 2) : (float) $kills,
            'hs_pct' => $kills > 0 ? (int) round($headshots / $kills * 100) : 0,
            'entry_kills' => (int) ($offence->entry_kills ?? 0),
            'first_deaths' => (int) ($defence->first_deaths ?? 0),
            'clutches' => (int) ($offence->clutches ?? 0),
        ];
    }

    // The last 10 games, chronological, each with K/D and the match outcome (won/lost/tie) for
    // this player's side — the recent-form chart.
    private function recent(string $id, array $matchIds): array
    {
        return DB::table('match_player_stats as mps')
            ->join('matches as m', 'm.id', '=', 'mps.match_id')
            ->whereIn('mps.match_id', $matchIds)
            ->where('mps.steam_id', $id)
            ->orderByRaw('coalesce(m.played_at, m.created_at) desc')
            ->limit(10)
            ->get(['m.id', 'm.map_name', 'm.played_at', 'm.duration_seconds', 'm.score_ct', 'm.score_t', 'mps.kills', 'mps.deaths', 'mps.team_side'])
            ->map(fn ($r) => [
                'match_id' => (int) $r->id,
                'map' => $r->map_name,
                'played_at' => $r->played_at,
                'duration_seconds' => $r->duration_seconds !== null ? (int) $r->duration_seconds : null,
                'kills' => (int) $r->kills,
                'deaths' => (int) $r->deaths,
                'kd' => $r->deaths > 0 ? round($r->kills / $r->deaths, 2) : (float) $r->kills,
                'won' => $this->outcome($r),
            ])
            ->reverse()
            ->values()
            ->all();
    }

    // true = the player's side won, false = lost, null = tie or unknown score/side.
    private function outcome(object $r): ?bool
    {
        if ($r->score_ct === null || $r->score_t === null || ! $r->team_side || $r->score_ct === $r->score_t) {
            return null;
        }

        return $r->team_side === 'CT' ? $r->score_ct > $r->score_t : $r->score_t > $r->score_ct;
    }

    // Top 6 weapons by kills for this player.
    private function weapons(string $id, array $matchIds): array
    {
        return DB::table('kill_events')
            ->whereIn('match_id', $matchIds)
            ->where('killer_steam_id', $id)
            ->where('weapon', '<>', '')
            ->groupBy('weapon')
            ->selectRaw('weapon, count(*) AS kills')
            ->orderByDesc('kills')
            ->limit(6)
            ->get()
            ->map(fn ($w) => ['weapon' => $w->weapon, 'kills' => (int) $w->kills])
            ->all();
    }

    // The player's most recent clutches (rounds won while last alive), newest first, each with
    // the match it happened in and the kills that won it — so a clutch expands to its kills
    // (hitgroups + demo jump) right on the profile, like the search results do.
    private function clutches(string $id, array $matchIds): array
    {
        // The 8 most recent (match, round) clutches for this player, newest first.
        $recent = DB::table('kill_events as ke')
            ->join('matches as m', 'm.id', '=', 'ke.match_id')
            ->whereIn('ke.match_id', $matchIds)
            ->where('ke.killer_steam_id', $id)
            ->where('ke.clutch', '>', 0)
            ->groupBy('ke.match_id', 'ke.round', 'm.map_name', 'm.played_at', 'm.created_at', 'm.tick_rate', 'm.original_filename')
            ->orderByRaw('coalesce(m.played_at, m.created_at) desc, ke.round desc')
            ->limit(8)
            ->get([
                'ke.match_id',
                'ke.round',
                'm.map_name',
                'm.played_at',
                'm.tick_rate',
                'm.original_filename as demo',
                DB::raw('max(ke.clutch) AS size'),
            ]);

        if ($recent->isEmpty()) {
            return [];
        }

        // The kills behind those clutches: fetch this player's clutch kills from the recent
        // matches in one pass, grouped by (match, round) for lookup below.
        $kills = DB::table('kill_events')
            ->whereIn('match_id', $recent->pluck('match_id')->unique()->all())
            ->where('killer_steam_id', $id)
            ->where('clutch', '>', 0)
            ->orderBy('tick')
            ->get(['match_id', 'round', 'victim_name', 'weapon', 'side', 'headshot', 'tick', 'hitgroups'])
            ->groupBy(fn ($k) => $k->match_id.'-'.$k->round);

        return $recent->map(fn ($c) => [
            'match_id' => (int) $c->match_id,
            'round' => (int) $c->round,
            'map' => $c->map_name,
            'played_at' => $c->played_at,
            'tick_rate' => $c->tick_rate,
            'demo' => $c->demo,
            'size' => (int) $c->size,
            'kills' => ($kills[$c->match_id.'-'.$c->round] ?? collect())->map(fn ($k) => [
                'victim_name' => $k->victim_name,
                'weapon' => $k->weapon,
                'side' => $k->side,
                'headshot' => $k->headshot,
                'tick' => $k->tick,
                'hitgroups' => $k->hitgroups,
            ])->values()->all(),
        ])->all();
    }

    private function zeroed(): array
    {
        return [
            'games' => 0, 'kills' => 0, 'deaths' => 0, 'kd' => 0.0,
            'hs_pct' => 0, 'entry_kills' => 0, 'first_deaths' => 0, 'clutches' => 0,
        ];
    }
}
