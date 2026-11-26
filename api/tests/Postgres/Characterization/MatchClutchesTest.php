<?php

namespace Tests\Postgres\Characterization;

use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\PlayerStats\Scenario;

// GET /matches/{match}/clutches: one match's won clutches, by round.
class MatchClutchesTest extends CharacterizationTestCase
{
    private function clutches(string $match): array
    {
        Sanctum::actingAs($this->s->owner);

        return $this->getJson('/api/matches/'.$this->s->id($match).'/clutches')->assertOk()->json('data');
    }

    public function test_each_round_won_in_a_clutch_lists_the_clutchers_kills(): void
    {
        $this->assertSame([
            'tick_rate' => 64, 'demo' => 'm1.dem',
            'clutches' => [[
                'round' => 21, 'size' => 2, 'killer_name' => 'One', 'killer_steam_id' => Scenario::P1,
                'kills' => [$this->kill('Eco One', 'ak47', 21100, hitgroups: ['head' => 91, 'chest' => 20]), $this->kill('Tied', 'ak47', 21200)],
            ]],
        ], $this->clutches('m1'));
    }

    public function test_the_clutch_size_is_the_rounds(): void
    {
        $this->assertSame([[20, 3]], array_map(fn ($c) => [$c['round'], $c['size']], $this->clutches('m2')['clutches']));
    }

    public function test_a_match_with_no_clutch_lists_none(): void
    {
        $this->assertSame([], $this->clutches('m3')['clutches']);
    }

    public function test_a_match_the_caller_cant_see_is_refused(): void
    {
        Sanctum::actingAs($this->s->owner);

        $this->getJson('/api/matches/'.$this->s->id('m5').'/clutches')->assertForbidden();
    }
}
