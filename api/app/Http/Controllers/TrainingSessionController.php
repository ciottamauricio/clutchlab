<?php

namespace App\Http\Controllers;

use App\Actions\Trainings\CreateTrainingSessionAction;
use App\Actions\Trainings\DeleteTrainingSessionAction;
use App\Actions\Trainings\UpdateTrainingSessionAction;
use App\Http\Requests\Trainings\StoreTrainingSessionRequest;
use App\Http\Requests\Trainings\UpdateTrainingSessionRequest;
use App\Http\Resources\TrainingSessionResource;
use App\Models\Team;
use App\Models\TrainingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingSessionController extends Controller
{
    // Chronological: the frontend splits upcoming/past at "now".
    public function index(Request $request): JsonResponse
    {
        $sessions = TrainingSession::with(['team', 'creator', 'tactics', 'players'])
            ->whereIn('team_id', $request->user()->teams()->pluck('teams.id'))
            ->orderBy('scheduled_at')
            ->get();

        return TrainingSessionResource::collection($sessions)->response();
    }

    public function store(StoreTrainingSessionRequest $request, CreateTrainingSessionAction $action): JsonResponse
    {
        $session = $action->execute(
            Team::findOrFail($request->validated('team_id')),
            $request->user(),
            $request->validated(),
        );

        return (new TrainingSessionResource($session))->response()->setStatusCode(201);
    }

    public function show(Request $request, TrainingSession $training): TrainingSessionResource
    {
        $this->authorize('view', $training);

        return new TrainingSessionResource($training->load(['team', 'creator', 'tactics', 'players']));
    }

    public function update(UpdateTrainingSessionRequest $request, TrainingSession $training, UpdateTrainingSessionAction $action): TrainingSessionResource
    {
        $this->authorize('update', $training);

        return new TrainingSessionResource($action->execute($training, $request->validated()));
    }

    public function destroy(Request $request, TrainingSession $training, DeleteTrainingSessionAction $action): JsonResponse
    {
        $this->authorize('delete', $training);

        $action->execute($training);

        return response()->json(status: 204);
    }
}
