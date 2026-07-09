<?php

namespace App\Http\Controllers;

use App\Actions\AddTeamMemberAction;
use App\Actions\ComputeTeamStatsAction;
use App\Actions\CreateTeamAction;
use App\Http\Requests\AddTeamMemberRequest;
use App\Http\Requests\AddTeamPlayerRequest;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return TeamResource::collection($request->user()->teams)->response();
    }

    public function store(StoreTeamRequest $request, CreateTeamAction $action): JsonResponse
    {
        $team = $action->execute($request->user(), $request->validated('name'));

        return (new TeamResource($team->load('members')))->response()->setStatusCode(201);
    }

    public function show(Team $team): TeamResource
    {
        $this->authorize('view', $team);

        return new TeamResource($team->load(['members', 'players']));
    }

    public function addPlayer(AddTeamPlayerRequest $request, Team $team): TeamResource
    {
        $this->authorize('manageRoster', $team);

        // Idempotent: re-adding the same steam_id updates its nickname instead of erroring.
        $team->players()->updateOrCreate(
            ['steam_id' => $request->validated('steam_id')],
            ['nickname' => $request->validated('nickname')],
        );

        return new TeamResource($team->load(['members', 'players']));
    }

    public function removePlayer(Team $team, string $steamId): TeamResource
    {
        $this->authorize('manageRoster', $team);

        $team->players()->where('steam_id', $steamId)->delete();

        return new TeamResource($team->load(['members', 'players']));
    }

    public function stats(Request $request, Team $team, ComputeTeamStatsAction $action): JsonResponse
    {
        $this->authorize('view', $team);

        return response()->json(['data' => $action->execute(
            $team,
            $this->asDate($request->query('from')),
            $this->asDate($request->query('to')),
        )]);
    }

    // Accept a query-string date only in strict Y-m-d form; anything else is ignored (no filter).
    private function asDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    public function addMember(AddTeamMemberRequest $request, Team $team, AddTeamMemberAction $action): TeamResource
    {
        $this->authorize('manageMembers', $team);

        // Creating a new owner is an owner-only act, so a non-owner manager can't promote
        // (including themselves) to owner.
        if ($request->validated('role') === 'owner') {
            $this->ownerOnly($request->user(), $team);
        }

        $action->execute($team, $request->validated('email'), $request->validated('role'));

        return new TeamResource($team->load('members'));
    }

    public function removeMember(Request $request, Team $team, User $user): TeamResource
    {
        $this->authorize('manageMembers', $team);

        // Only an owner may remove another owner; a non-owner manager can manage everyone else.
        if ($team->roleOf($user) === 'owner') {
            $this->ownerOnly($request->user(), $team);
        }

        $team->members()->detach($user->id);

        return new TeamResource($team->load('members'));
    }

    // Guard an owner-only action: the actor must be an owner of the team (admins bypass every
    // check upstream and never reach this). 403 with a code, per the ownership convention.
    private function ownerOnly(User $actor, Team $team): void
    {
        if ($actor->isAdmin() || $team->roleOf($actor) === 'owner') {
            return;
        }

        abort(403, 'team.owner_only');
    }
}
