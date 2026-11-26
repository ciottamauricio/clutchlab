<?php

namespace Tests\Postgres\Characterization;

use Laravel\Sanctum\Sanctum;

// GET /matches/{match}/team-stats: the match page's CT-vs-T comparison.
class MatchSidesTest extends CharacterizationTestCase
{
    private function sides(string $match): array
    {
        Sanctum::actingAs($this->s->owner);

        return $this->getJson('/api/matches/'.$this->s->id($match).'/team-stats')->assertOk()->json('data');
    }

    public function test_each_side_sums_its_scoreboard_and_counts_its_openings_and_clutches(): void
    {
        $this->assertSame([
            'ct' => ['side' => 'CT', 'name' => 'Alpha', 'score' => 13, 'kills' => 25, 'deaths' => 3, 'assists' => 0, 'hs_pct' => 40, 'opening_kills' => 8, 'opening_deaths' => 1, 'clutches' => 1],
            't' => ['side' => 'T', 'name' => 'Terrorists', 'score' => 9, 'kills' => 1, 'deaths' => 23, 'assists' => 0, 'hs_pct' => 0, 'opening_kills' => 1, 'opening_deaths' => 8, 'clutches' => 0],
        ], $this->sides('m1'));
    }

    public function test_a_sides_opening_deaths_are_the_other_sides_opening_kills(): void
    {
        // In m1, CT players died first in three rounds (21; 23, a team kill; 24, a self-kill), but
        // only round 21's opener was T's, so CT's opening deaths read 1.
        $this->assertSame(1, $this->sides('m1')['ct']['opening_deaths']);
    }

    public function test_an_unparsed_match_compares_two_empty_sides(): void
    {
        $empty = ['kills' => 0, 'deaths' => 0, 'assists' => 0, 'hs_pct' => 0, 'opening_kills' => 0, 'opening_deaths' => 0, 'clutches' => 0];

        $this->assertSame([
            'ct' => ['side' => 'CT', 'name' => 'Counter-Terrorists', 'score' => 0, ...$empty],
            't' => ['side' => 'T', 'name' => 'Terrorists', 'score' => 0, ...$empty],
        ], $this->sides('m4'));
    }

    public function test_a_match_the_caller_cant_see_is_refused(): void
    {
        Sanctum::actingAs($this->s->owner);

        $this->getJson('/api/matches/'.$this->s->id('m5').'/team-stats')->assertForbidden();
    }
}
