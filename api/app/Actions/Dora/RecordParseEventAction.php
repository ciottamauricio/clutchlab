<?php

namespace App\Actions\Dora;

use App\Models\ParseEvent;

// Records one parse outcome for the Reliability SLO. Called from the clutch_events
// subscriber, not from an HTTP route: the worker already announces every parse outcome
// on that channel, so telemetry rides the fact that is already crossing the boundary
// rather than opening a second path for the same information.
class RecordParseEventAction
{
    public function execute(array $data): ParseEvent
    {
        return ParseEvent::create($data);
    }
}
