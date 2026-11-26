<?php

namespace Tests\Architecture;

// app/PlayerStats's dependency rule (spec 004): ModuleLayers' five rules for stats and awards.
final class PlayerStatsLayers extends ModuleLayers
{
    protected function module(): string
    {
        return 'App\PlayerStats';
    }

    protected function provider(): string
    {
        return 'App\Providers\PlayerStatsServiceProvider';
    }
}
