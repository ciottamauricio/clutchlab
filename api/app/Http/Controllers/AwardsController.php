<?php

namespace App\Http\Controllers;

use App\Actions\ComputeAwardsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AwardsController extends Controller
{
    // Superlative leaderboards across the caller's matches, optionally narrowed to one of the
    // caller's teams (roster) and/or a map. See api/docs/domains/awards.md.
    public function index(Request $request, ComputeAwardsAction $action): JsonResponse
    {
        $team = $request->filled('team_id')
            ? $request->user()->teams()->whereKey((int) $request->query('team_id'))->first()
            : null;

        $map = $request->filled('map') ? (string) $request->query('map') : null;

        return response()->json(['data' => $action->execute($request->user(), $team, $map)]);
    }

    // The kills behind one award for one player (drill-down from a leaderboard row).
    public function kills(Request $request, ComputeAwardsAction $action): JsonResponse
    {
        $key = (string) $request->query('key');
        $steamId = (string) $request->query('steam_id');
        if ($steamId === '' || ! in_array($key, ComputeAwardsAction::KILL_KEYS, true)) {
            return response()->json(['data' => ['kills' => []]]);
        }

        $map = $request->filled('map') ? (string) $request->query('map') : null;

        return response()->json(['data' => $action->kills($request->user(), $key, $steamId, $map)]);
    }
}
