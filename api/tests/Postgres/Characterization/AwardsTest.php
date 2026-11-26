<?php

namespace Tests\Postgres\Characterization;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\PlayerStats\Scenario;

// GET /awards: one leaderboard per award over every visible match, narrowed by a team and a map.
class AwardsTest extends CharacterizationTestCase
{
    private const LABELS = [
        'legshots' => ['🦵', 'Leg-shot leader', 'Kills where the legs took the most damage'],
        'first_blood' => ['💀', 'First blood', 'Died first in the round the most'],
        'headshot' => ['🎯', 'Headshot machine', 'Highest headshot rate (min 20 kills)'],
        'eco' => ['🔫', 'Eco bully', 'Most kills with a pistol or knife'],
        'clutches' => ['🏆', 'Clutch god', 'Most 1vN rounds won'],
        'one_trick' => ['🎼', 'One-trick', 'Highest share of kills from a single weapon (min 20 kills)'],
    ];

    private function awards(array $query = []): array
    {
        Sanctum::actingAs($this->s->owner);
        $awards = $this->getJson('/api/awards'.($query ? '?'.http_build_query($query) : ''))->assertOk()->json('data.awards');

        return array_map(function (array $award) {
            $leaders = $award['leaders'];
            // e2's 11 awp and 11 m4a1 kills tie; Postgres picked either until Q6 said awp.
            foreach ($leaders as &$l) {
                if ($award['key'] === 'one_trick' && $l['steam_id'] === Scenario::E2) {
                    $this->assertContains($l['sub'], ['awp', 'm4a1']);
                    $l['sub'] = 'awp';
                }
            }
            $award['leaders'] = $award['key'] === 'one_trick' ? $leaders : $this->tiesBySteamId($leaders, 'value');

            return $award;
        }, $awards);
    }

    private function award(string $key, array $leaders): array
    {
        [$emoji, $label, $hint] = self::LABELS[$key];

        return ['key' => $key, 'emoji' => $emoji, 'label' => $label, 'hint' => $hint, 'leaders' => array_map(
            fn ($l) => ['steam_id' => $l[0], 'name' => $l[1], 'value' => $l[2], 'sub' => $l[3] ?? null], $leaders,
        )];
    }

    public function test_every_award_over_the_visible_matches(): void
    {
        $this->assertSame([
            $this->award('legshots', [[Scenario::P1, 'One', '2']]),
            $this->award('first_blood', [[Scenario::P2, 'Two v2', '13'], [Scenario::E2, 'Tied', '6'], [Scenario::E1, 'Eco One', '3'], [Scenario::P1, 'One', '1']]),
            // e1 has 19 kills, one under the floor; e2 and the bomb have no headshot.
            $this->award('headshot', [[Scenario::P1, 'One', '38%', '26 kills']]),
            $this->award('eco', [[Scenario::E1, 'Eco One', '19'], [Scenario::P2, 'Two v2', '1']]),
            $this->award('clutches', [[Scenario::P1, 'One', '1'], [Scenario::P2, 'Two v2', '1']]),
            $this->award('one_trick', [['', '', '100%', 'c4'], [Scenario::P1, 'One', '73%', 'ak47'], [Scenario::E2, 'Tied', '50%', 'awp']]),
        ], $this->awards());
    }

    public function test_the_kills_with_no_killer_lead_one_trick_as_a_nameless_player(): void
    {
        $oneTrick = collect($this->awards())->firstWhere('key', 'one_trick');

        $this->assertSame(['steam_id' => '', 'name' => '', 'value' => '100%', 'sub' => 'c4'], $oneTrick['leaders'][0]);
    }

    public function test_labels_and_hints_come_as_english_text(): void
    {
        $first = $this->awards()[0];

        $this->assertSame(['Leg-shot leader', 'Kills where the legs took the most damage'], [$first['label'], $first['hint']]);
    }

    public function test_a_team_narrows_the_field_to_its_roster(): void
    {
        $this->assertSame([
            $this->award('legshots', [[Scenario::P1, 'One', '2']]),
            $this->award('first_blood', [[Scenario::P2, 'Two v2', '13'], [Scenario::P1, 'One', '1']]),
            $this->award('headshot', [[Scenario::P1, 'One', '38%', '26 kills']]),
            $this->award('eco', [[Scenario::P2, 'Two v2', '1']]),
            $this->award('clutches', [[Scenario::P1, 'One', '1'], [Scenario::P2, 'Two v2', '1']]),
            $this->award('one_trick', [[Scenario::P1, 'One', '73%', 'ak47']]),
        ], $this->awards(['team_id' => $this->s->team->id]));
    }

    public function test_a_team_with_no_roster_ranks_nobody(): void
    {
        $this->assertSame([], $this->awards(['team_id' => $this->s->emptyTeam->id]));
    }

    public function test_a_team_the_caller_isnt_in_counts_as_no_team(): void
    {
        $this->assertSame($this->awards(), $this->awards(['team_id' => $this->s->otherTeam->id]));
    }

    public function test_a_map_narrows_the_kills_and_drops_the_awards_left_empty(): void
    {
        $this->assertSame([
            $this->award('first_blood', [[Scenario::P2, 'Two v2', '10']]),
            $this->award('eco', [[Scenario::E1, 'Eco One', '17']]),
            $this->award('clutches', [[Scenario::P2, 'Two v2', '1']]),
            $this->award('one_trick', [['', '', '100%', 'c4'], [Scenario::E2, 'Tied', '50%', 'awp']]),
        ], $this->awards(['map' => 'de_inferno']));
    }

    public function test_without_the_ability_the_page_is_refused(): void
    {
        DB::table('global_role_permissions')->delete();
        Sanctum::actingAs($this->s->owner);

        $this->getJson('/api/awards')->assertForbidden();
    }
}
