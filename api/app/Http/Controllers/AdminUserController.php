<?php

namespace App\Http\Controllers;

use App\Actions\UpdateUserAction;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\MatchPlayerStat;
use App\Models\User;
use Illuminate\Http\JsonResponse;

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

    // The pick list for linking an account to its demo identity: every distinct player seen
    // across all matches, by SteamID64 with the most-recent demo name. Admin-only, so global
    // (not visibility-scoped) — the admin links any account to any known player.
    public function players(): JsonResponse
    {
        $players = MatchPlayerStat::query()
            ->join('matches', 'matches.id', '=', 'match_player_stats.match_id')
            ->groupBy('match_player_stats.steam_id')
            ->selectRaw(<<<'SQL'
                match_player_stats.steam_id,
                (array_agg(match_player_stats.name ORDER BY matches.created_at DESC))[1] AS name,
                count(DISTINCT match_player_stats.match_id) AS match_count
                SQL)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => ['players' => $players]]);
    }
}
