<?php

namespace App\Providers;

use App\PlatformSync\Adapters\Eloquent\EloquentClutchlabMatches;
use App\PlatformSync\Adapters\Eloquent\EloquentPlatformAccounts;
use App\PlatformSync\Adapters\Faceit\FaceitDataApi;
use App\PlatformSync\Adapters\Laravel\LaravelCache;
use App\PlatformSync\Application\SyncSettings;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Ports\Cache;
use App\PlatformSync\Ports\ClutchlabMatches;
use App\PlatformSync\Ports\PlatformAccounts;
use App\PlatformSync\Ports\PlatformMatches;
use App\PlatformSync\Ports\PlatformPlayers;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;

// app/PlatformSync's composition root: the only class outside the module's adapters that names
// them (tests/Architecture rule 5). Its use cases need no binding; the container builds them.
class PlatformSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Read when a use case is built, not at boot, so a test can change the config first.
        $this->app->bind(SyncSettings::class, fn () => new SyncSettings(
            Platform::Faceit,
            enabled: (string) config('clutch.faceit.key') !== '',
            listTtl: (int) config('clutch.faceit.list_ttl'),
            listSize: (int) config('clutch.faceit.list_size'),
        ));

        $this->app->bind(FaceitDataApi::class, fn () => new FaceitDataApi(
            $this->app->make(Http::class),
            config('clutch.faceit.api'),
            (string) config('clutch.faceit.key'),
            (int) config('clutch.faceit.timeout'),
        ));
        $this->app->bind(PlatformPlayers::class, FaceitDataApi::class);
        $this->app->bind(PlatformMatches::class, FaceitDataApi::class);

        $this->app->bind(PlatformAccounts::class, EloquentPlatformAccounts::class);
        $this->app->bind(ClutchlabMatches::class, EloquentClutchlabMatches::class);
        $this->app->bind(Cache::class, LaravelCache::class);
    }
}
