<?php

namespace Tests\Unit\PlatformSync;

use App\PlatformSync\Application\FindAccount;
use App\PlatformSync\Application\GetMatch;
use App\PlatformSync\Application\ListMatches;
use App\PlatformSync\Application\SyncSettings;
use App\PlatformSync\Application\Viewer;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\SteamId;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\PlatformSync\FakePlatform;
use Tests\Fakes\PlatformSync\InMemoryCache;
use Tests\Fakes\PlatformSync\InMemoryClutchlabMatches;
use Tests\Fakes\PlatformSync\InMemoryPlatformAccounts;

// The module's use cases on in-memory adapters. PHPUnit's own TestCase, not Laravel's: there is no
// app, container or database to reach, so "no database" holds by construction (spec 002 AC-6).
abstract class UseCaseTestCase extends TestCase
{
    protected const LIST_TTL = 300;

    protected FakePlatform $platform;

    protected InMemoryPlatformAccounts $accounts;

    protected InMemoryClutchlabMatches $clutchlab;

    protected InMemoryCache $cache;

    protected bool $enabled = true;

    protected function setUp(): void
    {
        $this->platform = new FakePlatform;
        $this->accounts = new InMemoryPlatformAccounts;
        $this->clutchlab = new InMemoryClutchlabMatches;
        $this->cache = new InMemoryCache;
    }

    protected function settings(): SyncSettings
    {
        return new SyncSettings(Platform::Faceit, $this->enabled, self::LIST_TTL, 20);
    }

    protected function findAccount(): FindAccount
    {
        return new FindAccount($this->settings(), $this->platform, $this->accounts, $this->cache);
    }

    protected function getMatch(): GetMatch
    {
        return new GetMatch($this->settings(), $this->platform, $this->cache);
    }

    protected function listMatches(): ListMatches
    {
        return new ListMatches($this->settings(), $this->findAccount(), $this->getMatch(), $this->platform, $this->clutchlab, $this->cache);
    }

    protected function viewer(int $userId, ?string $steamId): Viewer
    {
        return new Viewer($userId, SteamId::tryFrom($steamId));
    }
}
