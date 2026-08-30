<?php

namespace App\Events\Subscribers;

use App\Actions\Dora\RecordParseEventAction;

// Records every parse outcome for the Reliability SLO. Two thin subclasses below bind it
// to match.parsed and match.failed, since a handler declares exactly one event name.
//
// This rides the fact the worker already publishes rather than adding an HTTP endpoint or
// a second Redis list: the outcome is already crossing the service boundary, and a metric
// that needs its own transport is a metric that can disagree with the thing it measures.
// Handlers are isolated by the listener, so a telemetry failure can never cost a user
// their parsed match — the ordering that matters when measurement sits next to delivery.
abstract class RecordParseTelemetry implements EventHandler
{
    public function __construct(private readonly RecordParseEventAction $action) {}

    abstract protected function status(): string;

    public function handle(array $payload): void
    {
        $this->action->execute([
            'match_id' => $payload['match_id'] ?? null,
            'status' => $this->status(),
            // Absent from older workers (the field is additive) — recorded as null and
            // excluded from the SLO rather than counted as an instant success.
            'duration_ms' => $payload['duration_ms'] ?? null,
            'error_code' => $payload['error_code'] ?? null,
        ]);
    }
}
