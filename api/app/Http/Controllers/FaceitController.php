<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListFaceitMatchesRequest;
use App\Http\Resources\FaceitMatchResource;
use App\PlatformSync\Application\GetMatch;
use App\PlatformSync\Application\ListMatches;
use App\PlatformSync\Application\MissingAccount;
use App\PlatformSync\Application\SyncOff;
use App\PlatformSync\Application\Viewer;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Domain\SteamId;
use App\PlatformSync\Ports\PlatformUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// The FACEIT panel's HTTP side, over app/PlatformSync's use cases. The module is platform-neutral;
// FACEIT's routes and its `faceit.*` codes are this controller's.
class FaceitController extends Controller
{
    public function index(ListFaceitMatchesRequest $request, ListMatches $listMatches): JsonResponse
    {
        try {
            $panel = $listMatches->execute($this->viewer($request), $request->boolean('refresh'));
        } catch (SyncOff) {
            return $this->syncOff();
        } catch (PlatformUnavailable $e) {
            return $this->unavailable($e);
        }

        $player = $panel->account?->player;

        return response()->json(['data' => [
            'account' => $player ? ['player_id' => $player->playerId, 'nickname' => $player->nickname] : null,
            'reason' => $panel->reason ? $this->reason($panel->reason) : null,
            'matches' => FaceitMatchResource::collection($panel->rows)->resolve($request),
        ]]);
    }

    // One FACEIT match's start time and map, for the upload form to fill in a FACEIT demo's time.
    // The id's format is checked by the route, so a malformed one never reaches here or FACEIT.
    public function show(string $faceitMatchId, GetMatch $getMatch): JsonResponse
    {
        try {
            $match = $getMatch->execute(new PlatformMatchId($faceitMatchId));
        } catch (SyncOff) {
            return $this->syncOff();
        } catch (PlatformUnavailable $e) {
            return $this->unavailable($e);
        }

        if ($match === null) {
            return response()->json(['message' => 'faceit.match_not_found'], 404);
        }

        return response()->json(['data' => [
            'faceit_match_id' => $match->id->value,
            'started_at' => $match->startedAt ? CarbonImmutable::createFromTimestampUTC($match->startedAt) : null,
            'map' => $match->map,
        ]]);
    }

    private function viewer(Request $request): Viewer
    {
        $user = $request->user();

        return new Viewer($user->id, SteamId::tryFrom($user->steam_id));
    }

    // Why there's no account, as FACEIT's panel codes.
    private function reason(MissingAccount $reason): string
    {
        return match ($reason) {
            MissingAccount::NoSteamId => 'faceit.no_steam_id',
            MissingAccount::NotFound => 'faceit.not_found',
        };
    }

    // No key on this instance: the panel says FACEIT sync is off, instead of a failure.
    private function syncOff(): JsonResponse
    {
        return response()->json(['message' => 'faceit.disabled'], 503);
    }

    // An outage on FACEIT's side, not a bug here: one line for Loki, no stack trace.
    private function unavailable(PlatformUnavailable $e): JsonResponse
    {
        Log::warning('faceit.unavailable', ['reason' => $e->getMessage()]);

        return response()->json(['message' => 'faceit.unavailable'], 503);
    }
}
