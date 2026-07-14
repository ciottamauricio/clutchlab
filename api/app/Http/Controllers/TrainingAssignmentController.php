<?php

namespace App\Http\Controllers;

use App\Actions\Trainings\CreateAssignmentAction;
use App\Actions\Trainings\DeleteAssignmentAction;
use App\Actions\Trainings\ToggleAssignmentDoneAction;
use App\Http\Requests\Trainings\StoreAssignmentRequest;
use App\Http\Requests\Trainings\UpdateAssignmentRequest;
use App\Http\Resources\TrainingAssignmentResource;
use App\Models\TrainingAssignment;
use App\Models\TrainingSession;
use Illuminate\Http\JsonResponse;

class TrainingAssignmentController extends Controller
{
    public function store(StoreAssignmentRequest $request, TrainingSession $training, CreateAssignmentAction $action): JsonResponse
    {
        $this->authorize('update', $training); // assigning homework = managing the session

        $assignment = $action->execute(
            $training,
            $request->validated('user_id'),
            $request->validated('map'),
            $request->validated('nade_type'),
        );

        return (new TrainingAssignmentResource($assignment))->response()->setStatusCode(201);
    }

    // The assignee marking their own homework studied — training.manage does not grant this.
    public function update(UpdateAssignmentRequest $request, TrainingSession $training, TrainingAssignment $assignment, ToggleAssignmentDoneAction $action): TrainingAssignmentResource
    {
        $this->authorize('complete', $assignment);

        return new TrainingAssignmentResource($action->execute($assignment, $request->validated('done')));
    }

    public function destroy(TrainingSession $training, TrainingAssignment $assignment, DeleteAssignmentAction $action): JsonResponse
    {
        $this->authorize('manage', $assignment);

        $action->execute($assignment);

        return response()->json(status: 204);
    }
}
