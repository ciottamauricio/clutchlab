<?php

namespace Tests\Unit\PlatformSync;

use App\PlatformSync\Domain\MatchFacts;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\PlatformAccount;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Domain\PlatformPlayer;
use App\PlatformSync\Domain\PlayedMatch;
use App\PlatformSync\Domain\SteamId;
use App\PlatformSync\Domain\Team;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ValuesTest extends TestCase
{
    private const ID = '1-3dec19fc-88c7-421f-8080-b90d23f2f1f9';

    private function played(?int $winner = 2): PlayedMatch
    {
        return new PlayedMatch(new PlatformMatchId(self::ID), 1795281483, true, 'South America 5V5 Queue',
            'https://www.faceit.com/en/cs2/room/'.self::ID, new Team(['me', 'mate'], 11), new Team(['them'], 13), $winner);
    }

    // What the database and Redis cache stores do to a value: they unserialize no objects.
    private function throughTheCache(array $value): array
    {
        return unserialize(serialize($value), ['allowed_classes' => false]);
    }

    public function test_a_profile_without_a_steam_id_has_none(): void
    {
        $this->assertNull(SteamId::tryFrom(null));
        $this->assertNull(SteamId::tryFrom(''));
        $this->assertSame('76561198963438273', SteamId::tryFrom('76561198963438273')->value);
    }

    public function test_ids_are_never_empty(): void
    {
        foreach ([fn () => new SteamId(''), fn () => new PlatformMatchId('')] as $empty) {
            try {
                $empty();
                $this->fail('expected an empty id to be refused');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_account_is_for_the_steam_id_it_was_found_for(): void
    {
        $account = new PlatformAccount(7, Platform::Faceit, new SteamId('76561198963438273'), new PlatformPlayer('p-1', 'FiveAlert'));

        $this->assertTrue($account->isFor(new SteamId('76561198963438273')));
        $this->assertFalse($account->isFor(new SteamId('76561190000000001')));
    }

    public function test_a_played_match_knows_the_players_team(): void
    {
        $match = $this->played();

        $this->assertSame(1, $match->teamOf('mate'));
        $this->assertSame(2, $match->teamOf('them'));
        $this->assertNull($match->teamOf('nobody'));
        $this->assertSame(13, $match->opponentOf(1)->score);
    }

    public function test_a_match_is_won_by_team_one_team_two_or_neither(): void
    {
        $this->assertNull($this->played(winner: null)->winner);

        $this->expectException(InvalidArgumentException::class);
        $this->played(winner: 3);
    }

    public function test_cached_values_survive_a_store_that_unserializes_no_objects(): void
    {
        $played = $this->played();
        $facts = new MatchFacts(new PlatformMatchId(self::ID), 1795281483, true, 'de_ancient');

        $this->assertEquals($played, PlayedMatch::fromArray($this->throughTheCache($played->toArray())));
        $this->assertEquals($facts, MatchFacts::fromArray($this->throughTheCache($facts->toArray())));
        $this->assertEquals(
            new MatchFacts(new PlatformMatchId(self::ID), null, false, null),
            MatchFacts::fromArray($this->throughTheCache((new MatchFacts(new PlatformMatchId(self::ID), null, false, null))->toArray())),
        );
    }
}
