<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// The safety net for the split's at-most-once status handoff (docs/plans/split-the-database.md):
// a match.parsed event lost while the listener is down strands a match in 'parsing'.
// matches:reconcile sweeps those — flipping ones whose analytics stats already exist, and
// re-enqueuing ones that truly never finished. SpyParseQueue captures re-enqueues.
class ReconcileStuckMatchesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    // Age a match's updated_at past the grace window without tripping auto-timestamps.
    private function strand(GameMatch $match, string $status = 'parsing'): void
    {
        DB::table('matches')->where('id', $match->id)->update([
            'status' => $status,
            'updated_at' => now()->subMinutes(3),
        ]);
    }

    private function addStat(GameMatch $match): void
    {
        DB::table('match_player_stats')->insert([
            'match_id' => $match->id, 'steam_id' => '765611980001', 'name' => 'p',
            'team_side' => 'CT', 'kills' => 10, 'deaths' => 5, 'assists' => 2, 'headshots' => 4,
        ]);
    }

    public function test_a_stuck_match_with_stats_is_marked_parsed_not_reparsed(): void
    {
        $match = GameMatch::factory()->create();
        $this->strand($match);
        $this->addStat($match); // the parse actually finished; only the status event was lost

        $this->artisan('matches:reconcile')->assertSuccessful();

        $this->assertSame('parsed', $match->fresh()->status);
        $this->assertNotNull($match->fresh()->parsed_at);
        $this->assertSame([], $this->parseQueue->pushed); // NOT re-enqueued
    }

    public function test_a_stuck_match_without_stats_is_re_enqueued(): void
    {
        $match = GameMatch::factory()->create(['demo_key' => 'demos/x.dem']);
        $this->strand($match); // no stats rows → the parse never completed

        $this->artisan('matches:reconcile')->assertSuccessful();

        $this->assertSame([$match->id], array_column($this->parseQueue->pushed, 0)); // re-enqueued
    }

    public function test_a_recently_parsing_match_is_left_alone(): void
    {
        // Within the grace window — a legitimately in-flight parse must not be touched.
        $match = GameMatch::factory()->create(['status' => 'parsing']);

        $this->artisan('matches:reconcile')->assertSuccessful();

        $this->assertSame('parsing', $match->fresh()->status);
        $this->assertSame([], $this->parseQueue->pushed);
    }

    // Upload creates a match as 'queued' and a handler moves it on, so a listener down for
    // the WHOLE parse leaves it there — never reaching 'parsing'. Sweeping only 'parsing'
    // let these sit stranded forever with their stats already written.
    public function test_a_stuck_queued_match_with_stats_is_marked_parsed(): void
    {
        $match = GameMatch::factory()->create();
        $this->strand($match, 'queued');
        $this->addStat($match);

        $this->artisan('matches:reconcile')->assertSuccessful();

        $this->assertSame('parsed', $match->fresh()->status);
        $this->assertSame([], $this->parseQueue->pushed);
    }

    public function test_a_stuck_queued_match_without_stats_is_re_enqueued(): void
    {
        $match = GameMatch::factory()->create(['demo_key' => 'demos/y.dem']);
        $this->strand($match, 'queued'); // the job itself was lost before any parse ran

        $this->artisan('matches:reconcile')->assertSuccessful();

        $this->assertSame([$match->id], array_column($this->parseQueue->pushed, 0));
    }

    public function test_a_freshly_queued_match_is_left_alone(): void
    {
        // The common case: just uploaded, worker hasn't picked it up yet. Re-enqueuing
        // inside the grace window would double-parse every single upload.
        $match = GameMatch::factory()->create(['status' => 'queued']);

        $this->artisan('matches:reconcile')->assertSuccessful();

        $this->assertSame('queued', $match->fresh()->status);
        $this->assertSame([], $this->parseQueue->pushed);
    }
}
