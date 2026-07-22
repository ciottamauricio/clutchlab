<?php

namespace Tests\Feature;

use App\Models\Tactic;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Tactics authorization after the team-scope split (tactics.create/edit/delete):
// - a PRIVATE tactic is the creator's alone;
// - a TEAM tactic is gated by the team-scope abilities, resolved against its team;
// - the creator always keeps control of their own.
// Defaults (seeded): owner/igl/coach/player create+edit; owner/igl/coach delete; player not.
class TacticAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function teamWith(User $u, string $role): Team
    {
        $team = Team::factory()->create();
        $team->members()->attach($u, ['role' => $role]);

        return $team;
    }

    public function test_a_private_tactic_needs_no_team_and_anyone_may_make_one_for_themselves(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/tactics', ['name' => 'my draft'])
            ->assertCreated()
            ->assertJsonPath('data.team_id', null);
    }

    public function test_creating_into_a_team_needs_tactics_create_on_it(): void
    {
        $igl = User::factory()->create();
        $team = $this->teamWith($igl, 'igl'); // igl has tactics.create by default

        Sanctum::actingAs($igl);
        $this->postJson('/api/tactics', ['name' => 'A exec', 'team_id' => $team->id])
            ->assertCreated()
            ->assertJsonPath('data.team_id', $team->id);
    }

    public function test_creating_into_a_team_you_are_not_on_is_rejected(): void
    {
        $outsider = User::factory()->create();
        $team = Team::factory()->create(); // outsider is not a member

        Sanctum::actingAs($outsider);
        $this->postJson('/api/tactics', ['name' => 'x', 'team_id' => $team->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.team_id.0', 'tactic.invalid_team');
    }

    public function test_a_teammate_with_edit_may_edit_a_shared_board_a_stranger_cannot(): void
    {
        $owner = User::factory()->create();
        $team = $this->teamWith($owner, 'owner');
        $mate = User::factory()->create();
        $team->members()->attach($mate, ['role' => 'player']); // player has tactics.edit
        $tactic = Tactic::factory()->create(['user_id' => $owner->id, 'team_id' => $team->id]);

        Sanctum::actingAs($mate);
        $this->putJson("/api/tactics/{$tactic->id}", ['name' => 'edited', 'board' => ['pieces' => []]])
            ->assertOk()
            ->assertJsonPath('data.name', 'edited');

        // A stranger — not on the team — can't even see it, let alone edit.
        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/tactics/{$tactic->id}", ['name' => 'hijack', 'board' => ['pieces' => []]])
            ->assertForbidden();
    }

    public function test_delete_follows_the_team_ability_not_mere_membership(): void
    {
        $owner = User::factory()->create();
        $team = $this->teamWith($owner, 'owner');
        $player = User::factory()->create();
        $team->members()->attach($player, ['role' => 'player']); // player: edit yes, delete no
        $tactic = Tactic::factory()->create(['user_id' => $owner->id, 'team_id' => $team->id]);

        // The player is on the team and can edit, but delete is not theirs.
        Sanctum::actingAs($player);
        $this->deleteJson("/api/tactics/{$tactic->id}")->assertForbidden();

        // The owner (creator, and owner role) deletes fine.
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/tactics/{$tactic->id}")->assertNoContent();
    }

    public function test_the_creator_controls_their_own_tactic_regardless_of_team_role(): void
    {
        // A player creates a tactic and shares it; being the creator, they can still
        // delete it even though the player role lacks tactics.delete.
        $player = User::factory()->create();
        $team = $this->teamWith($player, 'player');
        $tactic = Tactic::factory()->create(['user_id' => $player->id, 'team_id' => $team->id]);

        Sanctum::actingAs($player);
        $this->deleteJson("/api/tactics/{$tactic->id}")->assertNoContent();
    }

    public function test_a_private_tactic_is_invisible_and_untouchable_to_others(): void
    {
        $owner = User::factory()->create();
        $private = Tactic::factory()->create(['user_id' => $owner->id, 'team_id' => null]);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/tactics/{$private->id}")->assertForbidden();
        $this->putJson("/api/tactics/{$private->id}", ['name' => 'x', 'board' => ['pieces' => []]])->assertForbidden();
        $this->deleteJson("/api/tactics/{$private->id}")->assertForbidden();
    }

    public function test_the_list_exposes_edit_and_delete_can_flags(): void
    {
        $owner = User::factory()->create();
        $team = $this->teamWith($owner, 'owner');
        Tactic::factory()->create(['user_id' => $owner->id, 'team_id' => $team->id]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/tactics')
            ->assertOk()
            ->assertJsonPath('data.0.can.edit', true)
            ->assertJsonPath('data.0.can.delete', true)
            ->assertJsonPath('data.0.can.share', true);
    }
}
