<?php

namespace Tests\Postgres\Characterization;

use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\PlayerStats\Scenario;

// GET /players: every player in the caller's visible matches, the roster picker's list.
class PlayersTest extends CharacterizationTestCase
{
    private function players($user): array
    {
        Sanctum::actingAs($user);

        return $this->getJson('/api/players')->assertOk()->json('data.players');
    }

    public function test_the_list_counts_each_players_visible_matches_and_names_them_by_their_latest(): void
    {
        // Most-played first, equal counts by Steam ID (Q6).
        $this->assertSame([
            ['steam_id' => Scenario::P1, 'name' => 'One', 'match_count' => 4],
            ['steam_id' => Scenario::E1, 'name' => 'Eco One', 'match_count' => 4],
            ['steam_id' => Scenario::P2, 'name' => 'Two v2', 'match_count' => 3],
            ['steam_id' => Scenario::E2, 'name' => 'Tied', 'match_count' => 3],
        ], $this->players($this->s->owner));
    }

    public function test_another_caller_sees_only_their_own_matches_players(): void
    {
        $this->assertSame([
            ['steam_id' => Scenario::P1, 'name' => 'One', 'match_count' => 1],
            ['steam_id' => Scenario::X1, 'name' => 'Xavier', 'match_count' => 1],
        ], $this->players($this->s->outsider));
    }
}
