<?php

namespace Tests\Postgres\Characterization;

use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\PlayerStats\Scenario;

// GET /teams/{team}/stats: the roster's board over the team's own matches.
class TeamBoardTest extends CharacterizationTestCase
{
    private function board(array $query = [], ?int $team = null): array
    {
        Sanctum::actingAs($this->s->owner);
        $url = '/api/teams/'.($team ?? $this->s->team->id).'/stats'.($query ? '?'.http_build_query($query) : '');

        return $this->getJson($url)->assertOk()->json('data.players');
    }

    private function row(string $id, string $name, int $games, int $kills, int $deaths, int|float $kd, int $hs, int $entry, int $first, int $clutches): array
    {
        return ['steam_id' => $id, 'name' => $name, 'games' => $games, 'kills' => $kills, 'deaths' => $deaths, 'kd' => $kd,
            'hs_pct' => $hs, 'entry_kills' => $entry, 'first_deaths' => $first, 'clutches' => $clutches];
    }

    public function test_the_board_counts_the_teams_matches_for_every_rostered_player(): void
    {
        $this->assertSame([
            $this->row(Scenario::P1, 'One', 3, 24, 43, 0.56, 42, 7, 1, 1),
            $this->row(Scenario::P2, 'Two v2', 3, 6, 20, 0.3, 17, 3, 13, 1),
            $this->row(Scenario::P3, 'Three', 0, 0, 0, 0, 0, 0, 0, 0),
            $this->row(Scenario::P4, Scenario::P4, 0, 0, 0, 0, 0, 0, 0, 0),
        ], $this->tiesBySteamId($this->board(), 'kills'));
    }

    public function test_a_date_range_keeps_only_the_matches_played_in_it(): void
    {
        // m1 only: m2 was played in October, m3 and m4 are undated. No deaths gives K/D = kills.
        $this->assertSame([
            $this->row(Scenario::P1, 'One', 1, 23, 0, 23, 43, 6, 0, 1),
            $this->row(Scenario::P2, 'Two', 1, 2, 3, 0.67, 0, 2, 3, 0),
            $this->row(Scenario::P3, 'Three', 0, 0, 0, 0, 0, 0, 0, 0),
            $this->row(Scenario::P4, Scenario::P4, 0, 0, 0, 0, 0, 0, 0, 0),
        ], $this->tiesBySteamId($this->board(['from' => '2026-11-01']), 'kills'));
    }

    public function test_the_range_ends_include_their_whole_days(): void
    {
        $board = $this->board(['from' => '2026-11-05', 'to' => '2026-11-05']);

        $this->assertSame(23, $board[0]['kills']);
    }

    public function test_a_self_kill_counts_as_a_kill_an_opening_and_a_death(): void
    {
        // p2 in m1: one kill of e1 and the world's kill of p2 in round 24, which opened the round.
        $p2 = collect($this->board(['from' => '2026-11-01']))->firstWhere('steam_id', Scenario::P2);

        $this->assertSame([2, 3, 2, 3], [$p2['kills'], $p2['deaths'], $p2['entry_kills'], $p2['first_deaths']]);
    }

    public function test_no_match_in_range_gives_an_empty_board(): void
    {
        $this->assertSame([], $this->board(['from' => '2030-01-01', 'to' => '2030-01-31']));
    }

    public function test_an_empty_roster_gives_an_empty_board(): void
    {
        $this->assertSame([], $this->board(team: $this->s->emptyTeam->id));
    }

    public function test_a_date_that_isnt_one_is_ignored(): void
    {
        $this->assertSame($this->board(), $this->board(['from' => 'yesterday']));
    }

    public function test_someone_outside_the_team_is_refused(): void
    {
        Sanctum::actingAs($this->s->outsider);

        $this->getJson('/api/teams/'.$this->s->team->id.'/stats')->assertForbidden();
    }
}
