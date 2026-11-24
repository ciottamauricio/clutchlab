<?php

namespace Tests\Unit;

use App\Actions\Faceit\FaceitMatchRow;
use PHPUnit\Framework\TestCase;

// The FACEIT panel's row rules with nothing but arrays: no database, cache or FACEIT fake.
class FaceitMatchRowTest extends TestCase
{
    private const ME = 'p-me';

    private const MY_USER = 7;

    private function match(array $overrides = []): array
    {
        return array_replace([
            'faceit_match_id' => '1-3dec19fc-88c7-421f-8080-b90d23f2f1f9',
            'started_at' => 1795281483,
            'finished' => true,
            'competition' => 'South America 5V5 Queue',
            'room_url' => 'https://www.faceit.com/en/cs2/room/1-3dec19fc-88c7-421f-8080-b90d23f2f1f9',
            'factions' => ['faction1' => [self::ME, 'mate'], 'faction2' => ['them']],
            'winner' => 'faction1',
            'score' => ['faction1' => 13, 'faction2' => 11],
        ], $overrides);
    }

    private function row(array $match, array $inClutchlab = [], ?string $map = 'de_ancient'): array
    {
        return FaceitMatchRow::build($match, self::ME, self::MY_USER, $inClutchlab, $map);
    }

    public function test_score_and_result_are_from_the_users_team(): void
    {
        $won = $this->row($this->match());
        $this->assertSame(['own' => 13, 'opponent' => 11], $won['score']);
        $this->assertSame('win', $won['result']);

        $lost = $this->row($this->match(['factions' => ['faction1' => ['them'], 'faction2' => [self::ME]]]));
        $this->assertSame(['own' => 11, 'opponent' => 13], $lost['score']);
        $this->assertSame('loss', $lost['result']);
    }

    public function test_a_user_on_neither_team_gets_no_score_or_result(): void
    {
        $row = $this->row($this->match(['factions' => ['faction1' => ['a'], 'faction2' => ['b']]]));

        $this->assertNull($row['score']);
        $this->assertNull($row['result']);
    }

    public function test_no_winner_means_no_result_but_still_a_score(): void
    {
        $row = $this->row($this->match(['winner' => null]));

        $this->assertSame(['own' => 13, 'opponent' => 11], $row['score']);
        $this->assertNull($row['result']);
    }

    public function test_the_users_own_upload_wins_over_teammates_and_the_earliest_teammates_otherwise(): void
    {
        $mine = ['id' => 30, 'user_id' => self::MY_USER, 'status' => 'parsed'];
        $mate = ['id' => 12, 'user_id' => 8, 'status' => 'parsing'];
        $laterMate = ['id' => 20, 'user_id' => 9, 'status' => 'parsed'];

        $this->assertSame(['id' => 30, 'status' => 'parsed'], $this->row($this->match(), [$mate, $mine, $laterMate])['clutchlab']);
        $this->assertSame(['id' => 12, 'status' => 'parsing'], $this->row($this->match(), [$laterMate, $mate])['clutchlab']);
        $this->assertNull($this->row($this->match(), [])['clutchlab']);
    }

    public function test_the_start_is_a_utc_time_and_the_rest_passes_through(): void
    {
        $row = $this->row($this->match(), map: 'de_dust2');

        $this->assertSame('2026-11-21T17:18:03+00:00', $row['started_at']->toIso8601String());
        $this->assertSame('de_dust2', $row['map']);
        $this->assertSame('South America 5V5 Queue', $row['competition']);
        $this->assertNull($this->row($this->match(['started_at' => null]))['started_at']);
    }
}
