<?php

namespace App\PlatformSync\Application;

use App\PlatformSync\Domain\PlatformAccount;
use App\PlatformSync\Ports\Cache;
use App\PlatformSync\Ports\PlatformAccounts;
use App\PlatformSync\Ports\PlatformPlayers;
use App\PlatformSync\Ports\PlatformUnavailable;

// Finds the user's account on the platform from the Steam ID on their profile, and remembers it.
class FindAccount
{
    // How long "no account has this Steam ID" is believed, so a page load doesn't ask the platform
    // every time for someone who isn't on it.
    private const NONE_TTL = 300;

    public function __construct(
        private SyncSettings $settings,
        private PlatformPlayers $players,
        private PlatformAccounts $accounts,
        private Cache $cache,
    ) {}

    /** @throws SyncOff|PlatformUnavailable */
    public function execute(Viewer $viewer): AccountLookup
    {
        // Checked first, so a user without a Steam ID on a keyless instance hears "sync is off",
        // the real reason, rather than "add a Steam ID".
        if (! $this->settings->enabled) {
            throw new SyncOff;
        }

        $platform = $this->settings->platform;
        $stored = $this->accounts->of($viewer->userId, $platform);

        if ($viewer->steamId === null) {
            if ($stored !== null) {
                $this->accounts->remove($viewer->userId, $platform);
            }

            return AccountLookup::missing(MissingAccount::NoSteamId);
        }
        if ($stored !== null && $stored->isFor($viewer->steamId)) {
            return AccountLookup::found($stored);
        }

        $noneKey = $this->settings->key('none', $viewer->steamId->value);
        if ($this->cache->has($noneKey)) {
            return AccountLookup::missing(MissingAccount::NotFound);
        }

        $player = $this->players->findPlayerBySteamId($viewer->steamId);
        if ($player === null) {
            $this->cache->put($noneKey, true, self::NONE_TTL);
            if ($stored !== null) {
                $this->accounts->remove($viewer->userId, $platform);
            }

            return AccountLookup::missing(MissingAccount::NotFound);
        }

        $account = new PlatformAccount($viewer->userId, $platform, $viewer->steamId, $player);
        $this->accounts->save($account);

        return AccountLookup::found($account);
    }
}
