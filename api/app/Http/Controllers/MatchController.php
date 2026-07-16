<?php

namespace App\Http\Controllers;

use App\Actions\DeleteMatchAction;
use App\Actions\Matches\ComputeViewerResultsAction;
use App\Actions\ReparseMatchAction;
use App\Actions\UpdateMatchTeamAction;
use App\Actions\UploadDemoAction;
use App\Contracts\DemoStorage;
use App\Http\Requests\ListMatchesRequest;
use App\Http\Requests\UpdateMatchRequest;
use App\Http\Requests\UploadDemoRequest;
use App\Http\Resources\MatchResource;
use App\Models\GameMatch;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatchController extends Controller
{
    public function index(ListMatchesRequest $request, ComputeViewerResultsAction $viewerResults): JsonResponse
    {
        $matches = GameMatch::with(['team', 'owner'])
            ->visibleTo($request->user())
            ->when($request->validated('player'), fn ($query, $player) => $query
                ->whereHas('playerStats', fn ($stats) => $stats->whereRaw(
                    // LOWER + LIKE instead of ILIKE so the filter also works on the
                    // sqlite test database; %/_ are escaped (with an explicit ESCAPE —
                    // sqlite has no default escape character) so they match literally.
                    "LOWER(name) LIKE ? ESCAPE '\\'",
                    ['%'.addcslashes(mb_strtolower($player), '%_\\').'%'],
                )))
            // A month filter means "played in that calendar month" — matches without a
            // played_at (unrecognized filename) only show in the unfiltered view.
            ->when($request->validated('month'), function ($query, $month) {
                $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'UTC');
                $query->where('played_at', '>=', $start)
                    ->where('played_at', '<', $start->addMonth());
            })
            ->when($request->validated('day'), function ($query, $day) {
                $start = CarbonImmutable::createFromFormat('!Y-m-d', $day, 'UTC');
                $query->where('played_at', '>=', $start)
                    ->where('played_at', '<', $start->addDay());
            })
            // Most recently *played* first. played_at can be null (unrecognized filename
            // or not yet parsed); those sort to the bottom, then by upload time. The
            // `played_at is null` expression makes NULL-placement portable — Postgres sorts
            // NULLs last on DESC, sqlite (test DB) sorts them first, so we order it ourselves.
            ->orderByRaw('played_at is null')
            ->orderByDesc('played_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Mark each match with the viewer's result (win/loss/draw) where the game was
        // theirs — their own seat, or a 4+ stack from one of their teams.
        $viewerResults->execute(collect($matches->items()), $request->user());

        return MatchResource::collection($matches)->response();
    }

    public function store(UploadDemoRequest $request, UploadDemoAction $action): JsonResponse
    {
        $match = $action->execute(
            $request->file('demo'),
            $request->user(),
            $request->integer('team_id') ?: null,
            $request->contentHash(),
        );

        return (new MatchResource($match))->response()->setStatusCode(201);
    }

    // Move a match between private and a team (or between teams). Authorized like any other
    // write on the match; the request also checks the caller may upload to the target team.
    public function update(UpdateMatchRequest $request, GameMatch $match, UpdateMatchTeamAction $action): MatchResource
    {
        $this->authorize('delete', $match);

        $action->execute($match, $request->validated('team_id') ?: null);

        return new MatchResource($match->fresh(['team', 'owner']));
    }

    public function show(Request $request, GameMatch $match): MatchResource
    {
        $this->authorize('view', $match);

        $match->load(['team', 'owner', 'playerStats' => fn ($q) => $q->orderByDesc('kills')]);

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

    // Side-by-side comparison of the match's two teams (CT vs T): scoreboard totals plus the
    // opening-duel and clutch stats that only kill_events carries. Teams are the stable
    // whole-match sides (killer_team / team_side), so the numbers are steady across the half.
    public function teamStats(Request $request, GameMatch $match): JsonResponse
    {
        $this->authorize('view', $match);

        $board = $match->playerStats()
            ->selectRaw('team_side, sum(kills) kills, sum(deaths) deaths, sum(assists) assists, sum(headshots) headshots')
            ->groupBy('team_side')->get()->keyBy('team_side');

        $opening = $match->killEvents()->where('opening', true)
            ->selectRaw('killer_team, count(*) c')->groupBy('killer_team')->pluck('c', 'killer_team');

        $clutches = $match->killEvents()->where('clutch', '>', 0)
            ->selectRaw('killer_team, count(DISTINCT round) c')->groupBy('killer_team')->pluck('c', 'killer_team');

        // Each opening kill is one team's opener and the other team's opening death, so a
        // team's opening deaths are the opposing team's opening kills.
        $team = function (string $side, string $other, ?string $name, ?int $score) use ($board, $opening, $clutches) {
            $b = $board[$side] ?? null;
            $kills = (int) ($b->kills ?? 0);
            $headshots = (int) ($b->headshots ?? 0);

            return [
                'side' => $side,
                'name' => $name ?: ($side === 'CT' ? 'Counter-Terrorists' : 'Terrorists'),
                'score' => (int) $score,
                'kills' => $kills,
                'deaths' => (int) ($b->deaths ?? 0),
                'assists' => (int) ($b->assists ?? 0),
                'hs_pct' => $kills > 0 ? (int) round($headshots / $kills * 100) : 0,
                'opening_kills' => (int) ($opening[$side] ?? 0),
                'opening_deaths' => (int) ($opening[$other] ?? 0),
                'clutches' => (int) ($clutches[$side] ?? 0),
            ];
        };

        return response()->json(['data' => [
            'ct' => $team('CT', 'T', $match->ct_name, $match->score_ct),
            't' => $team('T', 'CT', $match->t_name, $match->score_t),
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
