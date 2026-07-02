<?php

namespace App\Http\Controllers;

use App\Actions\CreateTacticAction;
use App\Http\Requests\StoreTacticRequest;
use App\Http\Requests\UpdateTacticRequest;
use App\Http\Resources\TacticResource;
use App\Models\Tactic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TacticController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return TacticResource::collection(
            $request->user()->tactics()->latest()->get()
        )->response();
    }

    public function store(StoreTacticRequest $request, CreateTacticAction $action): JsonResponse
    {
        $tactic = $action->execute($request->user(), $request->validated('name'));

        return (new TacticResource($tactic))->response()->setStatusCode(201);
    }

    public function show(Tactic $tactic): TacticResource
    {
        $this->authorize('view', $tactic);

        return new TacticResource($tactic);
    }

    public function update(UpdateTacticRequest $request, Tactic $tactic): TacticResource
    {
        $this->authorize('update', $tactic);

        $tactic->update($request->validated());

        return new TacticResource($tactic);
    }

    public function destroy(Tactic $tactic): Response
    {
        $this->authorize('delete', $tactic);

        $tactic->delete();

        return response()->noContent();
    }
}
