<?php

namespace App\Events\Subscribers;

use App\Actions\Analysis\BuildMatchCardAction;
use App\Actions\Analysis\BuildRoundCardAction;
use App\Contracts\RoundRetriever;
use App\Contracts\SemanticRetriever;
use App\Models\GameMatch;
use Illuminate\Support\Facades\Log;

// Keeps the analyst's semantic index current: one parse, one new card. Without this the
// index only moves when someone remembers `analyst:embed`, and a stale index doesn't
// announce itself — the analyst just answers as though the newest matches don't exist.
//
// The second reaction to match.parsed, and the reason the event pattern earns its keep:
// the worker publishes a fact, and reactions are added here without it knowing.
class EmbedParsedMatch implements EventHandler
{
    public function __construct(
        private SemanticRetriever $retriever,
        private BuildMatchCardAction $card,
        private RoundRetriever $rounds,
        private BuildRoundCardAction $roundCard,
    ) {}

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

        // The card is built from the matches row, which ApplyMatchParsed writes from this
        // same event — so this handler MUST run after it (registration order in
        // AppServiceProvider). Read the row back rather than the payload: whatever
        // ApplyMatchParsed persisted is what the analyst will later cite.
        $match = GameMatch::with('playerStats')->find($id);
        if (! $match) {
            Log::warning("embed match.parsed: match {$id} not found (deleted before delivery?)");

            return;
        }

        // Guard the ordering rather than trusting it: an unparsed row still has null
        // map/score, and embedding it would write a contentless card that looks fine.
        if ($match->status !== 'parsed') {
            Log::warning("embed match.parsed: match {$id} is '{$match->status}', not parsed — skipping");

            return;
        }

        $this->retriever->index($match->id, $this->card->execute($match));

        // Rounds too, at the grain below the match card. forget() first because a re-parse
        // can produce fewer rounds than the previous one — indexing alone would leave the
        // surplus behind as cards for rounds that no longer exist.
        $this->rounds->forget($match->id);
        foreach ($this->roundCard->forMatch($match->id) as $round) {
            $this->rounds->index($match->id, (int) $round->round, $this->roundCard->execute($round));
        }
    }
}
