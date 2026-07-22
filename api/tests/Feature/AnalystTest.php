<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Team;
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

        config(['clutch.anthropic.key' => '']);
        $this->postJson('/api/analyst/ask', ['question' => 'Are we improving?'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'analyst.unavailable');

        config(['clutch.anthropic.key' => 'test-key']);
        $this->analystLlm->failWith = new \RuntimeException('provider down');
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
