<?php

namespace App\Http\Controllers;

use App\Models\KillEvent;
use App\Models\MatchPlayerStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    /**
     * The catalog of every distinct player seen across the caller's matches — the pick
     * list for building a team roster. Derived from match_player_stats (no separate
     * players table); the display name is the most-recently-seen one, since demo names
     * drift (clan tags, emojis) but the SteamID64 is stable.
     */
    public function index(Request $request): JsonResponse
    {
        $players = MatchPlayerStat::query()
            ->join('matches', 'matches.id', '=', 'match_player_stats.match_id')
            ->where('matches.user_id', $request->user()->id)
            ->groupBy('match_player_stats.steam_id')
            ->selectRaw(<<<'SQL'
                match_player_stats.steam_id,
                (array_agg(match_player_stats.name ORDER BY matches.created_at DESC))[1] AS name,
                count(DISTINCT match_player_stats.match_id) AS match_count
                SQL)
            ->orderByDesc('match_count')
            ->get();

        return response()->json(['data' => ['players' => $players]]);
    }

    /**
     * Every clutch this player won across the caller's own matches, grouped by round like
     * MatchController@clutches but spanning matches — each carries its own match/demo/tick so
     * the "watch in game" jump works. Scoped to the caller via kill_events.owner_id.
     */
    public function clutches(Request $request, string $steamId): JsonResponse
    {
        $clutches = KillEvent::query()
            ->join('matches', 'matches.id', '=', 'kill_events.match_id')
            ->where('kill_events.owner_id', $request->user()->id)
            ->where('kill_events.killer_steam_id', $steamId)
            ->where('kill_events.clutch', '>', 0)
            ->orderBy('kill_events.match_id')
            ->orderBy('kill_events.round')
            ->orderBy('kill_events.tick')
            ->get([
                'kill_events.match_id',
                'matches.map_name',
                'matches.original_filename',
                'matches.tick_rate',
                'kill_events.round',
                'kill_events.clutch',
                'kill_events.killer_name',
                'kill_events.victim_name',
                'kill_events.weapon',
                'kill_events.side',
                'kill_events.headshot',
                'kill_events.tick',
                'kill_events.hitgroups',
            ])
            ->groupBy(fn ($k) => $k->match_id.'-'.$k->round)
            ->map(fn ($kills) => [
                'match_id' => (int) $kills->first()->match_id,
                'map' => $kills->first()->map_name,
                'demo' => $kills->first()->original_filename,
                'tick_rate' => (float) $kills->first()->tick_rate,
                'round' => (int) $kills->first()->round,
                'size' => (int) $kills->first()->clutch,
                'killer_name' => $kills->first()->killer_name,
                'kills' => $kills->map(fn ($k) => [
                    'victim_name' => $k->victim_name,
                    'weapon' => $k->weapon,
                    'side' => $k->side,
                    'headshot' => $k->headshot,
                    'tick' => $k->tick,
                    'hitgroups' => $k->hitgroups,
                ])->values(),
            ])
            ->values();

        return response()->json(['data' => ['clutches' => $clutches]]);
    }
}
