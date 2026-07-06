<?php

namespace App\Actions;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Aggregates a team's roster stats from the canonical kill_events (Postgres is the source
// of truth for analytics; Meilisearch is only the text-search read model). Scoped to the
// caller's own matches via kill_events.owner_id, keyed on the stable SteamID64.
class ComputeTeamStatsAction
{
    public function execute(Team $team, User $owner): array
    {
        $roster = $team->players()->get();
        $ids = $roster->pluck('steam_id')->all();
        if (empty($ids)) {
            return ['players' => []];
        }

        $ownerId = $owner->id;

        // Offence: everything keyed on the killer. A won clutch is one round, not one kill,
        // so count distinct (match, round) pairs the player clutched in.
        $offence = DB::table('kill_events')
            ->where('owner_id', $ownerId)
            ->whereIn('killer_steam_id', $ids)
            ->groupBy('killer_steam_id')
            ->selectRaw(<<<'SQL'
                killer_steam_id AS steam_id,
                count(*) AS kills,
                sum(CASE WHEN headshot THEN 1 ELSE 0 END) AS headshots,
                sum(CASE WHEN opening THEN 1 ELSE 0 END) AS entry_kills,
                count(DISTINCT CASE WHEN clutch > 0 THEN match_id::text || '-' || round END) AS clutches
                SQL)
            ->get()->keyBy('steam_id');

        // Defence: keyed on the victim. An opening kill's victim is the round's first death.
        $defence = DB::table('kill_events')
            ->where('owner_id', $ownerId)
            ->whereIn('victim_steam_id', $ids)
            ->groupBy('victim_steam_id')
            ->selectRaw(<<<'SQL'
                victim_steam_id AS steam_id,
                count(*) AS deaths,
                sum(CASE WHEN opening THEN 1 ELSE 0 END) AS first_deaths
                SQL)
            ->get()->keyBy('steam_id');

        // Most-recently-seen demo name and how many of the caller's matches each player
        // appears in, so the board is self-contained.
        $meta = DB::table('match_player_stats')
            ->join('matches', 'matches.id', '=', 'match_player_stats.match_id')
            ->where('matches.user_id', $ownerId)
            ->whereIn('match_player_stats.steam_id', $ids)
            ->groupBy('match_player_stats.steam_id')
            ->selectRaw(<<<'SQL'
                match_player_stats.steam_id,
                (array_agg(match_player_stats.name ORDER BY matches.created_at DESC))[1] AS name,
                count(DISTINCT match_player_stats.match_id) AS games
                SQL)
            ->get()->keyBy('steam_id');

        $players = $roster->map(function ($p) use ($offence, $defence, $meta) {
            $o = $offence->get($p->steam_id);
            $d = $defence->get($p->steam_id);
            $m = $meta->get($p->steam_id);
            $kills = (int) ($o->kills ?? 0);
            $deaths = (int) ($d->deaths ?? 0);
            $headshots = (int) ($o->headshots ?? 0);

            return [
                'steam_id' => $p->steam_id,
                'name' => $p->nickname ?: ($m->name ?? $p->steam_id),
                'games' => (int) ($m->games ?? 0),
                'kills' => $kills,
                'deaths' => $deaths,
                'kd' => $deaths > 0 ? round($kills / $deaths, 2) : (float) $kills,
                'hs_pct' => $kills > 0 ? (int) round($headshots / $kills * 100) : 0,
                'entry_kills' => (int) ($o->entry_kills ?? 0),
                'first_deaths' => (int) ($d->first_deaths ?? 0),
                'clutches' => (int) ($o->clutches ?? 0),
            ];
        })->sortByDesc('kills')->values()->all();

        return ['players' => $players];
    }
}
