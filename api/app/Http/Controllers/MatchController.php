<?php

namespace App\Http\Controllers;

use App\Actions\UploadDemoAction;
use App\Http\Requests\UploadDemoRequest;
use App\Http\Resources\MatchResource;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    public function index(): JsonResponse
    {
        return MatchResource::collection(
            GameMatch::latest()->get()
        )->response();
    }

    public function store(UploadDemoRequest $request, UploadDemoAction $action): JsonResponse
    {
        $match = $action->execute($request->file('demo'));

        return (new MatchResource($match))->response()->setStatusCode(201);
    }

    public function show(GameMatch $match): MatchResource
    {
        $match->load(['playerStats' => fn ($q) => $q->orderByDesc('kills')]);

        return new MatchResource($match);
    }
}
