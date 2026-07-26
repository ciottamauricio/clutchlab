<?php

namespace App\Actions\Analysis;

use App\Models\GameMatch;

// The text that gets embedded (and shown to the model as semantic evidence): a compact
// prose "card" for one match. Prose, not JSON, because the embedder works on words —
// the card names the map, outcome, and standout players so a question like "our best
// showings on Nuke" lands near the right matches. One match → one card → one vector.
class BuildMatchCardAction
{
    public function execute(GameMatch $match): string
    {
        $match->loadMissing('playerStats');

        $map = $match->map_name ?? 'unknown map';
        $date = $match->played_at?->toDateString() ?? 'undated';
        $ct = $match->ct_name ?: 'CT';
        $t = $match->t_name ?: 'T';
        $outcome = "{$ct} {$match->score_ct} - {$match->score_t} {$t}";

        // Top fraggers give the card player-name and performance signal to match on.
        $top = $match->playerStats
            ->sortByDesc('kills')
            ->take(5)
            ->map(fn ($p) => "{$p->name} ({$p->kills} kills, {$p->deaths} deaths)")
            ->implode(', ');

        return trim(
            "Match on {$map}, played {$date}. Result: {$outcome}. "
            .($top !== '' ? "Top performers: {$top}." : 'No scoreboard.')
        );
    }
}
