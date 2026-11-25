<?php

namespace Tests\Unit\PlatformSync;

use App\PlatformSync\Application\MatchPanel;
use App\PlatformSync\Application\MissingAccount;
use App\PlatformSync\Application\SyncOff;
use App\PlatformSync\Domain\InClutchlab;
use App\PlatformSync\Domain\MatchFacts;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Domain\PlatformPlayer;
use App\PlatformSync\Domain\PlayedMatch;
use App\PlatformSync\Domain\Team;
use App\PlatformSync\Ports\PlatformUnavailable;

// Spec 001 AC-11 to AC-16: the panel's list, on in-memory adapters. FaceitMatchesTest checks the
// same over HTTP; this says the rules without a database.
class ListMatchesTest extends UseCaseTestCase
{
    private const ME_USER = 7;

    private const MATE_USER = 8;

    private const STRANGER_USER = 9;

    private const STEAM = '76561198963438273';

    private const ME = 'p-me';

    private const M1 = '1-11111111-1111-1111-1111-111111111111';

    private const M2 = '1-22222222-2222-2222-2222-222222222222';

    private const M3 = '1-33333333-3333-3333-3333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();
        $this->platform->players[self::STEAM] = new PlatformPlayer(self::ME, 'FiveAlert');
        $this->platform->recent[self::ME] = [
            $this->played(self::M3, finished: false),
            $this->played(self::M2, myTeam: 2, winner: 1, scores: [13, 8]),
            $this->played(self::M1, myTeam: 1, winner: 1, scores: [13, 11]),
        ];
        foreach ([self::M1 => 'de_ancient', self::M2 => 'de_dust2', self::M3 => 'de_mirage'] as $id => $map) {
            $this->platform->matches[$id] = new MatchFacts(new PlatformMatchId($id), 1795281483, $id !== self::M3, $map);
        }
    }

    private function played(string $id, bool $finished = true, int $myTeam = 1, ?int $winner = 1, array $scores = [13, 11]): PlayedMatch
    {
        $mine = [self::ME, 'mate'];
        $theirs = ['them'];

        return new PlayedMatch(
            new PlatformMatchId($id),
            1795281483,
            $finished,
            'South America 5V5 Queue',
            "https://www.faceit.com/en/cs2/room/{$id}",
            new Team($myTeam === 1 ? $mine : $theirs, $scores[0]),
            new Team($myTeam === 1 ? $theirs : $mine, $scores[1]),
            $winner,
        );
    }

    private function list(bool $refresh = false, ?string $steamId = self::STEAM): MatchPanel
    {
        return $this->listMatches()->execute($this->viewer(self::ME_USER, $steamId), $refresh);
    }

    public function test_it_lists_finished_matches_newest_first_scored_from_the_users_team(): void
    {
        $panel = $this->list();

        $this->assertSame('FiveAlert', $panel->account->player->nickname);
        $this->assertNull($panel->reason);
        $this->assertSame([self::M2, self::M1], array_map(fn ($r) => $r->id->value, $panel->rows));
        $this->assertSame('de_dust2', $panel->rows[0]->map);
        $this->assertSame(['own' => 8, 'opponent' => 13], $panel->rows[0]->score);
        $this->assertSame('loss', $panel->rows[0]->result);
        $this->assertSame(['own' => 13, 'opponent' => 11], $panel->rows[1]->score);
        $this->assertSame('win', $panel->rows[1]->result);
    }

    public function test_it_marks_the_users_and_teammates_uploads_but_never_another_teams(): void
    {
        $mine = new InClutchlab(30, self::ME_USER, 'parsed');
        $teammates = new InClutchlab(31, self::MATE_USER, 'parsing');
        $this->clutchlab->file($mine, 'faceit', self::M1, [self::ME_USER]);
        $this->clutchlab->file($teammates, 'faceit', self::M2, [self::ME_USER, self::MATE_USER]);
        $this->clutchlab->file(new InClutchlab(32, self::STRANGER_USER, 'parsed'), 'faceit', self::M2, [self::STRANGER_USER]);

        $panel = $this->list();

        $this->assertSame($teammates, $panel->rows[0]->inClutchlab);
        $this->assertSame($mine, $panel->rows[1]->inClutchlab);
    }

    public function test_a_match_filed_under_another_platform_is_missing(): void
    {
        $this->clutchlab->file(new InClutchlab(40, self::ME_USER, 'parsed'), 'steam', self::M1, [self::ME_USER]);

        $this->assertNull($this->list()->rows[1]->inClutchlab);
    }

    public function test_the_list_is_kept_for_its_ttl_refresh_asks_again_and_a_finished_match_is_asked_once(): void
    {
        $this->list();
        $this->list();
        $this->assertSame(1, $this->platform->callsTo('recentMatches'));
        $this->assertSame(2, $this->platform->callsTo('match'));

        $this->list(refresh: true);
        $this->assertSame(2, $this->platform->callsTo('recentMatches'));
        $this->assertSame(2, $this->platform->callsTo('match'));

        $this->cache->advance(self::LIST_TTL + 60);
        $this->list();
        $this->assertSame(3, $this->platform->callsTo('recentMatches'));
        $this->assertSame(2, $this->platform->callsTo('match'));
    }

    public function test_a_match_the_platform_has_no_facts_for_shows_without_a_map(): void
    {
        unset($this->platform->matches[self::M1]);

        $this->assertNull($this->list()->rows[1]->map);
    }

    public function test_a_map_the_platform_cant_give_right_now_leaves_the_row_without_one(): void
    {
        $this->platform->failing = ['match'];

        $panel = $this->list();
        $this->assertCount(2, $panel->rows);
        $this->assertNull($panel->rows[0]->map);

        $this->platform->failing = [];
        $this->assertSame('de_dust2', $this->list()->rows[0]->map);
    }

    public function test_without_a_key_sync_is_off(): void
    {
        $this->enabled = false;

        try {
            $this->list();
            $this->fail('expected SyncOff');
        } catch (SyncOff) {
            $this->assertSame([], $this->platform->calls);
        }
    }

    public function test_the_platform_down_on_the_list_is_unavailable(): void
    {
        $this->platform->failing = ['recentMatches'];

        $this->expectException(PlatformUnavailable::class);
        $this->list();
    }

    public function test_no_account_is_a_reason_not_an_error(): void
    {
        $none = $this->list(steamId: null);
        $this->assertNull($none->account);
        $this->assertSame(MissingAccount::NoSteamId, $none->reason);
        $this->assertSame([], $none->rows);

        $this->assertSame(MissingAccount::NotFound, $this->list(steamId: '76561190000000009')->reason);
        $this->assertSame(0, $this->platform->callsTo('recentMatches'));
    }
}
