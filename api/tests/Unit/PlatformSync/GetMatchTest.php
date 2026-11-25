<?php

namespace Tests\Unit\PlatformSync;

use App\PlatformSync\Application\SyncOff;
use App\PlatformSync\Domain\MatchFacts;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Ports\PlatformUnavailable;

// Spec 001 AC-24, AC-25: one platform match's start and map, kept once it's finished.
class GetMatchTest extends UseCaseTestCase
{
    private const FINISHED = '1-11111111-1111-1111-1111-111111111111';

    private const RUNNING = '1-33333333-3333-3333-3333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();
        $this->platform->matches[self::FINISHED] = new MatchFacts(new PlatformMatchId(self::FINISHED), 1795281483, true, 'de_ancient');
        $this->platform->matches[self::RUNNING] = new MatchFacts(new PlatformMatchId(self::RUNNING), 1795300000, false, 'de_mirage');
    }

    private function get(string $id): ?MatchFacts
    {
        return $this->getMatch()->execute(new PlatformMatchId($id));
    }

    public function test_one_match_gives_its_start_and_map(): void
    {
        $facts = $this->get(self::FINISHED);

        $this->assertSame(1795281483, $facts->startedAt);
        $this->assertSame('de_ancient', $facts->map);
    }

    public function test_a_finished_match_is_asked_once_and_a_running_one_each_time(): void
    {
        $this->assertEquals($this->get(self::FINISHED), $this->get(self::FINISHED));
        $this->get(self::RUNNING);
        $this->get(self::RUNNING);

        $this->assertSame([['match', self::FINISHED], ['match', self::RUNNING], ['match', self::RUNNING]], $this->platform->calls);
    }

    public function test_an_unknown_match_is_null_and_asked_again_next_time(): void
    {
        $unknown = '1-44444444-4444-4444-4444-444444444444';

        $this->assertNull($this->get($unknown));
        $this->assertNull($this->get($unknown));
        $this->assertSame(2, $this->platform->callsTo('match'));
    }

    public function test_without_a_key_sync_is_off(): void
    {
        $this->enabled = false;

        try {
            $this->get(self::FINISHED);
            $this->fail('expected SyncOff');
        } catch (SyncOff) {
            $this->assertSame([], $this->platform->calls);
        }
    }

    public function test_the_platform_being_down_passes_through(): void
    {
        $this->platform->unavailable = true;

        $this->expectException(PlatformUnavailable::class);
        $this->get(self::FINISHED);
    }
}
