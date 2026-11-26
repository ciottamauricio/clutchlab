<?php

namespace Tests\Unit\PlayerStats;

use App\PlayerStats\Domain\PlayerCounts;
use App\PlayerStats\Domain\StatLine;
use PHPUnit\Framework\TestCase;

class StatLineTest extends TestCase
{
    public function test_kd_is_kills_per_death_to_two_places(): void
    {
        $this->assertSame(0.56, StatLine::kdOf(24, 43));
        $this->assertSame(0.3, StatLine::kdOf(6, 20));
    }

    public function test_with_no_deaths_kd_is_the_kills(): void
    {
        $this->assertSame(23.0, StatLine::kdOf(23, 0));
        $this->assertSame(0.0, StatLine::kdOf(0, 0));
    }

    public function test_hs_pct_is_a_whole_percentage_and_zero_without_kills(): void
    {
        $this->assertSame(42, StatLine::hsPctOf(10, 24));
        $this->assertSame(17, StatLine::hsPctOf(1, 6));
        $this->assertSame(0, StatLine::hsPctOf(0, 0));
    }

    public function test_a_line_takes_its_counts_and_games(): void
    {
        $line = StatLine::of(new PlayerCounts('1', kills: 24, headshots: 10, openingKills: 7, clutchRounds: 1, deaths: 43, openingDeaths: 1), 3);

        $this->assertEquals(new StatLine(3, 24, 43, 10, 7, 1, 1), $line);
        $this->assertSame([0.56, 42], [$line->kd(), $line->hsPct()]);
    }
}
