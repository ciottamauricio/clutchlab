<?php

namespace Tests\Unit\PlayerStats;

use App\PlayerStats\Application\PlayerTotals;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PlayerStats\Scenario;

// The module's use cases over spec 004's scenario, in memory. PHPUnit's own TestCase: no app,
// container or database to reach (AC-5). The same cases run over HTTP on Postgres in
// tests/Postgres/Characterization.
abstract class UseCaseTestCase extends TestCase
{
    protected Scenario $s;

    protected function setUp(): void
    {
        $this->s = (new Scenario)->intoMemory();
    }

    protected function totals(): PlayerTotals
    {
        return new PlayerTotals($this->s->memoryEvents);
    }

    /** @return list<int> */
    protected function ids(string ...$matches): array
    {
        return array_map(fn ($m) => $this->s->id($m), $matches);
    }
}
