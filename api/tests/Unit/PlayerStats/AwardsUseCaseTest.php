<?php

namespace Tests\Unit\PlayerStats;

use App\PlayerStats\Application\AwardBoard;
use App\PlayerStats\Application\AwardKills;
use App\PlayerStats\Application\Awards;
use App\PlayerStats\Domain\Award;
use App\PlayerStats\Domain\AwardKey;
use App\PlayerStats\Domain\Leader;
use Tests\Fixtures\PlayerStats\Scenario;

class AwardsUseCaseTest extends UseCaseTestCase
{
    private function awards(?int $team = null, ?string $map = null): AwardBoard
    {
        return (new Awards($this->s->memoryMatches, $this->s->memoryRosters, $this->s->memoryEvents))->execute($this->s->ownerId, $team, $map);
    }

    /** @return list<array{0: int, 1: int}> */
    private function kills(AwardKey $key, string $steamId, ?string $map = null): array
    {
        $kills = (new AwardKills($this->s->memoryMatches, $this->s->memoryEvents))->execute($this->s->ownerId, $key, $steamId, $map);

        return array_map(fn ($k) => [$k->match->id, $k->kill->round], $kills);
    }

    /** @return array<string, list<string>> each award's leaders' Steam IDs */
    private function leaders(AwardBoard $board): array
    {
        return array_merge(...array_map(fn (Award $a) => [$a->key->value => array_map(fn (Leader $l) => $l->steamId, $a->leaders)], $board->awards));
    }

    public function test_every_award_over_the_visible_matches(): void
    {
        $board = $this->awards();

        $this->assertSame([
            'legshots' => [Scenario::P1],
            'first_blood' => [Scenario::P2, Scenario::E2, Scenario::E1, Scenario::P1],
            'headshot' => [Scenario::P1],
            'eco' => [Scenario::E1, Scenario::P2],
            'clutches' => [Scenario::P1, Scenario::P2],
            'one_trick' => ['', Scenario::P1, Scenario::E2],
        ], $this->leaders($board));
        $this->assertEquals(new Leader(Scenario::P1, 10 / 26, kills: 26), $board->awards[2]->leaders[0]);
        $this->assertEquals([new Leader('', 1.0, weapon: 'c4'), new Leader(Scenario::P1, 19 / 26, weapon: 'ak47'), new Leader(Scenario::E2, 0.5, weapon: 'awp')], $board->awards[5]->leaders);
        $this->assertSame(['Two v2', '', Scenario::P4], [$board->nameOf(Scenario::P2), $board->nameOf(''), $board->nameOf(Scenario::P4)]);
    }

    public function test_a_team_narrows_to_its_roster_and_an_empty_one_ranks_nobody(): void
    {
        $this->assertSame([
            'legshots' => [Scenario::P1],
            'first_blood' => [Scenario::P2, Scenario::P1],
            'headshot' => [Scenario::P1],
            'eco' => [Scenario::P2],
            'clutches' => [Scenario::P1, Scenario::P2],
            'one_trick' => [Scenario::P1],
        ], $this->leaders($this->awards($this->s->teamId)));
        $this->assertSame([], $this->awards($this->s->emptyTeamId)->awards);
    }

    public function test_a_map_narrows_the_kills(): void
    {
        $this->assertSame([
            'first_blood' => [Scenario::P2],
            'eco' => [Scenario::E1],
            'clutches' => [Scenario::P2],
            'one_trick' => ['', Scenario::E2],
        ], $this->leaders($this->awards(map: 'de_inferno')));
    }

    public function test_the_drill_down_lists_the_kills_behind_each_row(): void
    {
        $this->assertSame([[1, 1], [1, 2]], $this->kills(AwardKey::Legshots, Scenario::P1));
        $this->assertSame([[1, 21], [1, 23], [1, 24], ...array_map(fn ($r) => [2, $r], range(1, 10))], $this->kills(AwardKey::FirstBlood, Scenario::P2));
        $this->assertSame(array_map(fn ($r) => [2, $r], range(1, 10)), $this->kills(AwardKey::FirstBlood, Scenario::P2, 'de_inferno'));
        $this->assertSame([[1, 21], [1, 21]], $this->kills(AwardKey::Clutches, Scenario::P1));
    }

    public function test_a_tied_top_weapon_lists_the_one_the_award_named(): void
    {
        $kills = (new AwardKills($this->s->memoryMatches, $this->s->memoryEvents))->execute($this->s->ownerId, AwardKey::OneTrick, Scenario::E2);

        $this->assertCount(11, $kills);
        $this->assertSame(['awp'], array_values(array_unique(array_map(fn ($k) => $k->kill->weapon, $kills))));
    }
}
