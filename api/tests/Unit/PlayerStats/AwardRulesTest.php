<?php

namespace Tests\Unit\PlayerStats;

use App\PlayerStats\Domain\Award;
use App\PlayerStats\Domain\AwardKey;
use App\PlayerStats\Domain\AwardRules;
use App\PlayerStats\Domain\Leader;
use App\PlayerStats\Domain\PlayerCounts;
use App\PlayerStats\Domain\WeaponKills;
use PHPUnit\Framework\TestCase;

class AwardRulesTest extends TestCase
{
    private function award(array $awards, AwardKey $key): ?Award
    {
        foreach ($awards as $award) {
            if ($award->key === $key) {
                return $award;
            }
        }

        return null;
    }

    public function test_the_ratio_awards_need_twenty_kills(): void
    {
        $awards = AwardRules::awards([
            new PlayerCounts('19', kills: 19, headshots: 19),
            new PlayerCounts('20', kills: 20, headshots: 5),
        ], [new WeaponKills('19', 'awp', 19), new WeaponKills('20', 'ak47', 20)]);

        $this->assertEquals([new Leader('20', 0.25, kills: 20)], $this->award($awards, AwardKey::Headshot)->leaders);
        $this->assertEquals([new Leader('20', 1.0, weapon: 'ak47')], $this->award($awards, AwardKey::OneTrick)->leaders);
    }

    public function test_a_count_award_keeps_the_top_five_above_zero(): void
    {
        $counts = array_map(fn ($i) => new PlayerCounts((string) $i, ecoKills: $i), range(0, 7));

        $eco = $this->award(AwardRules::awards($counts, []), AwardKey::Eco);

        $this->assertSame(['7', '6', '5', '4', '3'], array_map(fn (Leader $l) => $l->steamId, $eco->leaders));
    }

    public function test_an_award_nobody_qualifies_for_is_left_out(): void
    {
        $awards = AwardRules::awards([new PlayerCounts('1', kills: 3, openingDeaths: 2)], [new WeaponKills('1', 'ak47', 3)]);

        $this->assertSame([AwardKey::FirstBlood], array_map(fn (Award $a) => $a->key, $awards));
    }

    public function test_a_headshot_rate_of_zero_isnt_a_leader(): void
    {
        $awards = AwardRules::awards([new PlayerCounts('1', kills: 30)], []);

        $this->assertNull($this->award($awards, AwardKey::Headshot));
    }

    public function test_one_trick_is_the_top_weapons_share_with_ties_going_to_the_first_name(): void
    {
        $awards = AwardRules::awards([], [new WeaponKills('2', 'm4a1', 11), new WeaponKills('2', 'awp', 11), new WeaponKills('1', 'ak47', 19), new WeaponKills('1', 'awp', 7)]);

        $this->assertEquals([new Leader('1', 19 / 26, weapon: 'ak47'), new Leader('2', 0.5, weapon: 'awp')], $this->award($awards, AwardKey::OneTrick)->leaders);
    }

    public function test_the_kills_with_no_killer_count_like_a_player(): void
    {
        $awards = AwardRules::awards([], [new WeaponKills('', 'c4', 20), new WeaponKills('1', 'ak47', 19), new WeaponKills('1', 'awp', 7)]);

        $this->assertSame(['', '1'], array_map(fn (Leader $l) => $l->steamId, $this->award($awards, AwardKey::OneTrick)->leaders));
    }

    public function test_the_awards_come_in_page_order(): void
    {
        $awards = AwardRules::awards([new PlayerCounts('1', kills: 20, headshots: 1, clutchRounds: 1, ecoKills: 1, legKills: 1, openingDeaths: 1)], [new WeaponKills('1', 'ak47', 20)]);

        $this->assertSame(AwardKey::cases(), array_map(fn (Award $a) => $a->key, $awards));
    }
}
