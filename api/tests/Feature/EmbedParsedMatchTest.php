<?php

namespace Tests\Feature;

use App\Events\Subscribers\ApplyMatchParsed;
use App\Events\Subscribers\EmbedParsedMatch;
use App\Models\GameMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The second reaction to match.parsed: the analyst's semantic index follows a parse
// instead of waiting for someone to run `analyst:embed`. FakeSemanticRetriever records
// what was indexed, so no embedder and no vector column are needed.
class EmbedParsedMatchTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** The real wire payload, as pinned by contracts/match_parsed.json. */
    private function payload(int $matchId): array
    {
        $fixture = json_decode(file_get_contents(base_path('../contracts/match_parsed.json')), true);

        return [...$fixture, 'match_id' => $matchId];
    }

    private function deliver(GameMatch $match): void
    {
        // Both handlers, in registration order — the card is built from what the first wrote.
        $payload = $this->payload($match->id);
        app(ApplyMatchParsed::class)->handle($payload);
        app(EmbedParsedMatch::class)->handle($payload);
    }

    public function test_a_parsed_match_is_embedded_without_running_the_command(): void
    {
        $match = GameMatch::factory()->create(['status' => 'queued']);

        $this->deliver($match);

        $this->assertArrayHasKey($match->id, $this->semanticRetriever->indexed);
    }

    public function test_the_card_describes_the_match_the_event_just_applied(): void
    {
        $match = GameMatch::factory()->create(['status' => 'queued']);

        $this->deliver($match);

        // The map and score arrive with the event; embedding before ApplyMatchParsed would
        // silently produce "unknown map" instead — the ordering this pair depends on.
        $card = $this->semanticRetriever->indexed[$match->id];
        $this->assertStringContainsString('de_mirage', $card);
        $this->assertStringContainsString('13 - 9', $card);
    }

    public function test_an_unparsed_match_is_not_embedded_as_a_contentless_card(): void
    {
        // Ordering guard: if this handler ever runs before the row is updated, skip rather
        // than write a card with no map, no score and no players.
        $match = GameMatch::factory()->create(['status' => 'queued']);

        app(EmbedParsedMatch::class)->handle($this->payload($match->id));

        $this->assertSame([], $this->semanticRetriever->indexed);
    }

    public function test_an_event_for_a_deleted_match_is_dropped_not_fatal(): void
    {
        app(EmbedParsedMatch::class)->handle($this->payload(999999));

        $this->assertSame([], $this->semanticRetriever->indexed);
    }

    // Rounds ride the same event as the match card, at the grain below it.
    public function test_rounds_are_cleared_before_re_embedding_so_a_shorter_reparse_leaves_no_orphans(): void
    {
        $match = GameMatch::factory()->create(['status' => 'queued']);

        // A previous, longer parse left cards behind (this match has no round_events rows
        // in the test DB, so nothing re-indexes them — exactly the orphan case).
        $this->roundRetriever->index($match->id, 30, 'stale round from a longer parse');

        $this->deliver($match);

        $this->assertContains($match->id, $this->roundRetriever->forgotten);
        $this->assertSame([], $this->roundRetriever->indexed);
    }

    public function test_re_embedding_replaces_the_card_rather_than_duplicating_it(): void
    {
        $match = GameMatch::factory()->create(['status' => 'queued']);

        $this->deliver($match);
        $this->deliver($match);

        $this->assertCount(1, $this->semanticRetriever->indexed);
    }
}
