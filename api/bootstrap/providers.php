<?php

use App\Providers\AppServiceProvider;
use App\Providers\PlatformSyncServiceProvider;
use App\Providers\PlayerStatsServiceProvider;

return [
    AppServiceProvider::class,
    PlatformSyncServiceProvider::class,
    PlayerStatsServiceProvider::class,
];
