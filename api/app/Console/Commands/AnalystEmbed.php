<?php

namespace App\Console\Commands;

use App\Actions\Analysis\BuildMatchCardAction;
use App\Actions\Analysis\BuildRoundCardAction;
use App\Contracts\RoundRetriever;
use App\Contracts\SemanticRetriever;
use App\Models\GameMatch;
use Illuminate\Console\Command;

// Builds the semantic read model from Postgres (the source of truth): one embedded card
// per parsed match. Rerunnable — like search:reindex, it's the projection you can always
// rebuild. Run after adding matches; a later step can call the retriever on each parse.
class AnalystEmbed extends Command
{
    protected $signature = 'analyst:embed {--match= : only (re)embed this match id}';

    protected $description = 'Build the analyst semantic index (match cards) from Postgres';

    public function handle(
        SemanticRetriever $retriever,
        BuildMatchCardAction $card,
        RoundRetriever $rounds,
        BuildRoundCardAction $roundCard,
    ): int {
        $query = GameMatch::where('status', 'parsed');
        if ($id = $this->option('match')) {
            $query->whereKey($id);
        }

        $matchCount = 0;
        $roundCount = 0;

        $query->with('playerStats')->chunk(100, function ($matches) use ($retriever, $card, $rounds, $roundCard, &$matchCount, &$roundCount) {
            foreach ($matches as $match) {
                $retriever->index($match->id, $card->execute($match));
                $matchCount++;

                // Clear first: a re-parse can yield fewer rounds than the last one, and
                // per-round upserts alone would leave the extras behind as phantom cards.
                $rounds->forget($match->id);
                foreach ($roundCard->forMatch($match->id) as $round) {
                    $rounds->index($match->id, (int) $round->round, $roundCard->execute($round));
                    $roundCount++;
                }
            }
        });

        $this->info("Embedded {$matchCount} match card(s) and {$roundCount} round card(s).");

        return self::SUCCESS;
    }
}
