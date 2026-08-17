<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Tactic;
use App\Models\Team;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// The RAG endpoint: retrieval is scoped to the caller's visible matches, generation is
// behind the AnalystLlm seam (spied here — no real API call ever happens in tests).
class AnalystTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function seat(GameMatch $match, string $steamId, string $name, string $side, int $kills): void
    {
        $match->playerStats()->forceCreate([
            'steam_id' => $steamId,
            'name' => $name,
            'team_side' => $side,
            'kills' => $kills,
        ]);
    }

    private function asMember(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['clutch.anthropic.key' => 'test-key']);

        return $user;
    }

    public function test_answers_with_evidence_from_own_and_team_matches_only(): void
    {
        $me = $this->asMember();
        $team = Team::factory()->create();
        $team->members()->attach($me, ['role' => 'player']);

        $mine = GameMatch::factory()->create(['user_id' => $me->id, 'map_name' => 'de_mirage', 'score_ct' => 13, 'score_t' => 7]);
        $this->seat($mine, '765611980001', 'ropz', 'CT', 25);

        $teams = GameMatch::factory()->create(['team_id' => $team->id, 'map_name' => 'de_inferno', 'score_ct' => 5, 'score_t' => 13]);

        // A stranger's match must never reach the model.
        $strangers = GameMatch::factory()->create(['map_name' => 'de_nuke']);

        $this->postJson('/api/analyst/ask', ['question' => 'How do we do on Mirage?'])
            ->assertOk()
            ->assertJsonPath('data.answer', $this->analystLlm->reply);

        $ids = array_column($this->analystLlm->evidence['recent_matches'], 'id');
        $this->assertContains($mine->id, $ids);
        $this->assertContains($teams->id, $ids);
        $this->assertNotContains($strangers->id, $ids);

        // The scoreboard travels with the match, so the model can answer stat questions.
        $mineRow = collect($this->analystLlm->evidence['recent_matches'])->firstWhere('id', $mine->id);
        $this->assertSame('de_mirage', $mineRow['map']);
        $this->assertSame(['CT' => 13, 'T' => 7], $mineRow['score']);
        $this->assertSame('ropz', $mineRow['scoreboard'][0]['name']);
        $this->assertSame(25, $mineRow['scoreboard'][0]['k']);

        $this->assertSame('How do we do on Mirage?', $this->analystLlm->question);
    }

    public function test_own_teams_trainings_are_evidence_with_intent_details(): void
    {
        $me = $this->asMember();
        $team = Team::factory()->create(['name' => 'LOLO Clan']);
        $team->members()->attach($me, ['role' => 'player']);

        $tactic = Tactic::factory()->create(['user_id' => $me->id, 'name' => 'B split', 'map' => 'de_mirage']);
        $training = TrainingSession::factory()->create([
            'team_id' => $team->id,
            'title' => 'A-executes + retakes',
            'scheduled_at' => '2026-07-20 19:00:00',
        ]);
        $training->tactics()->attach($tactic);
        $training->players()->attach($me, ['rsvp' => 'in']);
        $training->assignments()->create(['user_id' => $me->id, 'map' => 'de_mirage', 'nade_type' => 'smoke']);

        // Another team's practice must never reach the model.
        TrainingSession::factory()->create(['title' => 'their secret strats']);

        $this->postJson('/api/analyst/ask', ['question' => 'What did we practice lately?'])->assertOk();

        $trainings = $this->analystLlm->evidence['recent_trainings'];
        $this->assertCount(1, $trainings);
        $this->assertSame('A-executes + retakes', $trainings[0]['title']);
        $this->assertSame('LOLO Clan', $trainings[0]['team']);
        $this->assertFalse($trainings[0]['canceled']);
        $this->assertSame(['B split (de_mirage)'], $trainings[0]['tactics']);
        $this->assertSame([['name' => $me->name, 'rsvp' => 'in']], $trainings[0]['roster']);
        $this->assertSame(
            [['player' => $me->name, 'map' => 'de_mirage', 'nade' => 'smoke', 'done' => false]],
            $trainings[0]['homework'],
        );
    }

    public function test_semantic_recall_can_surface_matches_beyond_the_recent_window(): void
    {
        $me = $this->asMember();
        // 15 recent matches fill the window; an older one falls out of recent_matches.
        GameMatch::factory()->count(15)->create(['user_id' => $me->id, 'played_at' => now()]);
        $old = GameMatch::factory()->create(['user_id' => $me->id, 'played_at' => now()->subYear()]);

        // The vector store returns the old match as most related.
        $this->semanticRetriever->hits = [
            ['match_id' => $old->id, 'document' => 'Match on de_nuke...', 'similarity' => 0.82],
        ];

        $this->postJson('/api/analyst/ask', ['question' => 'our best game on Nuke ever'])->assertOk();

        $recentIds = array_column($this->analystLlm->evidence['recent_matches'], 'id');
        $this->assertNotContains($old->id, $recentIds); // pushed out of the window
        $related = $this->analystLlm->evidence['semantically_related_matches'];
        $this->assertSame($old->id, $related[0]['match_id']); // but recalled semantically
        $this->assertSame(0.82, $related[0]['similarity']);
    }

    // Round cards answer what match cards can't: which ROUND, not just which game.
    public function test_related_rounds_are_evidence_alongside_matches(): void
    {
        $me = $this->asMember();
        $match = GameMatch::factory()->create(['user_id' => $me->id]);

        $this->roundRetriever->hits = [
            ['match_id' => $match->id, 'round' => 12, 'document' => 'Round 12 ... clutch', 'similarity' => 0.77],
        ];

        $this->postJson('/api/analyst/ask', ['question' => 'clutches we won'])->assertOk();

        $rounds = $this->analystLlm->evidence['semantically_related_rounds'];
        $this->assertSame(12, $rounds[0]['round']);
        $this->assertSame($match->id, $rounds[0]['match_id']);
    }

    public function test_round_retrieval_is_scoped_to_visible_matches(): void
    {
        $me = $this->asMember();
        $mine = GameMatch::factory()->create(['user_id' => $me->id]);
        $strangers = GameMatch::factory()->create();

        // A round is visible exactly when its match is — the scope is the same set.
        $this->roundRetriever->hits = [
            ['match_id' => $strangers->id, 'round' => 3, 'document' => 'secret', 'similarity' => 0.99],
            ['match_id' => $mine->id, 'round' => 5, 'document' => 'mine', 'similarity' => 0.4],
        ];

        $this->postJson('/api/analyst/ask', ['question' => 'eco rounds we stole'])->assertOk();

        $this->assertNotContains($strangers->id, $this->roundRetriever->lastScope);
        $ids = array_column($this->analystLlm->evidence['semantically_related_rounds'], 'match_id');
        $this->assertSame([$mine->id], $ids);
    }

    public function test_a_round_store_failure_does_not_fail_the_answer(): void
    {
        $me = $this->asMember();
        GameMatch::factory()->create(['user_id' => $me->id]);

        // Round detail is a bonus; losing it must never cost the whole answer.
        $this->app->instance(\App\Contracts\RoundRetriever::class, new class extends \Tests\Fakes\FakeRoundRetriever
        {
            public function related(string $query, array $matchIds, int $limit = 3): array
            {
                throw new \RuntimeException('vector store down');
            }
        });

        $this->postJson('/api/analyst/ask', ['question' => 'clutches we won'])->assertOk();

        $this->assertSame([], $this->analystLlm->evidence['semantically_related_rounds']);
    }

    public function test_semantic_retrieval_is_scoped_to_visible_matches(): void
    {
        $me = $this->asMember();
        $mine = GameMatch::factory()->create(['user_id' => $me->id]);
        $strangers = GameMatch::factory()->create();

        // Even if the store were to offer a stranger's match, the scope excludes it.
        $this->semanticRetriever->hits = [
            ['match_id' => $strangers->id, 'document' => 'secret', 'similarity' => 0.99],
            ['match_id' => $mine->id, 'document' => 'mine', 'similarity' => 0.4],
        ];

        $this->postJson('/api/analyst/ask', ['question' => 'anything relevant here?'])->assertOk();

        $this->assertContains($mine->id, $this->semanticRetriever->lastScope);
        $this->assertNotContains($strangers->id, $this->semanticRetriever->lastScope);
        $ids = array_column($this->analystLlm->evidence['semantically_related_matches'], 'match_id');
        $this->assertSame([$mine->id], $ids);
    }

    public function test_unparsed_matches_are_not_evidence(): void
    {
        $me = $this->asMember();
        GameMatch::factory()->create(['user_id' => $me->id, 'status' => 'queued']);

        $this->postJson('/api/analyst/ask', ['question' => 'Anything to report?'])->assertOk();

        $this->assertSame([], $this->analystLlm->evidence['recent_matches']);
    }

    public function test_question_is_validated_with_codes(): void
    {
        $this->asMember();

        $this->postJson('/api/analyst/ask', [])
            ->assertJsonValidationErrors(['question' => 'analyst.question_required']);

        $this->postJson('/api/analyst/ask', ['question' => 'hi'])
            ->assertJsonValidationErrors(['question' => 'analyst.question_too_short']);

        $this->postJson('/api/analyst/ask', ['question' => str_repeat('x', 501)])
            ->assertJsonValidationErrors(['question' => 'analyst.question_too_long']);
    }

    public function test_degrades_when_no_key_or_provider_fails(): void
    {
        $me = $this->asMember();
        GameMatch::factory()->create(['user_id' => $me->id]);

        // Only the hosted provider is gated on a key — 'ollama' is reachable or it isn't.
        config(['clutch.analyst_provider' => 'claude', 'clutch.anthropic.key' => '']);
        $this->postJson('/api/analyst/ask', ['question' => 'Are we improving?'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'analyst.unavailable');

        config(['clutch.anthropic.key' => 'test-key']);
        $this->analystLlm->failWith = new \RuntimeException('provider down');
        $this->postJson('/api/analyst/ask', ['question' => 'Are we improving?'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'analyst.unavailable');
    }

    // The local provider needs no key, so a missing ANTHROPIC_API_KEY must NOT gate it —
    // an unreachable container surfaces as a throw, which degrades the same way.
    public function test_the_local_provider_is_not_gated_on_an_anthropic_key(): void
    {
        $me = $this->asMember();
        GameMatch::factory()->create(['user_id' => $me->id]);

        config(['clutch.analyst_provider' => 'ollama', 'clutch.anthropic.key' => '']);
        $this->postJson('/api/analyst/ask', ['question' => 'Are we improving?'])
            ->assertOk();

        $this->analystLlm->failWith = new \RuntimeException('ollama down');
        $this->postJson('/api/analyst/ask', ['question' => 'Are we improving?'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'analyst.unavailable');
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/analyst/ask', ['question' => 'Who is our best player?'])
            ->assertUnauthorized();
    }
}
