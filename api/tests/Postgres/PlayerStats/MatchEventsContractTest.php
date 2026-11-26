<?php

namespace Tests\Postgres\PlayerStats;

use App\PlayerStats\Adapters\Postgres\PostgresMatchEvents;
use App\PlayerStats\Domain\AwardKey;
use App\PlayerStats\Ports\MatchEvents;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\PlayerStats\Scenario;
use Tests\Postgres\PostgresTestCase;

// The MatchEvents contract: each question asked of the in-memory adapter (the domain's rules, as
// the use-case tests run them) and of the Postgres one (the SQL production runs), over spec 004's
// scenario, must get the same answer. That is what "defined once" means for the counting (AC-6).
//
// The Postgres side runs with only the analytics schema on the search_path, so a query that
// reached for a public table (matches, users, teams) fails here (AC-12).
class MatchEventsContractTest extends PostgresTestCase
{
    private Scenario $memory;

    private Scenario $database;

    private PostgresMatchEvents $postgres;

    private const VISIBLE = ['m1', 'm2', 'm3', 'm4', 'm6'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->memory = (new Scenario)->intoMemory();
        $this->database = (new Scenario)->intoDatabase();
        DB::statement('SET search_path TO analytics'); // undone with the test's transaction
        $this->postgres = new PostgresMatchEvents(DB::connection());
    }

    // Asks both adapters the same question and compares the answers, with each side's match ids
    // turned back into the scenario's match names (the two number them differently).
    /** @param  callable(MatchEvents, callable(string...): list<int>): mixed  $question */
    private function same(callable $question): mixed
    {
        $answers = [];
        foreach (['memory' => [$this->memory->memoryEvents, $this->memory], 'postgres' => [$this->postgres, $this->database]] as $kind => [$events, $s]) {
            $ids = fn (string ...$matches) => array_map(fn ($m) => $s->id($m), $matches);
            $answers[$kind] = $this->named(json_decode(json_encode($question($events, $ids)), true), array_flip($s->ids));
        }

        $this->assertEquals($answers['memory'], $answers['postgres']);

        return $answers['postgres'];
    }

