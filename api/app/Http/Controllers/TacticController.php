<?php

namespace App\Http\Controllers;

use App\Actions\CreateTacticAction;
use App\Actions\UpdateTacticTeamAction;
use App\Http\Requests\StoreTacticRequest;
use App\Http\Requests\UpdateTacticRequest;
use App\Http\Requests\UpdateTacticTeamRequest;
use App\Http\Resources\TacticResource;
use App\Models\Tactic;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TacticController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return TacticResource::collection(
            Tactic::visibleTo($request->user())->with(['team', 'owner'])->latest()->get()
        )->response();
    }

    public function store(StoreTacticRequest $request, CreateTacticAction $action): JsonResponse
    {
        // Creating into a team needs tactics.create on it; a private draft ($team null)
        // is always allowed. The request already verified membership of a non-null team.
        $teamId = $request->validated('team_id') ?: null;
        $team = $teamId ? Team::find($teamId) : null;
        $this->authorize('create', [Tactic::class, $team]);

        $tactic = $action->execute($request->user(), $request->validated('name'), $request->validated('map'), $teamId);

        return (new TacticResource($tactic->load(['team', 'owner'])))->response()->setStatusCode(201);
    }

    public function show(Tactic $tactic): TacticResource
    {
        $this->authorize('view', $tactic);

        return new TacticResource($tactic);
    }

    public function update(UpdateTacticRequest $request, Tactic $tactic): TacticResource
    {
        $this->authorize('update', $tactic);

        $tactic->update($request->validated());

        return new TacticResource($tactic);
    }

    // Move a tactic between private and a team. Sharing stays with the creator
    // (authorized like delete); the request checks membership of the target team.
    public function updateTeam(UpdateTacticTeamRequest $request, Tactic $tactic, UpdateTacticTeamAction $action): TacticResource
    {
        $this->authorize('share', $tactic);

        $action->execute($tactic, $request->validated('team_id') ?: null);

        return new TacticResource($tactic->fresh(['team', 'owner']));
    }

    public function destroy(Tactic $tactic): Response
    {
        $this->authorize('delete', $tactic);

        $tactic->delete();

        return response()->noContent();
    }
}
