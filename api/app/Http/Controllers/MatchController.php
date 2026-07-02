<?php

namespace App\Http\Controllers;

use App\Actions\UploadDemoAction;
use App\Http\Requests\UploadDemoRequest;
use App\Http\Resources\MatchResource;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return MatchResource::collection(
            $request->user()->matches()->latest()->get()
        )->response();
    }

    public function store(UploadDemoRequest $request, UploadDemoAction $action): JsonResponse
    {
        $match = $action->execute($request->file('demo'), $request->user());

        return (new MatchResource($match))->response()->setStatusCode(201);
    }

    public function show(Request $request, GameMatch $match): MatchResource
    {
        $this->authorize('view', $match);

        $match->load(['playerStats' => fn ($q) => $q->orderByDesc('kills')]);

        return new MatchResource($match);
    }
}
