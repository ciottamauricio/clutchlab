<?php

namespace App\Http\Controllers;

use App\Actions\Faceit\ListFaceitMatchesAction;
use App\Contracts\FaceitApi;
use App\Faceit\FaceitUnavailable;
use App\Http\Requests\ListFaceitMatchesRequest;
use App\Http\Resources\FaceitMatchResource;
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
}
