<?php

namespace Tests\Architecture;

// app/PlatformSync's dependency rule (spec 002): ModuleLayers' five rules for FACEIT sync.
final class PlatformSyncLayers extends ModuleLayers
{
    protected function module(): string
    {
        return 'App\PlatformSync';
    }

    protected function provider(): string
    {
        return 'App\Providers\PlatformSyncServiceProvider';
    }
}
