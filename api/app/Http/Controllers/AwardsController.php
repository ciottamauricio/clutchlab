<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerStats\AwardKillResource;
use App\Http\Resources\PlayerStats\AwardResource;
use App\PlayerStats\Application\AwardKills;
use App\PlayerStats\Application\Awards;
use App\PlayerStats\Domain\AwardKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AwardsController extends Controller
{
    // Superlative leaderboards across the caller's matches, optionally narrowed to one of the
    // caller's teams (roster) and/or a map. A team the caller isn't in counts as no team. See
    // api/docs/domains/awards.md.
    public function index(Request $request, Awards $awards): JsonResponse
    {
        $team = $request->filled('team_id')
            ? $request->user()->teams()->whereKey((int) $request->query('team_id'))->first()
            : null;

        $map = $request->filled('map') ? (string) $request->query('map') : null;

        $board = $awards->execute($request->user()->id, $team?->id, $map);

        return response()->json(['data' => ['awards' => array_map(fn ($award) => (new AwardResource($award, $board))->resolve($request), $board->awards)]]);
    }

    // The kills behind one award for one player (drill-down from a leaderboard row).
    public function kills(Request $request, AwardKills $kills): JsonResponse
    {
        $key = AwardKey::tryFrom((string) $request->query('key'));
        $steamId = (string) $request->query('steam_id');
        if ($steamId === '' || $key === null) {
            return response()->json(['data' => ['kills' => []]]);
        }

        $map = $request->filled('map') ? (string) $request->query('map') : null;

        return response()->json(['data' => ['kills' => AwardKillResource::collection($kills->execute($request->user()->id, $key, $steamId, $map))->resolve($request)]]);
    }
}
