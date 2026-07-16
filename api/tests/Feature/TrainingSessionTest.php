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

        // Scheduling announces the fact on the event channel (contract: additive-only).
        $this->assertCount(1, $this->eventBus->published);
        [$event, $v, $payload] = $this->eventBus->published[0];
        $this->assertSame('training.scheduled', $event);
        $this->assertSame(1, $v);
        $this->assertSame($this->team->name, $payload['team']);
        $this->assertSame(2, $payload['players']);
    }

    public function test_a_coach_attaches_homework_while_scheduling(): void
    {
        $tactic = Tactic::factory()->create(['user_id' => $this->player->id]);

        Sanctum::actingAs($this->coach);

        $this->postJson('/api/trainings', [
            'team_id' => $this->team->id,
            'title' => 'A-executes + retakes',
            'scheduled_at' => now()->addDay()->toISOString(),
            'tactic_ids' => [$tactic->id],
            'player_ids' => [$this->player->id, $this->coach->id],
            'assignments' => [
                ['user_id' => $this->player->id, 'map' => 'de_overpass', 'nade_type' => 'smoke'],
                ['user_id' => $this->player->id, 'map' => 'de_overpass', 'nade_type' => 'flashbang'],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.assignments')
            ->assertJsonPath('data.assignments.0.map', 'de_overpass');
    }

    public function test_homework_on_create_must_target_a_rostered_player(): void
    {
        $tactic = Tactic::factory()->create(['user_id' => $this->player->id]);

        Sanctum::actingAs($this->coach);

        // The coach is a team member but was left off this session's roster.
        $this->postJson('/api/trainings', [
            'team_id' => $this->team->id,
            'title' => 'x',
            'scheduled_at' => now()->addDay()->toISOString(),
            'tactic_ids' => [$tactic->id],
            'player_ids' => [$this->player->id],
            'assignments' => [
                ['user_id' => $this->coach->id, 'map' => 'de_overpass', 'nade_type' => 'smoke'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignments.0.user_id' => 'training.invalid_assignee']);
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

    public function test_a_session_needs_at_least_one_tactic_and_one_player(): void
    {
        $tactic = Tactic::factory()->create(['user_id' => $this->player->id]);

        Sanctum::actingAs($this->coach);

        // No roster.
        $this->postJson('/api/trainings', [
            'team_id' => $this->team->id,
            'title' => 'empty',
            'scheduled_at' => now()->addDay()->toISOString(),
            'tactic_ids' => [$tactic->id],
            'player_ids' => [],
        ])->assertUnprocessable()->assertJsonPath('errors.player_ids.0', 'training.invalid_player');

        // No tactics.
        $this->postJson('/api/trainings', [
            'team_id' => $this->team->id,
            'title' => 'empty',
            'scheduled_at' => now()->addDay()->toISOString(),
            'tactic_ids' => [],
            'player_ids' => [$this->player->id],
        ])->assertUnprocessable()->assertJsonPath('errors.tactic_ids.0', 'training.invalid_tactic');
    }

    public function test_editing_cannot_empty_the_roster_or_tactics(): void
    {
        $session = $this->sessionWithRoster();

        Sanctum::actingAs($this->coach);

        $this->patchJson("/api/trainings/{$session->id}", ['player_ids' => []])
            ->assertUnprocessable()->assertJsonPath('errors.player_ids.0', 'training.invalid_player');
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

    private function sessionWithRoster(): TrainingSession
    {
        $session = TrainingSession::factory()->create(['team_id' => $this->team->id]);
        $session->players()->attach([$this->player->id, $this->coach->id]);

        return $session;
    }

    public function test_an_invited_player_answers_their_own_rsvp(): void
    {
        $session = $this->sessionWithRoster();

        Sanctum::actingAs($this->player);

        $this->patchJson("/api/trainings/{$session->id}/rsvp", ['going' => true])
            ->assertOk()
            ->assertJsonPath('data.players', fn ($players) => collect($players)->firstWhere('id', $this->player->id)['rsvp'] === 'in');

        $this->patchJson("/api/trainings/{$session->id}/rsvp", ['going' => false])->assertOk();
        $this->assertSame('out', $session->players()->whereKey($this->player->id)->first()->pivot->rsvp);
    }

    public function test_a_member_off_the_roster_cannot_rsvp(): void
    {
        $session = TrainingSession::factory()->create(['team_id' => $this->team->id]);

        Sanctum::actingAs($this->player); // team member, but not invited

        $this->patchJson("/api/trainings/{$session->id}/rsvp", ['going' => true])->assertForbidden();
    }

    public function test_even_a_master_admin_off_the_roster_cannot_rsvp(): void
    {
        $session = $this->sessionWithRoster();
        $admin = User::factory()->create(['role' => 'admin']); // Gate::before bypasses everything else

        Sanctum::actingAs($admin);

        // The admin is not on this roster — there's no invite to answer. `can.rsvp` is false…
        $this->getJson("/api/trainings/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.can.rsvp', false);

        // …and the endpoint itself refuses, despite the admin's blanket policy bypass.
        $this->patchJson("/api/trainings/{$session->id}/rsvp", ['going' => true])->assertForbidden();
    }

    public function test_a_coach_assigns_homework_to_a_roster_player(): void
    {
        $session = $this->sessionWithRoster();

        Sanctum::actingAs($this->coach);

        $this->postJson("/api/trainings/{$session->id}/assignments", [
            'user_id' => $this->player->id, 'map' => 'de_overpass', 'nade_type' => 'smoke',
        ])
            ->assertCreated()
            ->assertJsonPath('data.map', 'de_overpass')
            ->assertJsonPath('data.done_at', null);

        // Idempotent: same homework twice is a no-op, not an error or a duplicate.
        $this->postJson("/api/trainings/{$session->id}/assignments", [
            'user_id' => $this->player->id, 'map' => 'de_overpass', 'nade_type' => 'smoke',
        ])->assertCreated();
        $this->assertSame(1, $session->assignments()->count());
    }

    public function test_homework_needs_a_roster_assignee_and_a_known_nade_type(): void
    {
        $session = $this->sessionWithRoster();
        $offRoster = User::factory()->create();
        $this->team->members()->attach($offRoster, ['role' => 'player']);

        Sanctum::actingAs($this->coach);

        $this->postJson("/api/trainings/{$session->id}/assignments", [
            'user_id' => $offRoster->id, 'map' => 'de_overpass', 'nade_type' => 'smoke',
        ])->assertUnprocessable()->assertJsonPath('errors.user_id.0', 'training.invalid_assignee');

        $this->postJson("/api/trainings/{$session->id}/assignments", [
            'user_id' => $this->player->id, 'map' => 'de_overpass', 'nade_type' => 'decoy',
        ])->assertUnprocessable()->assertJsonPath('errors.nade_type.0', 'training.invalid_nade');
    }

    public function test_only_the_assignee_marks_homework_done_not_even_the_coach(): void
    {
        $session = $this->sessionWithRoster();
        $assignment = $session->assignments()->create([
            'user_id' => $this->player->id, 'map' => 'de_overpass', 'nade_type' => 'flashbang',
        ]);

        Sanctum::actingAs($this->coach);
        $this->patchJson("/api/trainings/{$session->id}/assignments/{$assignment->id}", ['done' => true])
            ->assertForbidden();

        Sanctum::actingAs($this->player);
        $this->patchJson("/api/trainings/{$session->id}/assignments/{$assignment->id}", ['done' => true])
            ->assertOk();
        $this->assertNotNull($assignment->fresh()->done_at);
    }
}
