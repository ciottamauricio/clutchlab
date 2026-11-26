<?php

namespace Tests\Unit\PlayerStats;

use App\PlayerStats\Application\MatchClutches;
use App\PlayerStats\Application\MatchSides;
use App\PlayerStats\Application\PlayerClutches;
use App\PlayerStats\Application\PlayerList;
use App\PlayerStats\Domain\PlayerEntry;
use App\PlayerStats\Domain\SideCounts;
use Tests\Fixtures\PlayerStats\Scenario;

class ListsAndClutchesTest extends UseCaseTestCase
{
    public function test_the_players_list_ranks_by_matches_played_then_steam_id(): void
    {
        $list = new PlayerList($this->s->memoryMatches, $this->s->memoryEvents);

        $this->assertEquals([
            new PlayerEntry(Scenario::P1, 'One', 4), new PlayerEntry(Scenario::E1, 'Eco One', 4),
            new PlayerEntry(Scenario::P2, 'Two v2', 3), new PlayerEntry(Scenario::E2, 'Tied', 3),
        ], $list->seenBy($this->s->ownerId));
        $this->assertEquals([new PlayerEntry(Scenario::P1, 'One', 1), new PlayerEntry(Scenario::X1, 'Xavier', 1)], $list->seenBy($this->s->outsiderId));
    }

    public function test_the_admin_list_covers_every_match_by_name(): void
    {
        $this->assertEquals([
            new PlayerEntry(Scenario::E1, 'Eco One', 4), new PlayerEntry(Scenario::P1, 'One', 5), new PlayerEntry(Scenario::E2, 'Tied', 3),
            new PlayerEntry(Scenario::P2, 'Two v2', 3), new PlayerEntry(Scenario::X1, 'Xavier', 1),
        ], (new PlayerList($this->s->memoryMatches, $this->s->memoryEvents))->all());
    }

    public function test_a_players_clutches_span_every_visible_match(): void
    {
        $clutches = new PlayerClutches($this->s->memoryMatches, $this->s->memoryEvents);

        $this->assertSame([[$this->s->id('m1'), 21, 2, 2]], array_map(fn ($c) => [$c->match->id, $c->clutch->round, $c->clutch->size, count($c->clutch->kills)], $clutches->execute($this->s->ownerId, Scenario::P1)));
        $this->assertSame([[$this->s->id('m2'), 20, 3, 'Two']], array_map(fn ($c) => [$c->match->id, $c->clutch->round, $c->clutch->size, $c->clutch->clutcherName], $clutches->execute($this->s->ownerId, Scenario::P2)));
        $this->assertSame([], $clutches->execute($this->s->outsiderId, Scenario::P1));
    }

    public function test_a_match_compares_its_sides_and_lists_its_clutches(): void
    {
        $sides = (new MatchSides($this->s->memoryEvents))->execute($this->s->id('m1'));

        $this->assertEquals(new SideCounts(25, 3, 0, 10, 8, 1), $sides['CT']->counts);
        $this->assertSame([1, 40], [$sides['CT']->openingDeaths, $sides['CT']->hsPct]);
        $this->assertEquals(new SideCounts(1, 23, 0, 0, 1, 0), $sides['T']->counts);
        $this->assertSame([8, 0], [$sides['T']->openingDeaths, $sides['T']->hsPct]);

        $this->assertSame([[21, 2, Scenario::P1]], array_map(fn ($c) => [$c->round, $c->size, $c->clutcherId], (new MatchClutches($this->s->memoryEvents))->execute($this->s->id('m1'))));
        $this->assertEquals(new SideCounts, (new MatchSides($this->s->memoryEvents))->execute($this->s->id('m4'))['CT']->counts);
    }
}
