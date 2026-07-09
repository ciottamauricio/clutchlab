<?php

namespace App\Http\Controllers;

use App\Actions\ChangePasswordAction;
use App\Actions\ComputePlayerStatsAction;
use App\Actions\UpdateProfileAction;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, UpdateProfileAction $action): UserResource
    {
        return new UserResource($action->execute($request->user(), $request->validated()));
    }

    public function changePassword(ChangePasswordRequest $request, ChangePasswordAction $action): JsonResponse
    {
        $action->execute($request->user(), $request->validated('password'), $request->user()->currentAccessToken());

        return response()->json(['data' => ['ok' => true]]);
    }

    // The caller's own aggregate stats, or null when their account has no linked SteamID.
    public function stats(Request $request, ComputePlayerStatsAction $action): JsonResponse
    {
        return response()->json(['data' => ['stats' => $action->execute($request->user())]]);
    }
}
