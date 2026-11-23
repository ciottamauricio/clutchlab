<?php

namespace App\Http\Controllers;

use App\Actions\Faceit\GetFaceitMatchAction;
use App\Actions\Faceit\ListFaceitMatchesAction;
use App\Contracts\FaceitApi;
use App\Faceit\FaceitUnavailable;
use App\Http\Requests\ListFaceitMatchesRequest;
use App\Http\Resources\FaceitMatchResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FaceitController extends Controller
{
    public function index(ListFaceitMatchesRequest $request, FaceitApi $faceit, ListFaceitMatchesAction $action): JsonResponse
    {
        // No key on this instance: the panel says FACEIT sync is off, instead of a failure.
        if (! $faceit->configured()) {
            return response()->json(['message' => 'faceit.disabled'], 503);
        }

        try {
            $panel = $action->execute($request->user(), $request->boolean('refresh'));
        } catch (FaceitUnavailable $e) {
            // An outage on FACEIT's side, not a bug here: one line for Loki, no stack trace.
            Log::warning('faceit.unavailable', ['reason' => $e->getMessage()]);

            return response()->json(['message' => 'faceit.unavailable'], 503);
        }

        return response()->json(['data' => [
            'account' => $panel['account'],
            'reason' => $panel['reason'],
            'matches' => FaceitMatchResource::collection($panel['matches'])->resolve($request),
        ]]);
    }

    // One FACEIT match's start time and map, for the upload form to fill in a FACEIT demo's time.
    // The id's format is checked by the route, so a malformed one never reaches here or FACEIT.
    public function show(string $faceitMatchId, FaceitApi $faceit, GetFaceitMatchAction $action): JsonResponse
    {
        if (! $faceit->configured()) {
            return response()->json(['message' => 'faceit.disabled'], 503);
        }

        try {
            $match = $action->execute($faceitMatchId);
        } catch (FaceitUnavailable $e) {
            Log::warning('faceit.unavailable', ['reason' => $e->getMessage()]);

            return response()->json(['message' => 'faceit.unavailable'], 503);
        }

        if ($match === null) {
            return response()->json(['message' => 'faceit.match_not_found'], 404);
        }

        return response()->json(['data' => [
            'faceit_match_id' => $match['faceit_match_id'],
            'started_at' => $match['started_at'] ? CarbonImmutable::createFromTimestampUTC($match['started_at']) : null,
            'map' => $match['map'],
        ]]);
    }
}
