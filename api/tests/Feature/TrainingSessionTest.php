<?php

namespace Tests\Feature;

use App\Models\Tactic;
use App\Models\Team;
use App\Models\TrainingSession;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The trainings domain doc as assertions: coach schedules (the matrix's point), player
// views but can't manage, roster ⊆ members, tactics visible to the team, codes not
// sentences throughout.
class TrainingSessionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private Team $team;

    private User $coach;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->coach = User::factory()->create();
        $this->player = User::factory()->create();
        $this->team->members()->attach($this->coach, ['role' => 'coach']);
        $this->team->members()->attach($this->player, ['role' => 'player']);
    }

    public function test_a_coach_schedules_a_session_with_tactics_and_roster(): void
    {
        $tactic = Tactic::factory()->create(['user_id' => $this->player->id]); // owned by a member

        Sanctum::actingAs($this->coach);

        $this->postJson('/api/trainings', [
            'team_id' => $this->team->id,
            'title' => 'A-executes + retakes',
            'scheduled_at' => now()->addDay()->toISOString(),
            'duration_minutes' => 90,
            'tactic_ids' => [$tactic->id],
            'player_ids' => [$this->player->id, $this->coach->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'A-executes + retakes')
            ->assertJsonPath('data.tactics.0.id', $tactic->id)
            ->assertJsonCount(2, 'data.players')
            ->assertJsonPath('data.can.manage', true);
    }

    public function test_a_player_cannot_schedule_for_the_team(): void
    {
        Sanctum::actingAs($this->player);

        $this->postJson('/api/trainings', [
            'team_id' => $this->team->id,
            'title' => 'sneaky practice',
            'scheduled_at' => now()->addDay()->toISOString(),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.team_id.0', 'training.invalid_team');
    }

    public function test_the_roster_must_be_team_members(): void
    {
        $outsider = User::factory()->create();

        Sanctum::actingAs($this->coach);

        $this->postJson('/api/trainings', [
            'team_id' => $this->team->id,
            'title' => 'x',
            'scheduled_at' => now()->addDay()->toISOString(),
            'player_ids' => [$outsider->id],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.player_ids.0', 'training.invalid_player');
    }

    public function test_tactics_must_be_visible_to_the_team(): void
    {
        $foreign = Tactic::factory()->create(); // owned by a stranger, shared with nobody

        Sanctum::actingAs($this->coach);

        $this->postJson('/api/trainings', [
            'team_id' => $this->team->id,
            'title' => 'x',
            'scheduled_at' => now()->addDay()->toISOString(),
            'tactic_ids' => [$foreign->id],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.tactic_ids.0', 'training.invalid_tactic');
    }

    public function test_members_view_and_strangers_get_403(): void
    {
        $session = TrainingSession::factory()->create(['team_id' => $this->team->id]);

        Sanctum::actingAs($this->player);
        $this->getJson("/api/trainings/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.can.manage', false);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/trainings/{$session->id}")->assertForbidden();
    }

    public function test_a_player_cannot_cancel_but_a_coach_can(): void
    {
        $session = TrainingSession::factory()->create(['team_id' => $this->team->id]);

        Sanctum::actingAs($this->player);
        $this->patchJson("/api/trainings/{$session->id}", ['canceled' => true])->assertForbidden();

        Sanctum::actingAs($this->coach);
        $this->patchJson("/api/trainings/{$session->id}", ['canceled' => true])->assertOk();

        $this->assertNotNull($session->fresh()->canceled_at);
    }

    public function test_the_list_is_scoped_to_the_callers_teams(): void
    {
        $mine = TrainingSession::factory()->create(['team_id' => $this->team->id]);
        $foreign = TrainingSession::factory()->create();

        Sanctum::actingAs($this->player);

        $ids = collect($this->getJson('/api/trainings')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($foreign->id));
    }
}
