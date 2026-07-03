<?php

namespace App\Http\Controllers;

use App\Actions\DeleteMatchAction;
use App\Actions\ReparseMatchAction;
use App\Actions\UploadDemoAction;
use App\Http\Requests\UploadDemoRequest;
use App\Http\Resources\MatchResource;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return MatchResource::collection(
            $request->user()->matches()->latest()->get()
        )->response();
    }

    public function store(UploadDemoRequest $request, UploadDemoAction $action): JsonResponse
    {
        $match = $action->execute($request->file('demo'), $request->user());

        return (new MatchResource($match))->response()->setStatusCode(201);
    }

    public function show(Request $request, GameMatch $match): MatchResource
    {
        $this->authorize('view', $match);

        $match->load(['playerStats' => fn ($q) => $q->orderByDesc('kills')]);

        return new MatchResource($match);
    }

    // All kill positions for the heatmap (api/docs/domains/heatmap.md). Only kills with
    // coordinates; a match is small enough to send at once and filter client-side.
    public function killPositions(Request $request, GameMatch $match): JsonResponse
    {
        $this->authorize('view', $match);

        $points = $match->killEvents()
            ->whereNotNull('victim_x')
            ->whereNotNull('victim_y')
            ->orderBy('round')
            ->get(['round', 'killer_name', 'victim_name', 'weapon', 'side', 'killer_team', 'headshot', 'victim_x', 'victim_y'])
            ->map(fn ($k) => [
                'round' => $k->round,
                'killer_name' => $k->killer_name,
                'victim_name' => $k->victim_name,
                'weapon' => $k->weapon,
                'side' => $k->side,
                'team' => $k->killer_team,
                'headshot' => $k->headshot,
                'x' => $k->victim_x,
                'y' => $k->victim_y,
            ]);

        return response()->json(['data' => ['map' => $match->map_name, 'points' => $points]]);
    }

    public function destroy(Request $request, GameMatch $match, DeleteMatchAction $action): JsonResponse
    {
        $this->authorize('delete', $match);

        $action->execute($match);

        return response()->json(status: 204);
    }

    // Re-enqueue the stored demo for parsing — backfills newer analysis (e.g. heatmap
    // positions) without a re-upload. Returns the match reset to `queued`.
    public function reparse(Request $request, GameMatch $match, ReparseMatchAction $action): MatchResource
    {
        $this->authorize('reparse', $match);

        $action->execute($match);

        return new MatchResource($match);
    }
}
