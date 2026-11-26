<?php

namespace App\Http\Controllers;

use App\Actions\ChangePasswordAction;
use App\Actions\UpdateProfileAction;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\PlayerStats\ProfileResource;
use App\Http\Resources\UserResource;
use App\PlayerStats\Application\PlayerProfile;
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

    // The caller's own profile analytics: aggregate stats, recent form, top weapons, and recent
    // clutches. Stats is null when the account has no linked SteamID.
    public function stats(Request $request, PlayerProfile $profile): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => (new ProfileResource($profile->execute($user->id, $user->steam_id)))->resolve($request)]);
    }
}
