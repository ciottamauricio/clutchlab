<?php

namespace Tests\Postgres\Characterization;

use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\PlayerStats\Scenario;

// GET /awards/kills: the kills behind one player's row on one award, by match and tick.
class AwardKillsTest extends CharacterizationTestCase
{
    private function kills(string $key, string $steamId, ?string $map = null): array
    {
        Sanctum::actingAs($this->s->owner);
        $query = ['key' => $key, 'steam_id' => $steamId] + ($map ? ['map' => $map] : []);

        return $this->getJson('/api/awards/kills?'.http_build_query($query))->assertOk()->json('data.kills');
    }

    /** @return list<array{0: int, 1: int}> each kill's match and round */
    private function where(array $kills): array
    {
        return array_map(fn ($k) => [$k['match_id'], $k['round']], $kills);
    }

    private function rounds(string $match, array $rounds): array
    {
        return array_map(fn ($r) => [$this->s->id($match), $r], $rounds);
    }

    public function test_each_kill_carries_its_match_for_the_demo_jump(): void
    {
        $this->assertSame([
            ['match_id' => $this->s->id('m1'), 'map' => 'de_mirage', 'demo' => 'm1.dem', 'tick_rate' => 64, 'round' => 1, 'killer_name' => 'One',
                'victim_name' => 'Tied', 'weapon' => 'ak47', 'side' => 'CT', 'headshot' => false, 'tick' => 1000, 'hitgroups' => ['legs' => 50, 'chest' => 40]],
            ['match_id' => $this->s->id('m1'), 'map' => 'de_mirage', 'demo' => 'm1.dem', 'tick_rate' => 64, 'round' => 2, 'killer_name' => 'One',
                'victim_name' => 'Tied', 'weapon' => 'ak47', 'side' => 'CT', 'headshot' => true, 'tick' => 2000, 'hitgroups' => ['head' => 30, 'legs' => 30]],
        ], $this->kills('legshots', Scenario::P1));
    }

    public function test_first_blood_lists_the_players_opening_deaths(): void
    {
        $this->assertSame(
            [...$this->rounds('m1', [21, 23, 24]), ...$this->rounds('m2', range(1, 10))],
            $this->where($this->kills('first_blood', Scenario::P2)),
        );
    }

    public function test_the_other_awards_list_the_players_own_kills(): void
    {
        $this->assertSame([...$this->rounds('m1', [21]), ...$this->rounds('m2', range(1, 17)), ...$this->rounds('m3', [1])], $this->where($this->kills('eco', Scenario::E1)));
        $this->assertSame($this->rounds('m1', [21, 21]), $this->where($this->kills('clutches', Scenario::P1)));
        $this->assertSame($this->rounds('m1', range(2, 20, 2)), $this->where($this->kills('headshot', Scenario::P1)));
        $this->assertSame(
            [...$this->rounds('m1', [...range(1, 15), 21, 21, 23]), ...$this->rounds('m3', [1])],
            $this->where($this->kills('one_trick', Scenario::P1)),
        );
    }

    public function test_a_tied_top_weapon_lists_the_kills_of_one_of_them(): void
    {
        $kills = $this->kills('one_trick', Scenario::E2);

        $this->assertCount(11, $kills);
        $this->assertCount(1, array_unique(array_column($kills, 'weapon')));
        $this->assertContains($kills[0]['weapon'], ['awp', 'm4a1']);
    }

    public function test_a_map_narrows_the_kills(): void
    {
        $this->assertSame($this->rounds('m2', range(1, 10)), $this->where($this->kills('first_blood', Scenario::P2, 'de_inferno')));
    }

    public function test_an_unknown_award_or_an_empty_steam_id_lists_nothing(): void
    {
        $this->assertSame([], $this->kills('aces', Scenario::P1));
        $this->assertSame([], $this->kills('one_trick', ''));
    }

    public function test_the_nameless_one_trick_leader_cant_be_drilled_into(): void
    {
        // The award lists the kills with no killer under an empty Steam ID; asking for its kills
        // with that id is "no player", so the dialog is empty.
        $this->assertSame([], $this->kills('one_trick', Scenario::NO_KILLER));
    }
}
