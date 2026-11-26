<?php

namespace Tests\Unit\PlayerStats;

use App\PlayerStats\Application\BoardRow;
use App\PlayerStats\Application\PlayerProfile;
use App\PlayerStats\Application\RecentGame;
use App\PlayerStats\Application\TeamBoard;
use App\PlayerStats\Domain\MatchScope;
use App\PlayerStats\Domain\StatLine;
use App\PlayerStats\Domain\WeaponKills;
use Tests\Fixtures\PlayerStats\Scenario;

class BoardAndProfileTest extends UseCaseTestCase
{
    private function board(?string $from = null, ?string $to = null, ?int $team = null): array
    {
        return (new TeamBoard($this->s->memoryMatches, $this->s->memoryRosters, $this->totals()))->execute($team ?? $this->s->teamId, $from, $to);
    }

    private function profile(int $userId, ?string $steamId)
    {
        return (new PlayerProfile($this->s->memoryMatches, $this->s->memoryEvents, $this->totals()))->execute($userId, $steamId);
    }

    public function test_the_board_counts_the_teams_matches_for_every_rostered_player(): void
    {
        $this->assertEquals([
            new BoardRow(Scenario::P1, 'One', new StatLine(3, 24, 43, 10, 7, 1, 1)),
            new BoardRow(Scenario::P2, 'Two v2', new StatLine(3, 6, 20, 1, 3, 13, 1)),
            new BoardRow(Scenario::P3, 'Three', StatLine::none()),
            new BoardRow(Scenario::P4, Scenario::P4, StatLine::none()),
        ], $this->board());
    }

    public function test_a_date_range_keeps_only_the_matches_played_in_it(): void
    {
        $board = $this->board('2026-11-01');

        $this->assertEquals(new BoardRow(Scenario::P1, 'One', new StatLine(1, 23, 0, 10, 6, 0, 1)), $board[0]);
        $this->assertEquals(new BoardRow(Scenario::P2, 'Two', new StatLine(1, 2, 3, 0, 2, 3, 0)), $board[1]);
        $this->assertSame(23, $this->board('2026-11-05', '2026-11-05')[0]->line->kills);
    }

    public function test_no_match_in_range_or_an_empty_roster_gives_an_empty_board(): void
    {
        $this->assertSame([], $this->board('2030-01-01', '2030-01-31'));
        $this->assertSame([], $this->board(team: $this->s->emptyTeamId));
    }

    public function test_totals_cover_any_set_of_matches_and_everyone_in_them(): void
    {
        $totals = $this->totals()->over(new MatchScope([$this->s->info('m2')]));

        $this->assertSame(['', Scenario::P1, Scenario::P2, Scenario::E1, Scenario::E2], array_map(fn ($t) => $t->steamId, $totals));
        $this->assertNull($totals[0]->name);
        $this->assertEquals(new StatLine(1, 0, 42, 0, 0, 0, 0), $totals[1]->line);
    }

    public function test_the_profile_covers_every_visible_match(): void
    {
        $profile = $this->profile($this->s->ownerId, Scenario::P1);

        $this->assertEquals(new StatLine(4, 26, 43, 10, 8, 1, 1), $profile->stats);
        $this->assertEquals([
            new RecentGame($this->s->info('m2'), 0, 42, 0.0, null),
            new RecentGame($this->s->info('m1'), 23, 0, 23.0, true),
            new RecentGame($this->s->info('m3'), 1, 1, 1.0, false),
            new RecentGame($this->s->info('m6'), 2, 0, 2.0, true),
        ], $profile->recent);
        $this->assertEquals([new WeaponKills(Scenario::P1, 'ak47', 19), new WeaponKills(Scenario::P1, 'awp', 7)], $profile->weapons);
        $this->assertSame([[$this->s->id('m1'), 21, 2]], array_map(fn ($c) => [$c->match->id, $c->clutch->round, $c->clutch->size], $profile->clutches));
    }

    public function test_no_steam_id_gives_no_profile_and_no_visible_match_gives_zeroes(): void
    {
        $this->assertNull($this->profile($this->s->outsiderId, null));
        $this->assertEquals(StatLine::none(), $this->profile(99, Scenario::P1)->stats);
        $this->assertSame([], $this->profile(99, Scenario::P1)->recent);
    }
}
