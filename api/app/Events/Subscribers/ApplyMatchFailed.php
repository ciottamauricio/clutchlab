<?php

namespace App\Events\Subscribers;

use App\Models\GameMatch;
use Illuminate\Support\Facades\Log;

// Applies the worker's match.failed fact to the matches row (the worker can't write it
// since the DB split). Sets the terminal failed status + the error_code the worker
// reported. At-most-once: a dropped event leaves the row in 'parsing' — see the reconcile
// note in docs/plans/split-the-database.md.
class ApplyMatchFailed implements EventHandler
{
    public function handles(): string
    {
        return 'match.failed';
    }

    public function handle(array $payload): void
    {
        $id = $payload['match_id'] ?? null;
        if (! $id) {
            return;
        }

        $match = GameMatch::find($id);
        if (! $match) {
            Log::warning("apply match.failed: match {$id} not found (deleted before delivery?)");

            return;
        }

        $match->status = 'failed';
        $match->error_code = $payload['error_code'] ?? 'parse_failed_internal';
        $match->save();
    }
}
