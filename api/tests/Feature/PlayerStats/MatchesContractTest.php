<?php

namespace Tests\Feature\PlayerStats;

use App\PlayerStats\Adapters\Eloquent\EloquentMatches;
use App\PlayerStats\Adapters\Eloquent\EloquentRosters;
use App\PlayerStats\Domain\MatchScope;
use App\PlayerStats\Domain\RosterPlayer;
use App\PlayerStats\Ports\Matches;
use App\PlayerStats\Ports\Rosters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\PlayerStats\Scenario;
use Tests\TestCase;

// The Matches and Rosters contracts, on the in-memory adapters the use-case tests trust and on the
// Eloquent ones, over the same scenario, so they can't drift apart.
class MatchesContractTest extends TestCase
{
    use RefreshDatabase;

    private Scenario $s;

    public static function adapters(): array
    {
        return ['in memory' => ['memory'], 'eloquent' => ['eloquent']];
    }

    private function load(string $kind): void
    {
        $this->s = $kind === 'eloquent' ? (new Scenario)->intoDatabase() : (new Scenario)->intoMemory();
    }

    private function adapter(string $kind): Matches
    {
        return $kind === 'eloquent' ? new EloquentMatches : $this->s->memoryMatches;
    }

    private function rosters(string $kind): Rosters
    {
        return $kind === 'eloquent' ? new EloquentRosters : $this->s->memoryRosters;
    }

    private function scope(string ...$matches): MatchScope
    {
        return new MatchScope(array_map(fn ($m) => $this->s->info($m), $matches));
    }

    #[DataProvider('adapters')]
    public function test_a_user_sees_their_uploads_and_their_teams_matches(string $kind): void
    {
        $this->load($kind);

        $this->assertEquals($this->scope('m1', 'm2', 'm3', 'm4', 'm6'), $this->adapter($kind)->visibleTo($this->s->ownerId));
        $this->assertEquals($this->scope('m5'), $this->adapter($kind)->visibleTo($this->s->outsiderId));
        $this->assertEquals($this->scope(), $this->adapter($kind)->visibleTo(999999));
    }

    #[DataProvider('adapters')]
    public function test_a_teams_matches_narrow_to_whole_days_and_drop_undated_ones_once_bounded(string $kind): void
    {
        $this->load($kind);
        $team = fn (?string $from, ?string $to) => $this->adapter($kind)->ofTeam($this->s->teamId, $from, $to);

        $this->assertEquals($this->scope('m1', 'm2', 'm3', 'm4'), $team(null, null));
        $this->assertEquals($this->scope('m1'), $team('2026-11-01', null));
        $this->assertEquals($this->scope('m1'), $team('2026-11-05', '2026-11-05'));
        $this->assertEquals($this->scope('m2'), $team(null, '2026-10-31'));
        $this->assertEquals($this->scope(), $team('2030-01-01', '2030-01-31'));
    }

    #[DataProvider('adapters')]
    public function test_every_match_is_there_for_the_admin(string $kind): void
    {
        $this->load($kind);

        $this->assertEquals($this->scope('m1', 'm2', 'm3', 'm4', 'm5', 'm6'), $this->adapter($kind)->all());
    }

    #[DataProvider('adapters')]
    public function test_a_roster_lists_its_players_with_their_nicknames(string $kind): void
    {
        $this->load($kind);

        $this->assertEquals([new RosterPlayer(Scenario::P1), new RosterPlayer(Scenario::P2), new RosterPlayer(Scenario::P3, 'Three'), new RosterPlayer(Scenario::P4)], $this->rosters($kind)->of($this->s->teamId));
        $this->assertSame([], $this->rosters($kind)->of($this->s->emptyTeamId));
    }
}
