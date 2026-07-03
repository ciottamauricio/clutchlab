<?php

namespace App\Actions;

use App\Contracts\ParseQueue;
use App\Models\GameMatch;

class ReparseMatchAction
{
    public function __construct(private ParseQueue $queue) {}

    // Re-run the parse on the demo already in storage — no re-upload. The worker
    // rewrites stats/events idempotently (delete-then-insert), so this just backfills
    // anything a newer parser now extracts (e.g. kill positions for the heatmap).
    public function execute(GameMatch $match): void
    {
        $match->status = 'queued';
        $match->error_code = null;
        $match->save();

        $this->queue->push($match->id, $match->demo_key);
    }
}
