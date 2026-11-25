<?php

namespace Tests\Unit\PlatformSync;

use App\PlatformSync\Adapters\Faceit\FaceitDataApi;
use App\PlatformSync\Domain\MatchFacts;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Domain\PlatformPlayer;
use App\PlatformSync\Domain\PlayedMatch;
use App\PlatformSync\Domain\SteamId;
use App\PlatformSync\Domain\Team;
use App\PlatformSync\Ports\PlatformUnavailable;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\TestCase;

// FaceitDataApi against a faked HTTP client: answers shaped like FACEIT's real ones (spec 001
// research R1–R3, checked with a real key on 2026-11-22), never the network.
class FaceitDataApiTest extends TestCase
{
    private const KEY = 'test-key-0000';

    private const ID = '1-3dec19fc-88c7-421f-8080-b90d23f2f1f9';

    private Http $http;

    protected function setUp(): void
    {
        $this->http = new Http;
    }

    private function api(): FaceitDataApi
    {
        return new FaceitDataApi($this->http, 'https://faceit.test/data/v4', self::KEY, 5);
    }

    public function test_it_finds_a_player_by_steam_id_sending_the_key_as_a_bearer(): void
    {
        $this->http->fake(['*' => Http::response([
            'player_id' => '38382e8b-8f9e-4cb2-9705-b509c60122e5',
            'nickname' => 'FiveAlert',
            'games' => ['cs2' => ['game_player_id' => '76561198963438273', 'faceit_elo' => 1500]],
        ])]);

        $player = $this->api()->findPlayerBySteamId(new SteamId('76561198963438273'));

        $this->assertEquals(new PlatformPlayer('38382e8b-8f9e-4cb2-9705-b509c60122e5', 'FiveAlert'), $player);
        $this->http->assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.self::KEY)
            && str_starts_with($r->url(), 'https://faceit.test/data/v4/players?')
            && $r['game'] === 'cs2'
            && $r['game_player_id'] === '76561198963438273');
    }

    public function test_a_steam_id_with_no_faceit_account_is_null(): void
    {
        $this->http->fake(['*' => Http::response(['errors' => [['message' => 'not found']]], 404)]);

        $this->assertNull($this->api()->findPlayerBySteamId(new SteamId('76561190000000000')));
    }

    public function test_recent_matches_are_normalized_into_two_teams(): void
    {
        $this->http->fake(['*' => Http::response(['items' => [
            [
                'match_id' => self::ID,
                'started_at' => 1795281483,
                'finished_at' => 1795283887,
                'status' => 'finished',
                'competition_name' => 'South America 5V5 Queue',
                'faceit_url' => 'https://www.faceit.com/{lang}/cs2/room/'.self::ID,
                'teams' => [
                    'faction1' => ['players' => [['player_id' => 'me'], ['player_id' => 'mate']]],
                    'faction2' => ['players' => [['player_id' => 'them']]],
                ],
                'results' => ['winner' => 'faction2', 'score' => ['faction1' => 11, 'faction2' => 13]],
            ],
            ['no_match_id' => true],
            ['match_id' => ''],
        ], 'start' => 0, 'end' => 20])]);

        $matches = $this->api()->recentMatches('me', 20);

        $this->assertEquals([new PlayedMatch(
            new PlatformMatchId(self::ID),
            1795281483,
            true,
            'South America 5V5 Queue',
            'https://www.faceit.com/en/cs2/room/'.self::ID,
            new Team(['me', 'mate'], 11),
            new Team(['them'], 13),
            2,
        )], $matches);
        $this->http->assertSent(fn (Request $r) => str_contains($r->url(), '/players/me/history?')
            && $r['game'] === 'cs2' && (int) $r['limit'] === 20 && ! isset($r['from']));
    }

    public function test_a_match_is_normalized(): void
    {
        $this->http->fake(['*' => Http::response([
            'match_id' => self::ID,
            'started_at' => 1795281483,
            'status' => 'FINISHED',
            'voting' => ['map' => ['pick' => ['de_ancient']]],
        ])]);

        $this->assertEquals(
            new MatchFacts(new PlatformMatchId(self::ID), 1795281483, true, 'de_ancient'),
            $this->api()->match(new PlatformMatchId(self::ID)),
        );
    }

    public function test_an_unknown_match_is_null(): void
    {
        $this->http->fake(['*' => Http::response([], 404)]);

        $this->assertNull($this->api()->match(new PlatformMatchId('1-00000000-0000-0000-0000-000000000000')));
    }

    public function test_every_failure_is_unavailable_and_never_carries_the_key(): void
    {
        $failures = [
            'refused key' => Http::response(['errors' => []], 401),
            'forbidden' => Http::response(['errors' => []], 403),
            'rate limited' => Http::response(['errors' => []], 429),
            'server error' => Http::response('oops', 500),
            'not JSON' => Http::response('<html>', 200),
            'no player_id' => Http::response(['nickname' => 'x'], 200),
            'unreachable' => Http::failedConnection(),
        ];

        foreach ($failures as $case => $response) {
            $this->http = new Http;
            $this->http->fake(['*' => $response]);
            try {
                $this->api()->findPlayerBySteamId(new SteamId('76561198963438273'));
                $this->fail("{$case}: expected PlatformUnavailable");
            } catch (PlatformUnavailable $e) {
                $this->assertStringNotContainsString(self::KEY, $e->getMessage(), $case);
            }
        }
    }
}
