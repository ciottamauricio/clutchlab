<?php

namespace App\Console\Commands;

use App\Actions\ReparseMatchAction;
use App\Models\GameMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// The safety net for the split's at-most-once status handoff. The worker reports a parse
// outcome as a match.parsed / match.failed event; pub/sub is fire-and-forget, so a
// message lost while the listener is down (or briefly deaf) strands a match in 'parsing'
// with no redelivery. This sweeps those: any match stuck in 'parsing' past a grace
// window is reconciled.
//
// Two cases, handled safely:
//   - analytics stats already exist  → the parse finished; the only thing lost was the
//     status flip, so mark it parsed (idempotent, no re-work).
//   - no stats                       → the parse truly didn't complete; re-enqueue it and
//     let the worker redo + re-publish (its writes are idempotent).
class ReconcileStuckMatches extends Command
{
    // Grace window in seconds: long enough that a genuinely in-flight parse (a few seconds,
    // or a slow one) is never falsely reconciled, short enough that a match stranded by a
    // lost event heals quickly rather than sitting "parsing" for minutes. A real parse
    // finishes in seconds, so ~90s stuck almost always means a missed event, not slowness.
    protected $signature = 'matches:reconcile {--seconds=90 : how long a match may sit unfinished before it is considered stuck}';

    protected $description = 'Rescue matches stranded before a terminal status by a lost parse-outcome event';

    public function handle(ReparseMatchAction $reparse): int
    {
        $cutoff = now()->subSeconds((int) $this->option('seconds'));

        // Both pre-terminal states, not just 'parsing'. Upload creates a match as 'queued'
        // and only a handler moves it on, so a listener that's down for the WHOLE parse
        // leaves it at 'queued' forever — never entering the state a parsing-only sweep
        // watches, and invisible to the very safety net meant to catch it.
        $stuck = GameMatch::whereIn('status', ['queued', 'parsing'])
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('matches:reconcile — nothing stuck.');

            return self::SUCCESS;
        }

        foreach ($stuck as $match) {
            $hasStats = DB::table('match_player_stats')->where('match_id', $match->id)->exists();

            if ($hasStats) {
                // The parse completed; only the status event was lost. Flip it, don't re-parse.
                $match->status = 'parsed';
                $match->error_code = null;
                $match->parsed_at = $match->parsed_at ?? now();
                $match->save();
                $this->line("  match {$match->id}: stats present → marked parsed");
            } else {
                // No evidence it finished — re-enqueue; the worker redoes it idempotently.
                $reparse->execute($match);
                $this->line("  match {$match->id}: no stats → re-enqueued");
            }
        }

        $this->info("matches:reconcile — reconciled {$stuck->count()} stuck match(es).");

        return self::SUCCESS;
    }
}
