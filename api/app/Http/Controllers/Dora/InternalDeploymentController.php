<?php

namespace App\Http\Controllers\Dora;

use App\Actions\Dora\RecordDeploymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dora\RecordDeploymentRequest;
use Illuminate\Http\JsonResponse;

class InternalDeploymentController extends Controller
{
    public function store(RecordDeploymentRequest $request, RecordDeploymentAction $action): JsonResponse
    {
        $deployment = $action->execute($request->validated());

        return response()->json(['id' => $deployment->id], 201);
    }
}
