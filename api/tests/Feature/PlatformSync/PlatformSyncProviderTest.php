<?php

namespace Tests\Feature\PlatformSync;

use App\PlatformSync\Adapters\Eloquent\EloquentClutchlabMatches;
use App\PlatformSync\Adapters\Eloquent\EloquentPlatformAccounts;
use App\PlatformSync\Adapters\Faceit\FaceitDataApi;
use App\PlatformSync\Adapters\Laravel\LaravelCache;
use App\PlatformSync\Application\FindAccount;
use App\PlatformSync\Application\GetMatch;
use App\PlatformSync\Application\ListMatches;
use App\PlatformSync\Application\SyncSettings;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Ports\Cache;
use App\PlatformSync\Ports\ClutchlabMatches;
use App\PlatformSync\Ports\PlatformAccounts;
use App\PlatformSync\Ports\PlatformMatches;
use App\PlatformSync\Ports\PlatformPlayers;
use Tests\TestCase;

// The composition root: what each port is bound to, and the settings read from config.
class PlatformSyncProviderTest extends TestCase
{
    public function test_sync_is_on_only_with_a_key_and_the_list_comes_from_config(): void
    {
        config(['clutch.faceit.key' => 'a-key', 'clutch.faceit.list_ttl' => 300, 'clutch.faceit.list_size' => 20]);
        $settings = $this->app->make(SyncSettings::class);
        $this->assertSame(Platform::Faceit, $settings->platform);
        $this->assertTrue($settings->enabled);
        $this->assertSame(300, $settings->listTtl);
        $this->assertSame(20, $settings->listSize);

        config(['clutch.faceit.key' => '']);
        $this->assertFalse($this->app->make(SyncSettings::class)->enabled);
    }

    public function test_each_port_is_bound_to_its_adapter(): void
    {
        // The base TestCase puts a fake in front of the platform ports; under it is the binding.
        $this->app->forgetInstance(PlatformPlayers::class);
        $this->app->forgetInstance(PlatformMatches::class);

        $this->assertInstanceOf(FaceitDataApi::class, $this->app->make(PlatformPlayers::class));
        $this->assertInstanceOf(FaceitDataApi::class, $this->app->make(PlatformMatches::class));
        $this->assertInstanceOf(EloquentPlatformAccounts::class, $this->app->make(PlatformAccounts::class));
        $this->assertInstanceOf(EloquentClutchlabMatches::class, $this->app->make(ClutchlabMatches::class));
        $this->assertInstanceOf(LaravelCache::class, $this->app->make(Cache::class));
    }

    public function test_the_use_cases_are_built_by_the_container(): void
    {
        $this->assertInstanceOf(FindAccount::class, $this->app->make(FindAccount::class));
        $this->assertInstanceOf(ListMatches::class, $this->app->make(ListMatches::class));
        $this->assertInstanceOf(GetMatch::class, $this->app->make(GetMatch::class));
    }
}
