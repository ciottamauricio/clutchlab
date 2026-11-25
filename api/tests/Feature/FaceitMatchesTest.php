<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\PlatformSync\Domain\MatchFacts;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Domain\PlatformPlayer;
use App\PlatformSync\Domain\PlayedMatch;
use App\PlatformSync\Domain\Team as PlatformTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Spec 001 AC-11 to AC-16: the FACEIT panel's list — latest finished FACEIT matches, score and
// result from the user's team, marked in Clutchlab or missing, cached, and honest when off or down.
// AC-24, AC-25: one FACEIT match's start time and map, for the upload form.
class FaceitMatchesTest extends TestCase
{
    use RefreshDatabase;

    private const STEAM = '76561198963438273';

    private const ME = 'p-me';

    private const M1 = '1-11111111-1111-1111-1111-111111111111';

    private const M2 = '1-22222222-2222-2222-2222-222222222222';

    private const M3 = '1-33333333-3333-3333-3333-333333333333';

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create();
        $this->me->forceFill(['steam_id' => self::STEAM])->save();
        $this->faceit->players[self::STEAM] = new PlatformPlayer(self::ME, 'FiveAlert');
        $this->faceit->recent[self::ME] = [
            $this->recent(self::M3, 1795300000, finished: false),
            $this->recent(self::M2, 1795283000, myFaction: 'faction2', winner: 'faction1', score: [13, 8]),
            $this->recent(self::M1, 1795281483, myFaction: 'faction1', winner: 'faction1', score: [13, 11]),
        ];
        foreach ([self::M1 => 'de_ancient', self::M2 => 'de_dust2', self::M3 => 'de_mirage'] as $id => $map) {
            $this->faceit->matches[$id] = new MatchFacts(new PlatformMatchId($id), 1795281483, $id !== self::M3, $map);
        }
    }

    private function recent(string $id, int $startedAt, bool $finished = true, string $myFaction = 'faction1', ?string $winner = 'faction1', array $score = [13, 11]): PlayedMatch
    {
        $team = fn (string $faction) => $faction === $myFaction ? [self::ME, 'mate'] : ['them'];

        return new PlayedMatch(
            new PlatformMatchId($id),
            $startedAt,
            $finished,
            'South America 5V5 Queue',
            "https://www.faceit.com/en/cs2/room/{$id}",
            new PlatformTeam($team('faction1'), $score[0]),
            new PlatformTeam($team('faction2'), $score[1]),
            match ($winner) {
                'faction1' => 1,
                'faction2' => 2,
                default => null,
            },
        );
    }

    private function list(string $query = '')
    {
        Sanctum::actingAs($this->me);

        return $this->getJson('/api/faceit/matches'.$query);
    }

    public function test_it_lists_finished_matches_newest_first_scored_from_the_users_team(): void
    {
        $this->list()
            ->assertOk()
            ->assertJsonPath('data.account.nickname', 'FiveAlert')
            ->assertJsonPath('data.reason', null)
            ->assertJsonCount(2, 'data.matches')
            ->assertJsonPath('data.matches.0.faceit_match_id', self::M2)
            ->assertJsonPath('data.matches.0.map', 'de_dust2')
            ->assertJsonPath('data.matches.0.score', ['own' => 8, 'opponent' => 13])
            ->assertJsonPath('data.matches.0.result', 'loss')
            ->assertJsonPath('data.matches.1.faceit_match_id', self::M1)
            ->assertJsonPath('data.matches.1.score', ['own' => 13, 'opponent' => 11])
            ->assertJsonPath('data.matches.1.result', 'win')
            ->assertJsonPath('data.matches.1.started_at', '2026-11-21T17:18:03.000000Z')
            ->assertJsonPath('data.matches.1.room_url', 'https://www.faceit.com/en/cs2/room/'.self::M1);
    }

    public function test_it_marks_the_users_and_teammates_uploads_but_never_another_teams(): void
    {
        $team = Team::factory()->create();
        $mate = User::factory()->create();
        $team->members()->attach([$this->me->id => ['role' => 'player'], $mate->id => ['role' => 'igl']]);
        $strangers = Team::factory()->create();

        $mine = GameMatch::factory()->create(['user_id' => $this->me->id, 'platform' => 'faceit', 'external_id' => self::M1]);
        $teammates = GameMatch::factory()->create(['user_id' => $mate->id, 'team_id' => $team->id, 'platform' => 'faceit', 'external_id' => self::M2, 'status' => 'parsing']);
        GameMatch::factory()->create(['team_id' => $strangers->id, 'platform' => 'faceit', 'external_id' => self::M2]);

        $this->list()
            ->assertJsonPath('data.matches.0.clutchlab', ['id' => $teammates->id, 'status' => 'parsing'])
            ->assertJsonPath('data.matches.1.clutchlab', ['id' => $mine->id, 'status' => 'parsed']);
    }

    public function test_a_match_not_in_clutchlab_is_missing(): void
    {
        GameMatch::factory()->create(['user_id' => $this->me->id, 'platform' => 'steam', 'external_id' => null]);

        $this->list()
            ->assertJsonPath('data.matches.0.clutchlab', null)
            ->assertJsonPath('data.matches.1.clutchlab', null);
    }

    public function test_the_list_is_kept_five_minutes_refresh_asks_again_and_a_finished_match_is_asked_once(): void
    {
        $this->list()->assertOk();
        $this->list()->assertOk();
        $this->assertSame(1, $this->faceit->callsTo('recentMatches'));
        $this->assertSame(2, $this->faceit->callsTo('match'));

        $this->list('?refresh=1')->assertOk();
        $this->assertSame(2, $this->faceit->callsTo('recentMatches'));
        $this->assertSame(2, $this->faceit->callsTo('match'));

        $this->travel(6)->minutes();
        $this->list()->assertOk();
        $this->assertSame(3, $this->faceit->callsTo('recentMatches'));
    }

    public function test_a_match_faceit_has_no_details_for_shows_without_a_map(): void
    {
        unset($this->faceit->matches[self::M1]);

        $this->list()->assertOk()->assertJsonPath('data.matches.1.map', null);
    }

    public function test_without_a_key_sync_is_off(): void
    {
        config(['clutch.faceit.key' => '']);

        $this->list()->assertStatus(503)->assertJsonPath('message', 'faceit.disabled');
        $this->assertSame([], $this->faceit->calls);
    }

    public function test_faceit_down_is_unavailable(): void
    {
        $this->faceit->unavailable = true;

        $this->list()->assertStatus(503)->assertJsonPath('message', 'faceit.unavailable');
    }

    public function test_no_account_is_a_reason_not_an_error(): void
    {
        $this->me->forceFill(['steam_id' => null])->save();
        $this->list()->assertOk()
            ->assertJsonPath('data.account', null)
            ->assertJsonPath('data.reason', 'faceit.no_steam_id')
            ->assertJsonPath('data.matches', []);

        $this->me->forceFill(['steam_id' => '76561190000000009'])->save();
        $this->list()->assertOk()->assertJsonPath('data.reason', 'faceit.not_found');
    }

    public function test_it_needs_a_login(): void
    {
        $this->getJson('/api/faceit/matches')->assertUnauthorized();
    }

    private function one(string $id)
    {
        Sanctum::actingAs($this->me);

        return $this->getJson("/api/faceit/matches/{$id}");
    }

    public function test_one_match_gives_its_start_and_map(): void
    {
        $this->one(self::M1)
            ->assertOk()
            ->assertJsonPath('data.faceit_match_id', self::M1)
            ->assertJsonPath('data.started_at', '2026-11-21T17:18:03.000000Z')
            ->assertJsonPath('data.map', 'de_ancient');
    }

    public function test_a_finished_match_is_asked_once_and_a_running_one_each_time(): void
    {
        $this->one(self::M1)->assertOk();
        $this->one(self::M1)->assertOk();
        $this->one(self::M3)->assertOk();
        $this->one(self::M3)->assertOk();

        $this->assertSame([['match', self::M1], ['match', self::M3], ['match', self::M3]], $this->faceit->calls);
    }

    public function test_an_unknown_match_is_not_found(): void
    {
        $this->one('1-44444444-4444-4444-4444-444444444444')
            ->assertNotFound()
            ->assertJsonPath('message', 'faceit.match_not_found');
    }

    public function test_one_match_when_sync_is_off_or_faceit_is_down(): void
    {
        config(['clutch.faceit.key' => '']);
        $this->one(self::M1)->assertStatus(503)->assertJsonPath('message', 'faceit.disabled');

        config(['clutch.faceit.key' => 'test-key-not-real']);
        $this->faceit->unavailable = true;
        $this->one(self::M1)->assertStatus(503)->assertJsonPath('message', 'faceit.unavailable');
    }

    public function test_a_malformed_id_never_reaches_faceit(): void
    {
        $this->one('1-nope')->assertNotFound();
        $this->one('..%2F..%2Fplayers')->assertNotFound();

        $this->assertSame([], $this->faceit->calls);
    }
}
