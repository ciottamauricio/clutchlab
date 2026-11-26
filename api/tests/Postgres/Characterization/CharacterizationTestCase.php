<?php

namespace Tests\Postgres\Characterization;

use Tests\Fixtures\PlayerStats\Scenario;
use Tests\Postgres\PostgresTestCase;

// The HTTP answers of spec 004's endpoints over the scenario, pinned before their code moved
// (AC-1, AC-2). Ids come from Postgres sequences, which don't roll back between tests, so expected
// values name matches and teams through the scenario, never by number.
abstract class CharacterizationTestCase extends PostgresTestCase
{
    protected $seed = true; // the permission grants (awards.view) live in the database

    protected Scenario $s;

    protected function setUp(): void
    {
        parent::setUp();

        $this->s = (new Scenario)->intoDatabase();
    }

    // Before spec 004 the order among equal values was whatever Postgres returned; Q6 defines it
    // (the value descending, then Steam ID ascending). Until the switch, the tests compare tied
    // rows in that order whatever order they came in.
    /** @param list<array<string, mixed>> $rows */
    protected function tiesBySteamId(array $rows, string $field): array
    {
        usort($rows, fn ($a, $b) => [(float) $b[$field], $a['steam_id']] <=> [(float) $a[$field], $b['steam_id']]);

        return $rows;
    }

    /** @return array<string, mixed> one drill-down or clutch kill, as the answers write it */
    protected function kill(string $victim, string $weapon, int $tick, bool $headshot = false, ?array $hitgroups = null, string $side = 'CT'): array
    {
        return ['victim_name' => $victim, 'weapon' => $weapon, 'side' => $side, 'headshot' => $headshot, 'tick' => $tick, 'hitgroups' => $hitgroups];
    }
}
