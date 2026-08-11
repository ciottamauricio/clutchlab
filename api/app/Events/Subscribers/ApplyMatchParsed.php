<?php

namespace App\Events\Subscribers;

use App\Models\GameMatch;
use Illuminate\Support\Facades\Log;

// Applies the worker's match.parsed fact to the matches row. Since the DB split the
// worker owns only analytics.* and can't write matches, so it reports the parsed summary
// as an event and the api — which owns the row — persists it here. The scoreboard itself
// already lives in analytics.match_player_stats (written by the worker).
class ApplyMatchParsed implements EventHandler
{
    public function handles(): string
    {
        return 'match.parsed';
    }

    public function handle(array $payload): void
    {
        $id = $payload['match_id'] ?? null;
        if (! $id) {
            return;
        }

        $match = GameMatch::find($id);
        if (! $match) {
            Log::warning("apply match.parsed: match {$id} not found (deleted before delivery?)");

            return;
        }

        // Set attributes directly, not fill(): the summary columns aren't in the model's
        // #[Fillable] (they're never user-writable — only a parse produces them), so
        // mass-assignment would silently drop every one of them.
        $match->status = 'parsed';
        $match->error_code = null;
        $match->map_name = $payload['map'] ?? null;
        $match->score_ct = $payload['score_ct'] ?? null;
        $match->score_t = $payload['score_t'] ?? null;
        $match->ct_name = $payload['ct_name'] ?? null;
        $match->t_name = $payload['t_name'] ?? null;
        $match->total_rounds = $payload['total_rounds'] ?? null;
        $match->tick_rate = $payload['tick_rate'] ?? null;
        $match->duration_seconds = $payload['duration_seconds'] ?? null;
        $match->knife_round_winner = $payload['knife_round_winner'] ?: null;
        $match->parsed_at = now();
        $match->save();
    }
}
