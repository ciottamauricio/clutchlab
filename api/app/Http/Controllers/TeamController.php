<?php

namespace App\Http\Controllers;

use App\Actions\AddTeamMemberAction;
use App\Actions\CreateTeamAction;
use App\Http\Requests\AddTeamMemberRequest;
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

        return new TeamResource($team->load('members'));
    }

    public function addMember(AddTeamMemberRequest $request, Team $team, AddTeamMemberAction $action): TeamResource
    {
        $this->authorize('manageMembers', $team);

        $action->execute($team, $request->validated('email'), $request->validated('role'));

        return new TeamResource($team->load('members'));
    }

    public function removeMember(Team $team, User $user): TeamResource
    {
        $this->authorize('manageMembers', $team);

        $team->members()->detach($user->id);

        return new TeamResource($team->load('members'));
    }
}
