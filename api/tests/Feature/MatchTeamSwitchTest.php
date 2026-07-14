<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// PATCH /matches/{match}: move between private and a team. Authorized like delete;
// moving TO a team additionally needs uploadMatch there (match.invalid_team otherwise).
class MatchTeamSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_the_uploader_shares_a_private_match_with_their_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => 'igl']);
        $match = GameMatch::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/matches/{$match->id}", ['team_id' => $team->id])
            ->assertOk()
            ->assertJsonPath('data.team.id', $team->id);
    }

    public function test_null_makes_a_shared_match_private_again(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => 'owner']);
        $match = GameMatch::factory()->create(['user_id' => $owner->id, 'team_id' => $team->id]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/matches/{$match->id}", ['team_id' => null])->assertOk();

        $this->assertNull($match->fresh()->team_id);
    }

    public function test_sharing_to_a_team_without_upload_rights_returns_the_code(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => 'player']); // view-only role
        $match = GameMatch::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/matches/{$match->id}", ['team_id' => $team->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.team_id.0', 'match.invalid_team');

        $this->assertNull($match->fresh()->team_id);
    }

    public function test_a_view_only_team_member_cannot_reassign_the_match(): void
    {
        $viewer = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($viewer, ['role' => 'player']);
        $match = GameMatch::factory()->create(['team_id' => $team->id]);

        Sanctum::actingAs($viewer);

        $this->patchJson("/api/matches/{$match->id}", ['team_id' => null])->assertForbidden();
    }
}
