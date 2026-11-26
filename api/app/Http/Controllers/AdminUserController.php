<?php

namespace App\Http\Controllers;

use App\Actions\DeleteUserAction;
use App\Actions\UpdateUserAction;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\PlayerStats\PlayerEntryResource;
use App\Models\User;
use App\PlayerStats\Application\PlayerList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        return AdminUserResource::collection(
            User::with('teams')->orderBy('name')->get()
        )->response();
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): AdminUserResource
    {
        $action->execute($user, $request->validated());

        return new AdminUserResource($user->load('teams'));
    }

    public function destroy(Request $request, User $user, DeleteUserAction $action): JsonResponse
    {
        $action->execute($request->user(), $user);

        return response()->json(status: 204);
    }

    // The pick list for linking an account to its demo identity: every distinct player seen
    // across all matches, by SteamID64 with the most-recent demo name. Admin-only, so global
    // (not visibility-scoped) — the admin links any account to any known player.
    public function players(Request $request, PlayerList $players): JsonResponse
    {
        return response()->json(['data' => ['players' => PlayerEntryResource::collection($players->all())->resolve($request)]]);
    }
}
