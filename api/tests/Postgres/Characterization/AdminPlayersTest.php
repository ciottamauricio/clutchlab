<?php

namespace Tests\Postgres\Characterization;

use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\PlayerStats\Scenario;

// GET /admin/players: every player in every match, by name, the pick list for linking accounts.
class AdminPlayersTest extends CharacterizationTestCase
{
    public function test_the_admin_sees_every_player_in_every_match_by_name(): void
    {
        Sanctum::actingAs($this->s->admin);

        $this->assertSame([
            ['steam_id' => Scenario::E1, 'name' => 'Eco One', 'match_count' => 4],
            ['steam_id' => Scenario::P1, 'name' => 'One', 'match_count' => 5],
            ['steam_id' => Scenario::E2, 'name' => 'Tied', 'match_count' => 3],
            ['steam_id' => Scenario::P2, 'name' => 'Two v2', 'match_count' => 3],
            ['steam_id' => Scenario::X1, 'name' => 'Xavier', 'match_count' => 1],
        ], $this->getJson('/api/admin/players')->assertOk()->json('data.players'));
    }

    public function test_anyone_else_is_refused(): void
    {
        Sanctum::actingAs($this->s->owner);

        $this->getJson('/api/admin/players')->assertForbidden();
    }
}