    private function named(mixed $value, array $names): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $v) {
            $value[$key] = $key === 'matchId' ? $names[$v] : $this->named($v, $names);
        }

        return $value;
    }

    public function test_counts(): void
    {
        $all = $this->same(fn (MatchEvents $e, $ids) => $e->counts($ids(...self::VISIBLE)));
        $this->assertSame(['', Scenario::P1, Scenario::P2, Scenario::E1, Scenario::E2], array_column($all, 'steamId'));
        $this->assertSame(
            ['steamId' => Scenario::P1, 'kills' => 26, 'headshots' => 10, 'openingKills' => 8, 'clutchRounds' => 1, 'ecoKills' => 0, 'legKills' => 2, 'deaths' => 43, 'openingDeaths' => 1],
            $all[1],
        );
        $this->assertSame(19, $all[3]['ecoKills']);

        $this->same(fn (MatchEvents $e, $ids) => $e->counts($ids(...self::VISIBLE), [Scenario::P1, Scenario::P2, Scenario::P3, Scenario::P4]));
        $this->assertSame([], $this->same(fn (MatchEvents $e, $ids) => $e->counts($ids(...self::VISIBLE), [])));
        $this->same(fn (MatchEvents $e, $ids) => $e->counts($ids(...self::VISIBLE), null, 'de_inferno'));
        $this->assertSame([], $this->same(fn (MatchEvents $e) => $e->counts([])));
    }

    public function test_weapon_kills(): void
    {
        $this->same(fn (MatchEvents $e, $ids) => $e->weaponKills($ids(...self::VISIBLE)));
        $this->assertSame(
            [['steamId' => Scenario::E2, 'weapon' => 'awp', 'kills' => 11], ['steamId' => Scenario::E2, 'weapon' => 'm4a1', 'kills' => 11]],
            $this->same(fn (MatchEvents $e, $ids) => $e->weaponKills($ids(...self::VISIBLE), [Scenario::E2])),
        );
        $this->same(fn (MatchEvents $e, $ids) => $e->weaponKills($ids(...self::VISIBLE), null, 'de_inferno'));
    }

    public function test_players_take_the_name_from_the_first_match_given(): void
    {
        $visible = $this->same(fn (MatchEvents $e, $ids) => $e->players($ids('m6', 'm4', 'm3', 'm2', 'm1')));
        $this->assertSame(['Eco One', 'One', 'Tied', 'Two v2'], array_column($visible, 'name'));

        $this->same(fn (MatchEvents $e, $ids) => $e->players($ids('m6', 'm4', 'm3', 'm2', 'm1'), [Scenario::P1, Scenario::P2]));
        $this->assertSame('Two', $this->same(fn (MatchEvents $e, $ids) => $e->players($ids('m1', 'm3'), [Scenario::P2]))[0]['name']);
        $this->assertSame('Two v2', $this->same(fn (MatchEvents $e, $ids) => $e->players($ids('m3', 'm1'), [Scenario::P2]))[0]['name']);
        $this->assertSame([], $this->same(fn (MatchEvents $e) => $e->players([])));
    }

    public function test_scoreboards_and_sides(): void
    {
        $this->assertSame(
            [['matchId' => 'm1', 'kills' => 23, 'deaths' => 0, 'side' => 'CT'], ['matchId' => 'm2', 'kills' => 0, 'deaths' => 42, 'side' => 'T'],
                ['matchId' => 'm3', 'kills' => 1, 'deaths' => 1, 'side' => 'CT'], ['matchId' => 'm6', 'kills' => 2, 'deaths' => 0, 'side' => 'CT']],
            $this->same(fn (MatchEvents $e, $ids) => $e->scoreboard($ids(...self::VISIBLE), Scenario::P1)),
        );
        $this->assertSame([], $this->same(fn (MatchEvents $e, $ids) => $e->scoreboard($ids(...self::VISIBLE), Scenario::P3)));

        $m1 = $this->same(fn (MatchEvents $e, $ids) => $e->sides($ids('m1')[0]));
        $this->assertSame(['kills' => 25, 'deaths' => 3, 'assists' => 0, 'headshots' => 10, 'openingKills' => 8, 'clutchRounds' => 1], $m1['CT']);
        $this->same(fn (MatchEvents $e, $ids) => $e->sides($ids('m2')[0]));
        $this->assertSame([], $this->same(fn (MatchEvents $e, $ids) => $e->sides($ids('m4')[0])));
    }

    public function test_clutch_kills(): void
    {
        $all = $this->same(fn (MatchEvents $e, $ids) => $e->clutchKills($ids(...self::VISIBLE)));
        $this->assertSame([['m1', 21, 21100], ['m1', 21, 21200], ['m2', 20, 20100], ['m2', 20, 20200]], array_map(fn ($k) => [$k['matchId'], $k['round'], $k['tick']], $all));
        $this->assertSame(['head' => 91, 'chest' => 20], $all[0]['hitgroups']);

        $this->same(fn (MatchEvents $e, $ids) => $e->clutchKills($ids(...self::VISIBLE), Scenario::P2));
    }

    public function test_award_kills(): void
    {
        $kills = fn (AwardKey $key, string $id, ?string $map = null, ?string $weapon = null) => $this->same(
            fn (MatchEvents $e, $ids) => $e->awardKills($ids(...self::VISIBLE), $key, $id, $map, $weapon),
        );

        $this->assertSame([['legs' => 50, 'chest' => 40], ['head' => 30, 'legs' => 30]], array_column($kills(AwardKey::Legshots, Scenario::P1), 'hitgroups'));
        $this->assertCount(13, $kills(AwardKey::FirstBlood, Scenario::P2));
        $this->assertCount(10, $kills(AwardKey::FirstBlood, Scenario::P2, 'de_inferno'));
        $this->assertCount(10, $kills(AwardKey::Headshot, Scenario::P1));
        $this->assertCount(19, $kills(AwardKey::Eco, Scenario::E1));
        $this->assertCount(2, $kills(AwardKey::Clutches, Scenario::P2));
        $this->assertCount(11, $kills(AwardKey::OneTrick, Scenario::E2, null, 'awp'));
        $this->assertSame([], $kills(AwardKey::OneTrick, Scenario::E2));
    }
}
