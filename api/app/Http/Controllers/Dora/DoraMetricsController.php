<?php

namespace App\Http\Controllers\Dora;

use App\Http\Controllers\Controller;
use App\Services\Dora\MetricsCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoraMetricsController extends Controller
{
    public function index(Request $request, MetricsCalculator $metrics): JsonResponse
    {
        // Bounded: the window is a plain query param, and an unbounded one lets any
        // admin ask for a full-table scan by typing a bigger number.
        $window = max(1, min($request->integer('window', config('clutch.dora.window_days')), 365));

        $to = now();
        $from = $to->copy()->subDays($window);

        return response()->json([
            'window_days' => $window,
            'generated_at' => $to->toIso8601String(),
            'metrics' => [
                'deployment_frequency' => $metrics->deploymentFrequency($from, $to),
                'lead_time' => $metrics->medianLeadTime($from, $to),
                'change_failure_rate' => $metrics->changeFailureRate($from, $to),
                'time_to_restore' => $metrics->medianTimeToRestore($from, $to),
                'reliability' => $metrics->reliability($from, $to),
            ],
            'trend' => $metrics->trend($from, $to),
        ]);
    }
}
