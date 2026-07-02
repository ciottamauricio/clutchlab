<?php

namespace App\Http\Controllers;

use App\Contracts\SearchIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    // Allowed filter fields per index and how to coerce the query-string value so it
    // matches the type stored in Meilisearch.
    private const KILL_FILTERS = [
        'map' => 'string', 'weapon' => 'string', 'side' => 'string',
        'headshot' => 'bool', 'opening' => 'bool', 'round' => 'int',
    ];

    private const ROUND_FILTERS = [
        'map' => 'string', 'winner' => 'string', 'reason' => 'string',
        'ct_buy' => 'string', 't_buy' => 'string',
        'ct_alive' => 'int', 't_alive' => 'int', 'round' => 'int',
    ];

    public function kills(Request $request, SearchIndex $index): JsonResponse
    {
        return $this->run($request, $index, 'kills', self::KILL_FILTERS);
    }

    public function rounds(Request $request, SearchIndex $index): JsonResponse
    {
        return $this->run($request, $index, 'rounds', self::ROUND_FILTERS);
    }

    private function run(Request $request, SearchIndex $index, string $name, array $allowed): JsonResponse
    {
        $filters = [];
        foreach ($allowed as $field => $type) {
            if ($request->filled($field)) {
                $filters[$field] = $this->coerce($request->query($field), $type);
            }
        }

        try {
            $result = $index->search($name, (string) $request->query('q', ''), $filters, $request->user()->id);
        } catch (\Throwable) {
            // The engine is a read model — degrade instead of 500ing the request.
            return response()->json(['message' => 'search.unavailable'], 503);
        }

        return response()->json(['data' => ['hits' => $result['hits'], 'total' => $result['total']]]);
    }

    private function coerce(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            default => (string) $value,
        };
    }
}
