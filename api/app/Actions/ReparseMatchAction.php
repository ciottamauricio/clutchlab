<?php

namespace App\Actions;

use App\Contracts\ParseQueue;
use App\Models\GameMatch;
use App\Telemetry\Tracing;
use OpenTelemetry\API\Trace\SpanKind;

class ReparseMatchAction
{
    public function __construct(private ParseQueue $queue, private Tracing $tracing) {}

    // Re-run the parse on the demo already in storage — no re-upload. The worker
    // rewrites stats/events idempotently (delete-then-insert), so this just backfills
    // anything a newer parser now extracts (e.g. kill positions for the heatmap).
    public function execute(GameMatch $match): void
    {
        // Traced like the upload path, so a reparse is also one waterfall in Jaeger:
        // this span's traceparent rides the job and the worker's parse becomes its child.
        $span = $this->tracing->tracer()->spanBuilder('reparse_match')
            ->setSpanKind(SpanKind::KIND_PRODUCER)
            ->startSpan();
        $scope = $span->activate();

        try {
            $span->setAttribute('match_id', $match->id);
            $match->status = 'queued';
            $match->error_code = null;
            $match->save();

            $this->queue->push($match->id, $match->demo_key);
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
