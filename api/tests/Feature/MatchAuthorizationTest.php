<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// The authorization matrix from api/docs/domains/matches.md, as assertions:
// view for uploader + team members, writes only for uploader or upload-capable
// roles, 403 (never 404) for outsiders.
class MatchAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // grants live in the DB (PermissionSeeder)

    private function teamWith(User $member, string $role): Team
    {
        $team = Team::factory()->create();
        $team->members()->attach($member, ['role' => $role]);

        return $team;
    }

    public function test_a_stranger_gets_403_not_404_on_a_private_match(): void
    {
        $match = GameMatch::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/matches/{$match->id}")->assertForbidden();
        $this->deleteJson("/api/matches/{$match->id}")->assertForbidden();
    }

    public function test_the_uploader_sees_their_private_match(): void
    {
        $match = GameMatch::factory()->create();
        Sanctum::actingAs($match->owner);

        $this->getJson("/api/matches/{$match->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $match->id);
    }

    public function test_a_team_member_can_view_a_shared_match_but_a_player_cannot_delete_it(): void
    {
        $viewer = User::factory()->create();
        $team = $this->teamWith($viewer, 'player');
        $match = GameMatch::factory()->create(['team_id' => $team->id]);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/matches/{$match->id}")->assertOk();
        $this->deleteJson("/api/matches/{$match->id}")->assertForbidden();
    }

    public function test_an_igl_can_delete_a_team_match_they_did_not_upload(): void
    {
        $igl = User::factory()->create();
        $team = $this->teamWith($igl, 'igl');
        $match = GameMatch::factory()->create(['team_id' => $team->id]);

        Sanctum::actingAs($igl);

        $this->deleteJson("/api/matches/{$match->id}")->assertNoContent();
        $this->assertDatabaseMissing('matches', ['id' => $match->id]);
    }

    public function test_the_list_shows_own_and_team_matches_only(): void
    {
        $me = User::factory()->create();
        $team = $this->teamWith($me, 'player');

        $mine = GameMatch::factory()->create(['user_id' => $me->id]);
        $teams = GameMatch::factory()->create(['team_id' => $team->id]);
        $foreign = GameMatch::factory()->create();

        Sanctum::actingAs($me);

        $ids = collect($this->getJson('/api/matches')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertTrue($ids->contains($teams->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_the_list_is_ordered_by_most_recently_played(): void
    {
        $me = User::factory()->create();

        $old = GameMatch::factory()->create(['user_id' => $me->id, 'played_at' => '2026-06-01 20:00:00']);
        $recent = GameMatch::factory()->create(['user_id' => $me->id, 'played_at' => '2026-07-10 20:00:00']);
        $undated = GameMatch::factory()->create(['user_id' => $me->id, 'played_at' => null]);

        Sanctum::actingAs($me);

        $ids = collect($this->getJson('/api/matches')->assertOk()->json('data'))->pluck('id');

        // Most recently played on top; the undated match sinks to the bottom.
        $this->assertSame([$recent->id, $old->id, $undated->id], $ids->all());
    }

    public function test_the_list_filters_by_the_month_a_match_was_played(): void
    {
        $me = User::factory()->create();

        $june = GameMatch::factory()->create(['user_id' => $me->id, 'played_at' => '2026-06-15 21:00:00']);
        $july = GameMatch::factory()->create(['user_id' => $me->id, 'played_at' => '2026-07-02 21:00:00']);
        $undated = GameMatch::factory()->create(['user_id' => $me->id, 'played_at' => null]);

        Sanctum::actingAs($me);

        $ids = collect($this->getJson('/api/matches?month=2026-06')->assertOk()->json('data'))->pluck('id');

        // Only June's match — July and the undated one stay out of a month view.
        $this->assertSame([$june->id], $ids->all());
        $this->assertFalse($ids->contains($july->id));
        $this->assertFalse($ids->contains($undated->id));

        $this->getJson('/api/matches?month=july')
            ->assertUnprocessable()
            ->assertJsonPath('errors.month.0', 'match.invalid_month');
    }

    public function test_the_list_filters_by_the_day_a_match_was_played(): void
    {
        $me = User::factory()->create();

        $thatEvening = GameMatch::factory()->create(['user_id' => $me->id, 'played_at' => '2026-07-09 23:49:00']);
        $earlyThatDay = GameMatch::factory()->create(['user_id' => $me->id, 'played_at' => '2026-07-09 00:15:00']);
        $dayBefore = GameMatch::factory()->create(['user_id' => $me->id, 'played_at' => '2026-07-08 22:00:00']);

        Sanctum::actingAs($me);

        $ids = collect($this->getJson('/api/matches?day=2026-07-09')->assertOk()->json('data'))->pluck('id');

        // The whole day, midnight to midnight — and nothing from adjacent days.
        $this->assertSame([$thatEvening->id, $earlyThatDay->id], $ids->all());
        $this->assertFalse($ids->contains($dayBefore->id));

        $this->getJson('/api/matches?day=yesterday')
            ->assertUnprocessable()
            ->assertJsonPath('errors.day.0', 'match.invalid_day');
    }

    public function test_the_list_paginates_ten_per_page(): void
    {
        $me = User::factory()->create();

        foreach (range(1, 12) as $day) {
            GameMatch::factory()->create([
                'user_id' => $me->id,
                'played_at' => sprintf('2026-07-%02d 21:00:00', $day),
            ]);
        }

        Sanctum::actingAs($me);

        $first = $this->getJson('/api/matches')->assertOk();
        $first->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 2);

        // Page 2 carries the oldest two (the list is played-desc).
        $second = $this->getJson('/api/matches?page=2')->assertOk();
        $second->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }
}
