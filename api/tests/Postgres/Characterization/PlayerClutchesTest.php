<?php

namespace Tests\Postgres\Characterization;

use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\PlayerStats\Scenario;

// GET /players/{steamId}/clutches: the won clutches behind the board's clutch cell.
class PlayerClutchesTest extends CharacterizationTestCase
{
    private function clutches(string $steamId, $user = null): array
    {
        Sanctum::actingAs($user ?? $this->s->owner);

        return $this->getJson("/api/players/{$steamId}/clutches")->assertOk()->json('data.clutches');
    }

    public function test_each_won_clutch_comes_with_its_match_and_kills(): void
    {
        $this->assertSame([[
            'match_id' => $this->s->id('m1'), 'map' => 'de_mirage', 'demo' => 'm1.dem', 'tick_rate' => 64, 'round' => 21, 'size' => 2,
            'killer_name' => 'One', 'kills' => [$this->kill('Eco One', 'ak47', 21100, hitgroups: ['head' => 91, 'chest' => 20]), $this->kill('Tied', 'ak47', 21200)],
        ]], $this->clutches(Scenario::P1));

        $this->assertSame([[
            'match_id' => $this->s->id('m2'), 'map' => 'de_inferno', 'demo' => 'm2.dem', 'tick_rate' => 64, 'round' => 20, 'size' => 3,
            'killer_name' => 'Two', 'kills' => [$this->kill('Eco One', 'ak47', 20100, side: 'T'), $this->kill('Tied', 'ak47', 20200, side: 'T')],
        ]], $this->clutches(Scenario::P2));
    }

    public function test_the_list_spans_every_visible_match_whatever_the_board_counted(): void
    {
        // The board from 2026-11-01 counts no clutch for p2 (m2 was played in October); the cell
        // opens this list, which still shows m2's.
        Sanctum::actingAs($this->s->owner);
        $board = $this->getJson('/api/teams/'.$this->s->team->id.'/stats?from=2026-11-01')->json('data.players');

        $this->assertSame(0, collect($board)->firstWhere('steam_id', Scenario::P2)['clutches']);
        $this->assertCount(1, $this->clutches(Scenario::P2));
    }

    public function test_clutches_in_matches_the_caller_cant_see_are_left_out(): void
    {
        $this->assertSame([], $this->clutches(Scenario::P1, $this->s->outsider));
    }
}
