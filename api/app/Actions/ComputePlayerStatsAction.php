<?php

namespace App\Actions;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// One player's aggregate stats (their linked SteamID) across every match the user can see.
// Same shape/rules as the team stat board, for a single id. Null when no SteamID is linked.
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
            return $this->zeroed();
        }

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

    private function zeroed(): array
    {
        return [
            'games' => 0, 'kills' => 0, 'deaths' => 0, 'kd' => 0.0,
            'hs_pct' => 0, 'entry_kills' => 0, 'first_deaths' => 0, 'clutches' => 0,
        ];
    }
}
