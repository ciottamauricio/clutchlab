<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Domain rule 11: the list marks the viewer's result. Their own seat decides the side;
// otherwise a 4+ same-side stack from ONE of their teams counts as "us". Anything else
// stays unmarked (null) — the archive doesn't guess.
class MatchViewerResultTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function seat(GameMatch $match, string $steamId, string $side): void
    {
        $match->playerStats()->forceCreate([
            'steam_id' => $steamId,
            'name' => 'player-'.$steamId,
            'team_side' => $side,
        ]);
    }

    /** @return array<int, ?string> match id => viewer_result */
    private function listResults(User $user): array
    {
        Sanctum::actingAs($user);

        return collect($this->getJson('/api/matches')->assertOk()->json('data'))
            ->pluck('viewer_result', 'id')->all();
    }

    public function test_the_viewers_own_seat_decides_win_loss_and_draw(): void
    {
        $me = User::factory()->create(['steam_id' => '76561198000000001']);

        $win = GameMatch::factory()->create(['user_id' => $me->id, 'score_ct' => 13, 'score_t' => 9]);
        $loss = GameMatch::factory()->create(['user_id' => $me->id, 'score_ct' => 7, 'score_t' => 13]);
        $draw = GameMatch::factory()->create(['user_id' => $me->id, 'score_ct' => 12, 'score_t' => 12]);

        foreach ([$win, $loss, $draw] as $match) {
            $this->seat($match, $me->steam_id, 'CT');
        }

        $results = $this->listResults($me);

        $this->assertSame('win', $results[$win->id]);
        $this->assertSame('loss', $results[$loss->id]);
        $this->assertSame('draw', $results[$draw->id]);
    }

    public function test_a_four_player_stack_from_one_team_counts_as_us(): void
    {
        $me = User::factory()->create(); // never played — no steam_id
        $team = Team::factory()->create();
        $team->members()->attach($me, ['role' => 'player']);

        $mates = User::factory()->count(4)->sequence(
            fn ($seq) => ['steam_id' => '7656119800000010'.$seq->index],
        )->create();
        foreach ($mates as $mate) {
            $team->members()->attach($mate, ['role' => 'player']);
        }

        // Four teammates on T; T loses 9-13.
        $stack = GameMatch::factory()->create(['user_id' => $me->id, 'score_ct' => 13, 'score_t' => 9]);
        foreach ($mates as $mate) {
            $this->seat($stack, $mate->steam_id, 'T');
        }

        // Only three on the same side (the fourth on the other) — not a stack.
        $split = GameMatch::factory()->create(['user_id' => $me->id, 'score_ct' => 13, 'score_t' => 9]);
        foreach ($mates->take(3) as $mate) {
            $this->seat($split, $mate->steam_id, 'T');
        }
        $this->seat($split, $mates->last()->steam_id, 'CT');

        // Nobody relevant played at all.
        $strangers = GameMatch::factory()->create(['user_id' => $me->id, 'score_ct' => 13, 'score_t' => 9]);

        $results = $this->listResults($me);

        $this->assertSame('loss', $results[$stack->id]);
        $this->assertNull($results[$split->id]);
        $this->assertNull($results[$strangers->id]);
    }
}
