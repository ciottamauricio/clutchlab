<?php

namespace App\Http\Controllers;

use App\Actions\Analysis\AskAnalystAction;
use App\Http\Requests\AskAnalystRequest;
use Illuminate\Http\JsonResponse;

class AnalystController extends Controller
{
    public function ask(AskAnalystRequest $request, AskAnalystAction $action): JsonResponse
    {
        // Not configured, or the provider is down — degrade like search does, instead of
        // surfacing a 500 with provider internals. Only the hosted provider needs a key;
        // the local one is reachable or it isn't, and that shows up as a throw below.
        if (config('clutch.analyst_provider') !== 'ollama' && config('clutch.anthropic.key') === '') {
            return response()->json(['message' => 'analyst.unavailable'], 503);
        }

        try {
            $answer = $action->execute($request->user(), $request->validated('question'));
        } catch (\Throwable) {
            return response()->json(['message' => 'analyst.unavailable'], 503);
        }

        return response()->json(['data' => ['answer' => $answer]]);
    }
}
