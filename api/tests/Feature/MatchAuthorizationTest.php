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
}
