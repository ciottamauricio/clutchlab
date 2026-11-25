<?php

namespace Tests\Unit\PlatformSync;

use App\PlatformSync\Domain\InClutchlab;
use App\PlatformSync\Domain\MatchRow;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Domain\PlayedMatch;
use App\PlatformSync\Domain\Team;
use PHPUnit\Framework\TestCase;

// The panel row's rules (spec 001 AC-11, AC-12) on the module's own values: no app, database,
// cache or platform.
class MatchRowTest extends TestCase
{
    private const ME = 'p-me';

    private const MY_USER = 7;

    private const ID = '1-3dec19fc-88c7-421f-8080-b90d23f2f1f9';

    private function match(
        array $team1 = [self::ME, 'mate'],
        array $team2 = ['them'],
        ?int $winner = 1,
        ?int $startedAt = 1795281483,
    ): PlayedMatch {
        return new PlayedMatch(
            new PlatformMatchId(self::ID),
            $startedAt,
            true,
            'South America 5V5 Queue',
            'https://www.faceit.com/en/cs2/room/'.self::ID,
            new Team($team1, 13),
            new Team($team2, 11),
            $winner,
        );
    }

    private function row(PlayedMatch $match, array $inClutchlab = [], ?string $map = 'de_ancient'): MatchRow
    {
        return MatchRow::of($match, self::ME, self::MY_USER, $inClutchlab, $map);
    }

    public function test_score_and_result_are_from_the_users_team(): void
    {
        $won = $this->row($this->match());
        $this->assertSame(['own' => 13, 'opponent' => 11], $won->score);
        $this->assertSame('win', $won->result);

        $lost = $this->row($this->match(team1: ['them'], team2: [self::ME]));
        $this->assertSame(['own' => 11, 'opponent' => 13], $lost->score);
        $this->assertSame('loss', $lost->result);
    }

    public function test_a_user_on_neither_team_gets_no_score_or_result(): void
    {
        $row = $this->row($this->match(team1: ['a'], team2: ['b']));

        $this->assertNull($row->score);
        $this->assertNull($row->result);
    }

    public function test_no_winner_means_no_result_but_still_a_score(): void
    {
        $row = $this->row($this->match(winner: null));

        $this->assertSame(['own' => 13, 'opponent' => 11], $row->score);
        $this->assertNull($row->result);
    }

    public function test_the_users_own_upload_wins_over_teammates_and_the_earliest_teammates_otherwise(): void
    {
        $mine = new InClutchlab(30, self::MY_USER, 'parsed');
        $mate = new InClutchlab(12, 8, 'parsing');
        $laterMate = new InClutchlab(20, 9, 'parsed');

        $this->assertSame($mine, $this->row($this->match(), [$mate, $mine, $laterMate])->inClutchlab);
        $this->assertSame($mate, $this->row($this->match(), [$laterMate, $mate])->inClutchlab);
        $this->assertNull($this->row($this->match(), [])->inClutchlab);
    }

    public function test_the_start_is_a_utc_time_and_the_rest_passes_through(): void
    {
        $row = $this->row($this->match(), map: 'de_dust2');

        $this->assertSame('2026-11-21T17:18:03+00:00', $row->startedAt->toIso8601String());
        $this->assertSame(self::ID, $row->id->value);
        $this->assertSame('de_dust2', $row->map);
        $this->assertSame('South America 5V5 Queue', $row->competition);
        $this->assertSame('https://www.faceit.com/en/cs2/room/'.self::ID, $row->roomUrl);
        $this->assertNull($this->row($this->match(startedAt: null))->startedAt);
    }
}
