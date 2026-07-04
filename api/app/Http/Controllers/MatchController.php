<?php

namespace App\Http\Controllers;

use App\Actions\DeleteMatchAction;
use App\Actions\ReparseMatchAction;
use App\Actions\UploadDemoAction;
use App\Contracts\DemoStorage;
use App\Http\Requests\UploadDemoRequest;
use App\Http\Resources\MatchResource;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->get(['round', 'killer_name', 'killer_steam_id', 'victim_name', 'weapon', 'side', 'killer_team', 'clutch', 'headshot', 'tick', 'victim_x', 'victim_y', 'hitgroups'])
            ->map(fn ($k) => [
                'round' => $k->round,
                'killer_name' => $k->killer_name,
                'killer_steam_id' => $k->killer_steam_id,
                'victim_name' => $k->victim_name,
                'weapon' => $k->weapon,
                'side' => $k->side,
                'team' => $k->killer_team,
                'clutch' => $k->clutch,
                'headshot' => $k->headshot,
                'tick' => $k->tick,
                'hitgroups' => $k->hitgroups,
                'x' => $k->victim_x,
                'y' => $k->victim_y,
            ]);

        return response()->json(['data' => [
            'map' => $match->map_name,
            'tick_rate' => $match->tick_rate,
            'demo' => $match->original_filename,
            'points' => $points,
        ]]);
    }

    public function demo(Request $request, GameMatch $match, DemoStorage $storage): StreamedResponse
    {
        $this->authorize('view', $match);

        return $storage->download($match->demo_key, $match->original_filename);
    }

    // Clutches in the match: one per round where a player was last alive on their team,
    // grouped as { round, size, killer, kills[] } with the clutcher's kills in kill order.
    public function clutches(Request $request, GameMatch $match): JsonResponse
    {
        $this->authorize('view', $match);

        $clutches = $match->killEvents()
            ->where('clutch', '>', 0)
            ->orderBy('round')
            ->orderBy('tick')
            ->get(['round', 'clutch', 'killer_name', 'killer_steam_id', 'victim_name', 'weapon', 'side', 'headshot', 'tick', 'hitgroups'])
            ->groupBy('round')
            ->map(fn ($kills, $round) => [
                'round' => (int) $round,
                'size' => $kills->first()->clutch,
                'killer_name' => $kills->first()->killer_name,
                'killer_steam_id' => $kills->first()->killer_steam_id,
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

        return response()->json(['data' => [
            'tick_rate' => $match->tick_rate,
            'demo' => $match->original_filename,
            'clutches' => $clutches,
        ]]);
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
