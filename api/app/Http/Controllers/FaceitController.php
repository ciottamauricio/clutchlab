<?php

namespace App\Http\Controllers;

use App\Actions\Faceit\FaceitSyncOff;
use App\Actions\Faceit\GetFaceitMatchAction;
use App\Actions\Faceit\ListFaceitMatchesAction;
use App\Contracts\FaceitUnavailable;
use App\Http\Requests\ListFaceitMatchesRequest;
use App\Http\Resources\FaceitMatchResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FaceitController extends Controller
{
    public function index(ListFaceitMatchesRequest $request, ListFaceitMatchesAction $action): JsonResponse
    {
        try {
            $panel = $action->execute($request->user(), $request->boolean('refresh'));
        } catch (FaceitSyncOff) {
            return $this->syncOff();
        } catch (FaceitUnavailable $e) {
            return $this->unavailable($e);
        }

        return response()->json(['data' => [
            'account' => $panel['account'],
            'reason' => $panel['reason'],
            'matches' => FaceitMatchResource::collection($panel['matches'])->resolve($request),
        ]]);
    }

    // One FACEIT match's start time and map, for the upload form to fill in a FACEIT demo's time.
    // The id's format is checked by the route, so a malformed one never reaches here or FACEIT.
    public function show(string $faceitMatchId, GetFaceitMatchAction $action): JsonResponse
    {
        try {
            $match = $action->execute($faceitMatchId);
        } catch (FaceitSyncOff) {
            return $this->syncOff();
        } catch (FaceitUnavailable $e) {
            return $this->unavailable($e);
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

    // No key on this instance: the panel says FACEIT sync is off, instead of a failure.
    private function syncOff(): JsonResponse
    {
        return response()->json(['message' => 'faceit.disabled'], 503);
    }

    // An outage on FACEIT's side, not a bug here: one line for Loki, no stack trace.
    private function unavailable(FaceitUnavailable $e): JsonResponse
    {
        Log::warning('faceit.unavailable', ['reason' => $e->getMessage()]);

        return response()->json(['message' => 'faceit.unavailable'], 503);
    }
}
