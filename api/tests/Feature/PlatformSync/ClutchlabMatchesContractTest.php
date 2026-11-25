<?php

namespace Tests\Feature\PlatformSync;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\PlatformSync\Adapters\Eloquent\EloquentClutchlabMatches;
use App\PlatformSync\Domain\InClutchlab;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Ports\ClutchlabMatches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\PlatformSync\InMemoryClutchlabMatches;
use Tests\TestCase;

// The ClutchlabMatches contract on the in-memory adapter and the real one. The same scenario in
// both: my upload, a teammate's on our team, a stranger team's, and one of mine on another
// platform. The real adapter works visibility out from uploads and teams; the in-memory one is
// told it, so this is what keeps the two saying the same.
class ClutchlabMatchesContractTest extends TestCase
{
    use RefreshDatabase;

    private const M1 = '1-11111111-1111-1111-1111-111111111111';

    private const M2 = '1-22222222-2222-2222-2222-222222222222';

    private const M3 = '1-33333333-3333-3333-3333-333333333333';

    public static function adapters(): array
    {
        return ['in memory' => ['memory'], 'eloquent' => ['eloquent']];
    }

    /** @return array{port: ClutchlabMatches, me: int, mate: int, mine: InClutchlab, teammates: InClutchlab} */
    private function scenario(string $kind): array
    {
        if ($kind === 'memory') {
            $port = new InMemoryClutchlabMatches;
            $mine = new InClutchlab(1, 1, 'parsed');
            $teammates = new InClutchlab(2, 2, 'parsing');
            $port->file($mine, 'faceit', self::M1, [1]);
            $port->file($teammates, 'faceit', self::M2, [1, 2]);
            $port->file(new InClutchlab(3, 3, 'parsed'), 'faceit', self::M2, [3]);
            $port->file(new InClutchlab(4, 1, 'parsed'), 'steam', self::M1, [1]);
            $port->file(new InClutchlab(5, 1, 'parsed'), 'faceit', self::M3, [1]);

            return ['port' => $port, 'me' => 1, 'mate' => 2, 'mine' => $mine, 'teammates' => $teammates];
        }

        $me = User::factory()->create();
        $mate = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach([$me->id => ['role' => 'player'], $mate->id => ['role' => 'igl']]);
        $strangers = Team::factory()->create();

        $mine = GameMatch::factory()->create(['user_id' => $me->id, 'platform' => 'faceit', 'external_id' => self::M1, 'status' => 'parsed']);
        $teammates = GameMatch::factory()->create(['user_id' => $mate->id, 'team_id' => $team->id, 'platform' => 'faceit', 'external_id' => self::M2, 'status' => 'parsing']);
        GameMatch::factory()->create(['team_id' => $strangers->id, 'platform' => 'faceit', 'external_id' => self::M2]);
        GameMatch::factory()->create(['user_id' => $me->id, 'platform' => 'steam', 'external_id' => self::M1]);
        GameMatch::factory()->create(['user_id' => $me->id, 'platform' => 'faceit', 'external_id' => self::M3]);

        return [
            'port' => $this->app->make(EloquentClutchlabMatches::class),
            'me' => $me->id,
            'mate' => $mate->id,
            'mine' => new InClutchlab($mine->id, $me->id, 'parsed'),
            'teammates' => new InClutchlab($teammates->id, $mate->id, 'parsing'),
        ];
    }

    private function ids(string ...$ids): array
    {
        return array_map(fn ($id) => new PlatformMatchId($id), $ids);
    }

    #[DataProvider('adapters')]
    public function test_the_user_sees_their_own_and_their_teammates_uploads_never_another_teams(string $kind): void
    {
        $s = $this->scenario($kind);

        $this->assertEquals(
            [self::M1 => [$s['mine']], self::M2 => [$s['teammates']]],
            $s['port']->filedUnder($s['me'], Platform::Faceit, $this->ids(self::M1, self::M2)),
        );
    }

    #[DataProvider('adapters')]
    public function test_a_teammate_sees_the_team_s_upload_but_not_my_personal_one(string $kind): void
    {
        $s = $this->scenario($kind);

        $this->assertEquals(
            [self::M2 => [$s['teammates']]],
            $s['port']->filedUnder($s['mate'], Platform::Faceit, $this->ids(self::M1, self::M2)),
        );
    }

    #[DataProvider('adapters')]
    public function test_only_the_asked_matches_and_nothing_for_no_ids(string $kind): void
    {
        $s = $this->scenario($kind);

        $this->assertSame([self::M1], array_keys($s['port']->filedUnder($s['me'], Platform::Faceit, $this->ids(self::M1))));
        $this->assertSame([], $s['port']->filedUnder($s['me'], Platform::Faceit, []));
    }
}
