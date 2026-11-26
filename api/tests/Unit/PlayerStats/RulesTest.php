<?php

namespace Tests\Unit\PlayerStats;

use App\PlayerStats\Domain\Clutch;
use App\PlayerStats\Domain\Clutches;
use App\PlayerStats\Domain\HitGroups;
use App\PlayerStats\Domain\Kill;
use App\PlayerStats\Domain\MatchInfo;
use App\PlayerStats\Domain\MatchScope;
use App\PlayerStats\Domain\Outcome;
use App\PlayerStats\Domain\Ranking;
use App\PlayerStats\Domain\TopWeapon;
use App\PlayerStats\Domain\Weapons;
use PHPUnit\Framework\TestCase;

class RulesTest extends TestCase
{
    public function test_the_eco_weapons_are_the_pistols_and_the_knife(): void
    {
        $this->assertTrue(Weapons::isEco('deserteagle'));
        $this->assertTrue(Weapons::isEco('knife'));
        $this->assertFalse(Weapons::isEco('deagle')); // the registry's values, not CS2's short names
        $this->assertFalse(Weapons::isEco('ak47'));
        $this->assertFalse(Weapons::isEco(null));
    }

    public function test_a_leg_dominant_kill_hit_the_legs_at_least_as_hard_as_any_other_zone(): void
    {
        $this->assertTrue(HitGroups::legsDominant(['legs' => 50, 'chest' => 40]));
        $this->assertTrue(HitGroups::legsDominant(['legs' => 30, 'head' => 30]));
        $this->assertTrue(HitGroups::legsDominant(['legs' => 5]));
        $this->assertFalse(HitGroups::legsDominant(['legs' => 10, 'head' => 90]));
        $this->assertFalse(HitGroups::legsDominant(['legs' => 0, 'chest' => 100]));
        $this->assertFalse(HitGroups::legsDominant(['chest' => 100]));
        $this->assertFalse(HitGroups::legsDominant(null));
    }

    public function test_clutch_kills_group_into_one_clutch_per_round(): void
    {
        $k = fn (int $match, int $round, int $tick, int $clutch = 2) => new Kill($match, $round, '1', '9', 'ak47', clutch: $clutch, tick: $tick, killerName: 'One');

        $clutches = Clutches::fromKills([$k(2, 20, 200, 3), $k(1, 21, 2200), $k(1, 21, 2100), $k(1, 4, 500, 0), $k(2, 20, 100, 3)]);

        $this->assertEquals([
            new Clutch(1, 21, 2, '1', 'One', [$k(1, 21, 2100), $k(1, 21, 2200)]),
            new Clutch(2, 20, 3, '1', 'One', [$k(2, 20, 100, 3), $k(2, 20, 200, 3)]),
        ], $clutches);
        $this->assertSame(2, Clutches::count([$k(1, 21, 1), $k(1, 21, 2), $k(1, 22, 3)]));
    }

    public function test_equal_values_rank_by_steam_id(): void
    {
        $rows = [['id' => '3', 'v' => 1], ['id' => '1', 'v' => 1], ['id' => '2', 'v' => 4], ['id' => '', 'v' => 1]];

        $ranked = Ranking::rank($rows, fn ($r) => $r['v'], fn ($r) => $r['id']);

        $this->assertSame(['2', '', '1', '3'], array_column($ranked, 'id'));
    }

    public function test_a_tied_top_weapon_goes_to_the_first_name(): void
    {
        $this->assertSame('awp', TopWeapon::of(['m4a1' => 11, 'awp' => 11, 'deagle' => 2]));
        $this->assertSame(['ak47', 'awp', 'm4a1'], TopWeapon::ranked(['awp' => 7, 'm4a1' => 7, 'ak47' => 19]));
        $this->assertNull(TopWeapon::of([]));
    }

    public function test_the_newest_upload_comes_first_and_the_higher_id_breaks_a_tie(): void
    {
        $scope = new MatchScope([
            new MatchInfo(3, '2026-11-06 10:00:00'),
            new MatchInfo(1, '2026-11-06 10:00:00'),
            new MatchInfo(7, '2026-11-07 09:00:00'),
            new MatchInfo(2, '2026-10-01 00:00:00'),
        ]);

        $this->assertSame([7, 3, 1, 2], $scope->newestFirst());
        $this->assertSame([1, 2, 3, 7], $scope->ids());
        $this->assertFalse($scope->isEmpty());
        $this->assertTrue((new MatchScope([]))->isEmpty());
    }

    public function test_an_undated_match_sorts_by_its_upload(): void
    {
        $this->assertSame('2026-11-07 09:00:00', (new MatchInfo(1, '2026-11-07 09:00:00'))->playedOrUploaded());
        $this->assertSame('2026-11-05 20:00:00', (new MatchInfo(1, '2026-11-07 09:00:00', '2026-11-05 20:00:00'))->playedOrUploaded());
    }

    public function test_the_players_side_decides_the_outcome(): void
    {
        $this->assertTrue(Outcome::won(13, 9, 'CT'));
        $this->assertFalse(Outcome::won(5, 13, 'CT'));
        $this->assertTrue(Outcome::won(5, 13, 'T'));
        $this->assertNull(Outcome::won(13, 13, 'T'));
        $this->assertNull(Outcome::won(null, 13, 'T'));
        $this->assertNull(Outcome::won(13, 9, null));
    }
}
