<?php

namespace Tests\Feature\PlayerStats;

use App\PlayerStats\Application\AwardKills;
use App\PlayerStats\Application\Awards;
use App\PlayerStats\Application\MatchClutches;
use App\PlayerStats\Application\MatchSides;
use App\PlayerStats\Application\PlayerClutches;
use App\PlayerStats\Application\PlayerList;
use App\PlayerStats\Application\PlayerProfile;
use App\PlayerStats\Application\PlayerTotals;
use App\PlayerStats\Application\TeamBoard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlayerStatsProviderTest extends TestCase
{
    public static function useCases(): array
    {
        return array_map(fn ($c) => [$c], [PlayerTotals::class, TeamBoard::class, PlayerProfile::class, Awards::class, AwardKills::class, PlayerList::class, PlayerClutches::class, MatchSides::class, MatchClutches::class]);
    }

    #[DataProvider('useCases')]
    public function test_the_provider_wires_every_use_case(string $useCase): void
    {
        $this->assertInstanceOf($useCase, $this->app->make($useCase));
    }
}
