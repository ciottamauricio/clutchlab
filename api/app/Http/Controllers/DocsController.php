<?php

namespace App\Http\Controllers;

use App\Actions\Analysis\AskDocsAction;
use App\Http\Requests\AskDocsRequest;
use Illuminate\Http\JsonResponse;

class DocsController extends Controller
{
    public function ask(AskDocsRequest $request, AskDocsAction $action): JsonResponse
    {
        // Same degradation as the analyst: only the hosted provider needs a key, and the
        // local one is reachable or it isn't — which surfaces as a throw below.
        if (config('clutch.analyst_provider') !== 'ollama' && config('clutch.anthropic.key') === '') {
            return response()->json(['message' => 'docs.unavailable'], 503);
        }

        try {
            $result = $action->execute($request->validated('question'));
        } catch (\Throwable) {
            return response()->json(['message' => 'docs.unavailable'], 503);
        }

        // An empty answer means the index has no chunks at all. That is a build step that
        // was never run, not a provider outage, and it needs its own code — telling the
        // reader the corpus is empty is actionable where "unavailable" is not.
        if ($result['answer'] === '') {
            return response()->json(['message' => 'docs.not_indexed'], 503);
        }

        return response()->json(['data' => $result]);
    }
}
