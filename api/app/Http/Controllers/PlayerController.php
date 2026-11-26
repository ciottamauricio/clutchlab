<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerStats\PlayerClutchResource;
use App\Http\Resources\PlayerStats\PlayerEntryResource;
use App\PlayerStats\Application\PlayerClutches;
use App\PlayerStats\Application\PlayerList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    // The catalog of every distinct player seen across the caller's visible matches (their own
    // uploads + their teams'), the pick list for building a team roster. See teams-auth.md.
    public function index(Request $request, PlayerList $players): JsonResponse
    {
        return response()->json(['data' => ['players' => PlayerEntryResource::collection($players->seenBy($request->user()->id))->resolve($request)]]);
    }

    // Every clutch this player won across the caller's visible matches, each carrying its own
    // match/demo/tick so the "watch in game" jump works.
    public function clutches(Request $request, string $steamId, PlayerClutches $clutches): JsonResponse
    {
        return response()->json(['data' => ['clutches' => PlayerClutchResource::collection($clutches->execute($request->user()->id, $steamId))->resolve($request)]]);
    }
}
