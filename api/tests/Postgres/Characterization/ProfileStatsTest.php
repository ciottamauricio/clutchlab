<?php

namespace Tests\Postgres\Characterization;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\PlayerStats\Scenario;

// GET /profile/stats: the caller's own line over every match they can see.
class ProfileStatsTest extends CharacterizationTestCase
{
    public function test_the_profile_covers_every_visible_match(): void
    {
        Sanctum::actingAs($this->s->owner);

        $this->assertSame([
            // m1, m2, m3 and the private m6; never the other team's m5.
            'stats' => ['games' => 4, 'kills' => 26, 'deaths' => 43, 'kd' => 0.6, 'hs_pct' => 38, 'entry_kills' => 8, 'first_deaths' => 1, 'clutches' => 1],
            // Newest played (or uploaded, when undated) last; dates as Postgres writes them.
            'recent' => [
                ['match_id' => $this->s->id('m2'), 'map' => 'de_inferno', 'played_at' => '2026-10-20 18:00:00', 'duration_seconds' => 1800, 'kills' => 0, 'deaths' => 42, 'kd' => 0, 'won' => null],
                ['match_id' => $this->s->id('m1'), 'map' => 'de_mirage', 'played_at' => '2026-11-05 20:00:00', 'duration_seconds' => 2000, 'kills' => 23, 'deaths' => 0, 'kd' => 23, 'won' => true],
                ['match_id' => $this->s->id('m3'), 'map' => 'de_mirage', 'played_at' => null, 'duration_seconds' => null, 'kills' => 1, 'deaths' => 1, 'kd' => 1, 'won' => false],
                ['match_id' => $this->s->id('m6'), 'map' => 'de_nuke', 'played_at' => '2026-11-09 12:00:00', 'duration_seconds' => null, 'kills' => 2, 'deaths' => 0, 'kd' => 2, 'won' => true],
            ],
            'weapons' => [['weapon' => 'ak47', 'kills' => 19], ['weapon' => 'awp', 'kills' => 7]],
            // tick_rate and hitgroups are the raw columns here, as Postgres writes them: a string,
            // and jsonb's text. The other answers give a number and an object.
            'clutches' => [[
                'match_id' => $this->s->id('m1'), 'round' => 21, 'map' => 'de_mirage', 'played_at' => '2026-11-05 20:00:00',
                'tick_rate' => '64', 'demo' => 'm1.dem', 'size' => 2,
                'kills' => [['victim_name' => 'Eco One', 'weapon' => 'ak47', 'side' => 'CT', 'headshot' => false, 'tick' => 21100, 'hitgroups' => '{"head": 91, "chest": 20}'], $this->kill('Tied', 'ak47', 21200)],
            ]],
        ], $this->getJson('/api/profile/stats')->assertOk()->json('data'));
    }

    public function test_no_linked_steam_id_gives_no_stats(): void
    {
        Sanctum::actingAs($this->s->outsider);

        $this->assertSame(['stats' => null, 'recent' => [], 'weapons' => [], 'clutches' => []], $this->getJson('/api/profile/stats')->assertOk()->json('data'));
    }

    public function test_a_steam_id_with_no_visible_match_gives_zeroes(): void
    {
        Sanctum::actingAs(User::factory()->create(['steam_id' => Scenario::E1])); // played, but in no match this user can see

        $this->assertSame([
            'stats' => ['games' => 0, 'kills' => 0, 'deaths' => 0, 'kd' => 0, 'hs_pct' => 0, 'entry_kills' => 0, 'first_deaths' => 0, 'clutches' => 0],
            'recent' => [], 'weapons' => [], 'clutches' => [],
        ], $this->getJson('/api/profile/stats')->assertOk()->json('data'));
    }
}
